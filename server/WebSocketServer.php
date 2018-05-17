<?php
require_once('WebSocketUser.php');

abstract class WebSocketServer
{
    protected $maxBufferSize;

    protected $master;
    protected $sockets = array();
    protected $users = array();

    protected $heldMessages = array();
    protected $interactive = true;

    protected $requireOrigin = false; //requires the header origin
    protected $requireSWSP = false;   // requires the header secWebSocketProtocol
    protected $requireSWSE = false;   // requires the header secWebSocketExtensions

    function __construct($addr, $port, $bufferLength = 2048)
    {
        $this->maxBufferSize = $bufferLength;
        $this->master = socket_create(AF_INET, SOCK_STREAM, SOL_TCP) or die("Failed: socket_create()");

        socket_set_option($this->master, SOL_SOCKET, SO_REUSEADDR, 1) or die("Failed: socket_option()");
        socket_bind($this->master, $addr, $port) or die("Failed: socket_bind()");
        socket_listen($this->master, 10) or die("Failed: socket_listen()");

        $this->sockets['m'] = $this->master;

        $this->stdout("Server started\nListening on: $addr:$port\nMaster socket: " . $this->master . "\r\n");
    }

    abstract protected function process($user, $message); // Called immediately when the data is received.
    abstract protected function connected($user);         // Called after the handshake response is sent to the client.
    abstract protected function closed($user);            // Called after the connection is closed.
    abstract protected function connecting($user);        // after the instance of the User is created, but before the handshake has completed.
    abstract protected function tick();                   // any process that should happen periodically (at least once per second, but possibly more often.

    protected function write($user, $message)
    {
        if (!is_string($message)) {
            $message = json_encode($message);
        }

        $message = $this->frame($message, $user);
        @socket_write($user->socket, $message, strlen($message));
    }

    protected function send($user, $message)
    {

        if (!$user->handshake) {
            // User has not yet performed their handshake.  Store for sending later.
            $holdingMessage = array('user' => $user, 'message' => $message);
            $this->heldMessages[] = $holdingMessage;
        }

        foreach ($this->users as $user) {
            $this->write($user, $message);
        }

    }

    protected function sendHeldMessages()
    {
        // Core maintenance processes, such as retrying failed messages.
        foreach ($this->heldMessages as $key => $hm) {
            $user = !empty($this->users[$hm['user']->id]) ? $this->users[$hm['user']->id] : null;

            if ($user && $user->socket == $hm['user']->socket) {

                if ($user->handshake) {
                    unset($this->heldMessages[$key]);
                    $this->send($user, $hm['message']);
                }

            } else {
                // If they're no longer in the list of connected users, drop the message.
                unset($this->heldMessages[$key]);
            }
        }
    }

    /**
     * Main processing loop
     */
    public function run()
    {
        while (true) {

            if (empty($this->sockets)) {
                $this->sockets['m'] = $this->master;
            }

            $read = $this->sockets;

            $this->sendHeldMessages();
            $this->tick();

            $write = $except = null;
            @socket_select($read, $write, $except, 1);

            foreach ($read as $socket) {

                if ($socket == $this->master) {

                    $client = socket_accept($socket);
                    if ($client < 0) {
                        $this->stderr("Failed: socket_accept()");
                        continue;
                    } else {
                        $this->connect($client);
                        $this->stdout("Client connected: " . $client);
                    }

                } else {

                    $numBytes = @socket_recv($socket, $buffer, $this->maxBufferSize, 0);

                    if ($numBytes === false) {

                        $this->handleError($socket);

                    } elseif ($numBytes == 0) {

                        $this->stderr("TCP connection lost: " . $socket);
                        $this->disconnect($socket);

                    } else {

                        $user = $this->getUserBySocket($socket);

                        if (!$user->handshake) {
                            $tmp = str_replace("\r", '', $buffer);
                            if (strpos($tmp, "\n\n") === false) {
                                continue; // If the client has not finished sending the header, then wait before sending our upgrade response.
                            }
                            $this->doHandshake($user, $buffer);
                        } else {
                            //split packet into frame and send it to deframe
                            $this->split_packet($numBytes, $buffer, $user);
                        }
                    }
                }
            }
        }
    }

    protected function handleError($socket){
        $sockErrNo = socket_last_error($socket);
        switch ($sockErrNo) {
            case 102: // ENETRESET    -- Network dropped connection because of reset
            case 103: // ECONNABORTED -- Software caused connection abort
            case 104: // ECONNRESET   -- Connection reset by peer
            case 108: // ESHUTDOWN    -- Cannot send after transport endpoint shutdown -- probably more of an error on our part, if we're trying to write after the socket is closed.  Probably not a critical error, though.
            case 110: // ETIMEDOUT    -- Connection timed out
            case 111: // ECONNREFUSED -- Connection refused -- We shouldn't see this one, since we're listening... Still not a critical error.
            case 112: // EHOSTDOWN    -- Host is down -- Again, we shouldn't see this, and again, not critical because it's just one connection and we still want to listen to/for others.
            case 113: // EHOSTUNREACH -- No route to host
            case 121: // EREMOTEIO    -- Rempte I/O error -- Their hard drive just blew up.
            case 125: // ECANCELED    -- Operation canceled

                $this->stderr("Unusual disconnect on socket " . $socket);
                $this->disconnect($socket, true, $sockErrNo); // disconnect before clearing error, in case someone with their own implementation wants to check for error conditions on the socket.
                break;
            default:
                $this->stderr('Socket error: ' . socket_strerror($sockErrNo));
        }
    }

    protected function connect($socket)
    {
        $user = new WebSocketUser(uniqid('u'), $socket);

        $this->users[$user->id] = $user;
        $this->sockets[$user->id] = $socket;

        $this->connecting($user);
    }

    protected function disconnect($socket, $triggerClosed = true, $sockErrNo = null)
    {

        $disconnectedUser = $this->getUserBySocket($socket);

        if (!is_null($disconnectedUser)) {
            unset($this->users[$disconnectedUser->id]);

            if (array_key_exists($disconnectedUser->id, $this->sockets)) {
                unset($this->sockets[$disconnectedUser->id]);
            }

            if (!is_null($sockErrNo)) {
                socket_clear_error($socket);
            }

            if ($triggerClosed) {
                $this->closed($disconnectedUser);
                socket_close($disconnectedUser->socket);
            } else {
                $message = $this->frame('', $disconnectedUser, 'close');
                @socket_write($disconnectedUser->socket, $message, strlen($message));
            }

            $this->stdout("Client disconnected: " . $disconnectedUser->socket . "\r\n");
        }
    }

    protected function  doHandshake($user, $buffer)
    {
        $headers = array();

        $lines = explode("\n", $buffer);
        foreach ($lines as $line) {

            if (strpos($line, ":") !== false) {
                $header = explode(":", $line, 2);
                $headers[strtolower(trim($header[0]))] = trim($header[1]);
            } elseif (stripos($line, "get ") !== false) {
                preg_match("/GET (.*) HTTP/i", $buffer, $reqResource);
                $headers['get'] = trim($reqResource[1]);
            }
        }

        if (isset($headers['get'])) {
            $user->requestedResource = $headers['get'];
        } else {
            $handshakeResponse = "HTTP/1.1 405 Method Not Allowed\r\n\r\n";
        }

        if ($this->isBadRequest($headers)) {
            $handshakeResponse = "HTTP/1.1 400 Bad Request";
        }

        if (!isset($headers['sec-websocket-version']) || strtolower($headers['sec-websocket-version']) != 13) {
            $handshakeResponse = "HTTP/1.1 426 Upgrade Required\r\nSec-WebSocketVersion: 13";
        }

        if (!$this->checkOrigin($headers['origin'])){
            $handshakeResponse = "HTTP/1.1 403 Forbidden";
        }

        if (isset($handshakeResponse)) {
            socket_write($user->socket, $handshakeResponse, strlen($handshakeResponse));
            $this->disconnect($user->socket);
            return;
        }

        $user->headers = $headers;
        $user->handshake = $buffer;

        $magicGUID = "258EAFA5-E914-47DA-95CA-C5AB0DC85B11";
        $webSocketKeyHash = sha1($headers['sec-websocket-key'] . $magicGUID);

        $rawToken = "";
        for ($i = 0; $i < 20; $i++) {
            $rawToken .= chr(hexdec(substr($webSocketKeyHash, $i * 2, 2)));
        }

        $handshakeToken = base64_encode($rawToken) . "\r\n";

        $subProtocol = (isset($headers['sec-websocket-protocol'])) ? $this->processProtocol($headers['sec-websocket-protocol']) : "";
        $extensions = (isset($headers['sec-websocket-extensions'])) ? $this->processExtensions($headers['sec-websocket-extensions']) : "";

        $handshakeResponse = "HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Accept: $handshakeToken$subProtocol$extensions\r\n";
        socket_write($user->socket, $handshakeResponse, strlen($handshakeResponse));
        $this->connected($user);

        $this->stdout($handshakeResponse);
    }

    protected function checkHost($hostName)
    {
        return true; // Override and return false if the host is not one that you would expect.
    }

    protected function checkOrigin($origin)
    {
        if($this->requireOrigin){
            // Override and return false if the origin is not one that you would expect.
        }

        return true;
    }

    protected function checkWSProtocol($protocol)
    {
        if($this->requireSWSP){
            // Override and return false if a protocol is not found that you would expect.
        }

        return true;
    }

    protected function checkWSExtensions($extensions)
    {
        if($this->requireSWSE){
            // Override and return false if an extension is not found that you would expect.
        }

        return true;
    }

    protected function processProtocol($protocol)
    {
        //return "Sec-WebSocket-Protocol: SelectedProtocolFromClientList\r\n";
        return "";

        // The carriage return/newline combo must appear at the end of a non-empty string, and must not
        // appear at the beginning of the string nor in an otherwise empty string, or it will be considered part of
        // the response body, which will trigger an error in the client as it will not be formatted correctly.
    }

    protected function processExtensions($extensions)
    {
        //return "Sec-WebSocket-Extensions: SelectedExtensions\r\n";
        return "";
    }

    protected function isBadRequest($headers){
        return !isset($headers['host']) || !$this->checkHost($headers['host'])
        || !isset($headers['upgrade']) || strtolower($headers['upgrade']) != 'websocket'
        || !isset($headers['connection']) || strpos(strtolower($headers['connection']), 'upgrade') === FALSE
        || !isset($headers['sec-websocket-key'])
        || (isset($headers['sec-websocket-protocol']) && !$this->checkWSProtocol($headers['sec-websocket-protocol']))
        || (isset($headers['sec-websocket-extensions']) && !$this->checkWSExtensions($headers['sec-websocket-extensions']));
    }

    protected function getUserBySocket($socket)
    {
        $userId = array_search($socket, $this->sockets);

        $user = $this->users[$userId];
        return $user && $user->socket == $socket ? $user : null;
    }

    public function stdout($message)
    {
        if ($this->interactive) {
            echo "$message\n";
        }
    }

    public function stderr($message)
    {
        if ($this->interactive) {
            echo "$message\n";
        }
    }

    //check if packe has more than one frame and process each frame individually
    protected function split_packet($length, $packet, $user)
    {
        //add PartialPacket and calculate the new $length
        if ($user->handlingPartialPacket) {
            $packet = $user->partialBuffer . $packet;
            $user->handlingPartialPacket = false;
            $length = strlen($packet);
        }

        $fullpacket = $packet;
        $frame_pos = 0;
        $frame_id = 1;

        while ($frame_pos < $length) {
            $headers = $this->extractHeaders($packet);
            $headers_size = $this->calcoffset($headers);
            $framesize = $headers['length'] + $headers_size;

            //split frame from packet and process it
            $frame = substr($fullpacket, $frame_pos, $framesize);
            if (($message = $this->deframe($frame, $user, $headers)) !== FALSE) {
                if (!$user->closed) {
                    if (preg_match('//u', $message)) { //if is utf-8 encoded
                        $this->process($user, $message);
                    }
                }
            }

            //get the new position also modify packet data
            $frame_pos += $framesize;
            $packet = substr($fullpacket, $frame_pos);
            $frame_id++;
        }
    }

    protected function frame($message, $user, $messageType = 'text', $messageContinues = false)
    {
        switch ($messageType) {
            case 'continuous': $b1 = 0; break;
            case 'text':
                $b1 = ($user->sendingContinuous) ? 0 : 1;
            case 'binary':
                break;
                $b1 = ($user->sendingContinuous) ? 0 : 2;
                break;
            case 'close': $b1 = 8; break;
            case 'ping':  $b1 = 9; break;
            case 'pong':  $b1 = 10; break;
        }

        if ($messageContinues) {
            $user->sendingContinuous = true;
        } else {
            $b1 += 128;
            $user->sendingContinuous = false;
        }

        $length = strlen($message);
        $lengthField = "";

        if ($length < 126) {

            $b2 = $length;

        } else {

            $b2 = $length <= 65536 ? 126 : 127;
            $pad = $length <= 65536 ? 3 : 8;

            $hexLength = dechex($length);

            if (strlen($hexLength) % 2 == 1) {
                $hexLength = '0' . $hexLength;
            }

            $n = strlen($hexLength) - 2;

            for ($i = $n; $i >= 0; $i = $i - 2) {
                $lengthField = chr(hexdec(substr($hexLength, $i, 2))) . $lengthField;
            }

            $lengthField = str_pad($lengthField, $pad, chr(0), STR_PAD_LEFT);

        }

        return chr($b1) . chr($b2) . $lengthField . $message;
    }

    protected function deframe($message, &$user)
    {
        $headers = $this->extractHeaders($message);

        if (!in_array($headers['opcode'], [0,1,2,8,9,10])) {
            $this->disconnect($user->socket);
            return false;
        }

        if($headers['opcode'] == 8){
            $this->disconnect($user->socket);
            $user->closed = true;
            return "";
        }

        if ($this->checkRSVBits($headers, $user)) {
            return false;
        }

        $payload = $user->partialMessage . $this->extractPayload($message, $headers);

        //pongReply
        if ($headers['opcode'] == 9) {
            $reply = $this->frame($payload, $user, 'pong');
            socket_write($user->socket, $reply, strlen($reply));
            return false;
        }

        $payload = $this->applyMask($headers, $payload);

        $mbstring = extension_loaded('mbstring');
        $length = $headers['length'];

        if (($mbstring && $length > mb_strlen($payload))
         || (!$mbstring && $length > strlen($payload) )) {
            $user->handlingPartialPacket = true;
            $user->partialBuffer = $message;
            return false;
        }

        if ($headers['fin']) {
            $user->partialMessage = "";

            $payload = json_decode($payload);
            $payload->user = intval($user->socket);
            $payload = json_encode($payload);

            return $payload;
        }

        $user->partialMessage = $payload;
        return false;
    }

    protected function extractHeaders($message)
    {
        $header = [
            'fin'     => $message[0] & chr(128),
            'rsv1'    => $message[0] & chr(64),
            'rsv2'    => $message[0] & chr(32),
            'rsv3'    => $message[0] & chr(16),
            'opcode'  => ord($message[0]) & 15,
            'hasmask' => $message[1] & chr(128),
            'length'  => 0,
            'mask'    => ""
        ];

        $header['length'] = (ord($message[1]) >= 128) ? ord($message[1]) - 128 : ord($message[1]);

        if ($header['length'] == 126) {

            if ($header['hasmask']) {
                $header['mask'] = $message[4] . $message[5] . $message[6] . $message[7];
            }

            $header['length'] = ord($message[2]) * 256 + ord($message[3]);

        } elseif ($header['length'] == 127) {

            if ($header['hasmask']) {
                $header['mask'] = $message[10] . $message[11] . $message[12] . $message[13];
            }

            $header['length'] = ord($message[2]) * 65536 * 65536 * 65536 * 256
                + ord($message[3]) * 65536 * 65536 * 65536
                + ord($message[4]) * 65536 * 65536 * 256
                + ord($message[5]) * 65536 * 65536
                + ord($message[6]) * 65536 * 256
                + ord($message[7]) * 65536
                + ord($message[8]) * 256
                + ord($message[9]);

        } elseif ($header['hasmask']) {

            $header['mask'] = $message[2] . $message[3] . $message[4] . $message[5];
        }

        return $header;
    }

    protected function extractPayload($message, $headers)
    {
        $offset = 2;
        if ($headers['hasmask']) {
            $offset += 4;
        }
        if ($headers['length'] > 65535) {
            $offset += 8;
        } elseif ($headers['length'] > 125) {
            $offset += 2;
        }

        return substr($message, $offset);
    }

    protected function applyMask($headers, $payload)
    {
        $effectiveMask = "";

        if ($headers['hasmask']) {
            $mask = $headers['mask'];
        } else {
            return $payload;
        }

        while (strlen($effectiveMask) < strlen($payload)) {
            $effectiveMask .= $mask;
        }

        while (strlen($effectiveMask) > strlen($payload)) {
            $effectiveMask = substr($effectiveMask, 0, -1);
        }

        return $effectiveMask ^ $payload;
    }

    protected function checkRSVBits($headers, $user)
    { // override this method if you are using an extension where the RSV bits are used.
        if (ord($headers['rsv1']) + ord($headers['rsv2']) + ord($headers['rsv3']) > 0) {
            //$this->disconnect($user->socket); // todo: fail connection
            return true;
        }
        return false;
    }

    protected function calcoffset($headers)
    {
        $offset = 2;
        if ($headers['hasmask']) {
            $offset += 4;
        }
        if ($headers['length'] > 65535) {
            $offset += 8;
        } elseif ($headers['length'] > 125) {
            $offset += 2;
        }
        return $offset;
    }

}

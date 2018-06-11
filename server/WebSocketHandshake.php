<?php

abstract class WebSocketHandshake
{

    public $response;
    public $status = false;

    protected $requireOrigin = false; //requires the header origin
    protected $requireSWSP = false;   //requires the header secWebSocketProtocol
    protected $requireSWSE = false;   //requires the header secWebSocketExtensions

    abstract protected function checkHost($hostName);
    abstract protected function checkOrigin($origin);
    abstract protected function checkWSProtocol($protocol);
    abstract protected function checkWSExtensions($extensions);

    function __construct($user, $buffer)
    {
        $headers = [];

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
        
        if (!$this->checkOrigin($headers['origin']))
            $this->response = "HTTP/1.1 403 Forbidden";
    
        if (!isset($headers['sec-websocket-version']) || strtolower($headers['sec-websocket-version']) != 13)
            $this->response = "HTTP/1.1 426 Upgrade Required\r\nSec-WebSocketVersion: 13";
        
        if (!isset($headers['host']) || !$this->checkHost($headers['host'])
        || !isset($headers['upgrade']) || strtolower($headers['upgrade']) != 'websocket'
        || !isset($headers['connection']) || strpos(strtolower($headers['connection']), 'upgrade') === FALSE
        || !isset($headers['sec-websocket-key'])
        || (isset($headers['sec-websocket-protocol']) && !$this->checkWSProtocol($headers['sec-websocket-protocol']))
        || (isset($headers['sec-websocket-extensions']) && !$this->checkWSExtensions($headers['sec-websocket-extensions'])))
            $this->response = "HTTP/1.1 400 Bad Request";
    
        if (!isset($headers['get']))
            $this->response = "HTTP/1.1 405 Method Not Allowed\r\n\r\n";
    
        if ($this->response) 
            return;
        
        if (isset($headers['get'])) {
            $user->requestedResource = $headers['get'];
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
        
        $this->response = $handshakeResponse;
        $this->status = true;
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

}
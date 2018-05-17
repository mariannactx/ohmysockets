<?php
require_once('WebSocketServer.php');

class Server extends WebSocketServer
{
    protected $maxBufferSize = 1048576; //1MB

    protected function process($user, $message)
    {
        $this->send($user, $message);
    }

    protected function connecting($user)
    {

    }

    protected function connected($user)
    {
        $this->write($user, ["type" => "connected", "user" => intval($user->socket)]);
    }

    protected function closed($user)
    {
        // Do nothing: This is where cleanup would go, in case the user had any sort of
        // open files or other objects associated with them.  This runs after the socket
        // has been closed, so there is no need to clean up the socket itself here.
        $this->send($user, ["type" => "disconnected", "user" => intval($user->socket)]);
    }

    protected function tick()
    {

    }
}

?>
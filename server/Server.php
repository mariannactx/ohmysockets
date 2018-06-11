<?php

class Server extends WebSocketServer
{
    protected $maxBufferSize = 1048576; //1MB


    protected function connecting($user){}

    protected function connected($user)
    {
        $this->write($user, ["type" => "connected", "user" => $user->id]);
    }

    protected function tick(){}

    protected function process($user, $message)
    {
        $this->send($user, $message);
    }
    
    protected function closed($user)
    {
        $this->send($user, ["type" => "disconnected", "user" => $user->id]);
    }
}

?>
<?php

class Handshake extends WebSocketHandshake
{
    
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
}

?>
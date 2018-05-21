<?php

abstract class WebSocketUser {

  public $socket;
  public $id;
  public $headers = array();
  public $handshake = false;

  public $handlingPartialPacket = false;
  public $partialBuffer = "";

  public $sendingContinuous = false;
  public $partialMessage = "";
  
  public $closed = false;

  function __construct($socket) {
    $this->socket = $socket;
    $this->setId();
  }

  protected function setId(){
        $this->id = uniqid('u');
  }

}
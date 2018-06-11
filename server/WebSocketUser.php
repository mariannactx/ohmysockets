<?php

abstract class WebSocketUser {

  public $socket;
  public $id;
  public $headers = [];
  public $handshake = false;
  
  protected $properties = [];

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

  public function getProperty($property){
    return $this->properties[$property];
  }

  public function setProperty($property, $value){
    $this->properties[$property] = $value;
    return true;
  }

}
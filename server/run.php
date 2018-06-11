<?php
require_once('autoloader.php');

$server = new Server("192.168.0.18", '47401');

try {
    $server->run();
} catch (Exception $e) {
    $server->stdout($e->getMessage());
}
?>
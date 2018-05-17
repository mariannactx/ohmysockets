<?php
require_once('Server.php');

$server = new Server("192.168.15.6", '47401');

try {
    $server->run();
} catch (Exception $e) {
    $server->stdout($e->getMessage());
}
?>
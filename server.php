<?php

require __DIR__ . '/vendor/autoload.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use QuizGame\QuizGameServer;

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new QuizGameServer()
        )
    ),
    8080
);

echo "WebSocket server started on port 8080\n";
$server->run();

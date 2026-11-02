<?php

// Router of the PHP built-in server used by CurlTransportTest: echoes the request.
if ($_SERVER['REQUEST_URI'] === '/redirect') {
    header('Location: /elsewhere', true, 302);

    return true;
}
header('Content-Type: application/json');
header('X-Echo: yes');
http_response_code($_SERVER['REQUEST_URI'] === '/missing' ? 404 : 200);
echo json_encode([
    'method' => $_SERVER['REQUEST_METHOD'],
    'uri' => $_SERVER['REQUEST_URI'],
    'auth' => isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : null,
    'body' => file_get_contents('php://input'),
]);

<?php

/*
 * Simulated https://api.pathlyhq.com for the integration tests.
 *
 * Runs in its own container, reachable as api.pathlyhq.com on the Docker
 * network, with a test certificate that only the PrestaShop container trusts.
 * Every request is written to /work/requests.jsonl for the checks.
 */
$work = '/work';
$context = stream_context_create(['ssl' => [
    'local_cert' => "$work/cert.pem",
    'local_pk' => "$work/key.pem",
    'verify_peer' => false,
]]);
$server = stream_socket_server('tls://0.0.0.0:443', $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
if (!$server) {
    fwrite(STDERR, "listen failed: $errstr\n");
    exit(1);
}
$state = is_file("$work/state.json") ? json_decode(file_get_contents("$work/state.json"), true) : ['scenarios' => [], 'pages' => []];

function page($id, $public)
{
    return [
        'id' => $id,
        'kind' => 'space',
        'spaceId' => 'space-1',
        'name' => 'Boutique test',
        'slug' => 'boutique-test',
        'public' => $public,
        'url' => 'https://pathlyhq.com/status/boutique-test',
        'badgeUrl' => 'https://api.pathlyhq.com/public/status/boutique-test/badge.svg',
    ];
}

function route($method, $path, array $body, array &$state)
{
    if ($method === 'GET' && $path === '/v1/account') {
        return [200, [
            'organization' => ['id' => 'org-1', 'name' => 'Boutique test'],
            'space' => ['id' => 'space-1', 'name' => 'Boutique test'],
            'plan' => ['id' => 'pro', 'name' => 'Pro', 'priceMonthlyEur' => 69],
            'billingUrl' => 'https://pathlyhq.com/billing',
            'quotas' => [
                'ping' => ['used' => count($state['scenarios']), 'limit' => 50, 'remaining' => 49],
                'flow' => ['used' => 1, 'limit' => null, 'remaining' => null],
            ],
        ]];
    }
    if ($method === 'GET' && $path === '/v1/scenarios') {
        return [200, ['items' => array_values($state['scenarios']), 'nextCursor' => null]];
    }
    if ($method === 'POST' && $path === '/v1/scenarios') {
        $id = 'ping-' . (count($state['scenarios']) + 1);
        $state['scenarios'][$id] = ['id' => $id, 'type' => 'http', 'url' => $body['url'], 'name' => $body['name'], 'lastStatus' => null];

        return [201, $state['scenarios'][$id]];
    }
    if (preg_match('#^/v1/scenarios/([\w-]+)/run$#', $path, $m) && $method === 'POST') {
        return isset($state['scenarios'][$m[1]]) ? [200, ['ok' => true, 'queued' => true]] : [404, ['error' => 'Scénario introuvable']];
    }
    if (preg_match('#^/v1/scenarios/([\w-]+)$#', $path, $m) && $method === 'GET') {
        if (!isset($state['scenarios'][$m[1]])) {
            return [404, ['error' => 'Scénario introuvable']];
        }
        $scenario = $state['scenarios'][$m[1]];
        $scenario['lastStatus'] = $scenario['type'] === 'browser' ? 'fail' : 'ok';

        return [200, $scenario];
    }
    if ($method === 'GET' && $path === '/v1/runs') {
        return [200, ['items' => [
            ['id' => 'run-2', 'status' => 'ok', 'created_at' => '2026-11-02T10:05:00Z'],
            ['id' => 'run-1', 'status' => 'fail', 'created_at' => '2026-11-02T10:00:00Z'],
        ]]];
    }
    if ($method === 'GET' && $path === '/v1/runs/run-1') {
        return [200, [
            'id' => 'run-1',
            'status' => 'fail',
            'message' => 'Le bouton Commander est introuvable',
            'screenshotUrl' => 'https://storage.example/shot.jpg',
            'screenshotExpiresAt' => '2026-11-02T10:15:00Z',
        ]];
    }
    if ($method === 'POST' && $path === '/v1/flow-assistant/detect') {
        return [200, ['available' => true, 'reason' => null, 'siteKind' => 'prestashop', 'ai' => false, 'limit' => 15, 'remaining' => 15, 'unlock' => null]];
    }
    if ($method === 'POST' && $path === '/v1/flow-assistant/trials') {
        return empty($body['confirmOwner']) ? [400, ['code' => 'confirm_owner']] : [202, ['id' => 'trial-1']];
    }
    if ($method === 'GET' && $path === '/v1/flow-assistant/trials/trial-1') {
        return [200, ['item' => [
            'id' => 'trial-1',
            'status' => 'ready',
            'siteKind' => 'prestashop',
            'mode' => 'template',
            'proposals' => [
                ['index' => 0, 'kind' => 'cart', 'steps' => [['action' => 'goto'], ['action' => 'click']], 'stoppedBefore' => 'Commander'],
            ],
            'rejected' => 0,
            'reason' => null,
        ]]];
    }
    if ($method === 'POST' && $path === '/v1/flow-assistant/trials/trial-1/save') {
        $state['scenarios']['flow-1'] = ['id' => 'flow-1', 'type' => 'browser', 'url' => 'http://localhost/', 'name' => 'cart · localhost'];

        return [201, ['item' => ['id' => 'flow-1', 'name' => 'cart · localhost']]];
    }
    if ($method === 'GET' && $path === '/v1/status-pages') {
        return [200, ['items' => array_values($state['pages'])]];
    }
    if ($method === 'POST' && $path === '/v1/status-pages') {
        $state['pages']['page-1'] = page('page-1', !empty($body['public']));

        return [201, $state['pages']['page-1']];
    }
    if ($method === 'PATCH' && $path === '/v1/status-pages/page-1') {
        $state['pages']['page-1']['public'] = !empty($body['public']);

        return [200, $state['pages']['page-1']];
    }

    return [404, ['error' => "Route inconnue du simulateur : $method $path"]];
}

while (true) {
    $conn = @stream_socket_accept($server, -1);
    if (!$conn) {
        continue;
    }
    $head = '';
    while (strpos($head, "\r\n\r\n") === false && !feof($conn)) {
        $head .= fread($conn, 1);
    }
    $lines = explode("\r\n", trim($head));
    list($method, $target) = explode(' ', array_shift($lines));
    $headers = [];
    foreach ($lines as $line) {
        list($name, $value) = array_map('trim', explode(':', $line, 2));
        $headers[strtolower($name)] = $value;
    }
    $raw = '';
    $length = isset($headers['content-length']) ? (int) $headers['content-length'] : 0;
    while (strlen($raw) < $length && !feof($conn)) {
        $raw .= fread($conn, $length - strlen($raw));
    }
    $body = json_decode($raw, true);
    $path = parse_url($target, PHP_URL_PATH);
    $authorized = isset($headers['authorization']) && $headers['authorization'] === 'Bearer sp_integration_test_key';
    list($status, $payload) = $authorized ? route($method, $path, is_array($body) ? $body : [], $state) : [401, ['error' => 'Clé inconnue']];
    file_put_contents("$work/state.json", json_encode($state));
    file_put_contents("$work/requests.jsonl", json_encode([
        'method' => $method,
        'target' => $target,
        'status' => $status,
        'userAgent' => isset($headers['user-agent']) ? $headers['user-agent'] : null,
        'idempotencyKey' => isset($headers['idempotency-key']) ? $headers['idempotency-key'] : null,
        'body' => $body,
    ]) . "\n", FILE_APPEND);
    $json = json_encode($payload);
    fwrite($conn, "HTTP/1.1 $status X\r\nContent-Type: application/json\r\nContent-Length: " . strlen($json) . "\r\nConnection: close\r\n\r\n$json");
    fclose($conn);
}

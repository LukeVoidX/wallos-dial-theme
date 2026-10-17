<?php
// Docker --internal network only. Capture synthetic local notification POSTs.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if ($path === '/health') {
    header('Content-Type: text/plain');
    echo 'ok';
    return;
}

$body = file_get_contents('php://input');
$entry = [
    'path' => $path,
    'method' => $_SERVER['REQUEST_METHOD'] ?? '',
    'body' => $body === false ? '' : $body,
];
file_put_contents('/receipts/requests.jsonl', json_encode($entry, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
header('Content-Type: application/json');
echo $path === '/mattermost' ? '{"code":200}' : '{"success":true,"id":1}';

<?php
declare(strict_types=1);
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/dial_icon_repository.php';

header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header('Vary: Cookie');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET' ||
    ($_SESSION['loggedin'] ?? null) !== true || (int) $userId <= 0) {
    http_response_code(404);
    exit;
}
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
if ($id === false || $id === null) {
    http_response_code(404);
    exit;
}
session_write_close();
$asset = dialIconAsset($db, (int) $userId, (int) $id);
if (!$asset || !in_array($asset['mime'], ['image/png','image/jpeg','image/webp'], true)) {
    http_response_code(404);
    exit;
}
header('Content-Type: ' . $asset['mime']);
header('Content-Length: ' . strlen($asset['bytes']));
echo $asset['bytes'];

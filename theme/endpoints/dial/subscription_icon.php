<?php
declare(strict_types=1);
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/dial_icon_transaction.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header('Vary: Cookie');

function dialQuickIconResponse(int $status, string $code): never
{
    http_response_code($status);
    echo json_encode(['success'=>$status < 400, 'code'=>$code], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') dialQuickIconResponse(405, 'invalid_method');
if (($_SESSION['loggedin'] ?? null) !== true || (int) $userId <= 0) {
    dialQuickIconResponse(401, 'session_expired');
}
require_once '../../libs/csrf.php';
$csrf = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!is_string($csrf) || !verify_csrf_token($csrf)) dialQuickIconResponse(403, 'invalid_csrf');
require_once '../../includes/validate_endpoint.php';
if (getenv('DEMO_MODE') === '1') dialQuickIconResponse(403, 'demo_read_only');
session_write_close();

$subscriptionId = filter_var($_POST['subscription_id'] ?? null, FILTER_VALIDATE_INT,
    ['options'=>['min_range'=>1]]);
if ($subscriptionId === false || $subscriptionId === null) {
    dialQuickIconResponse(400, 'invalid_request');
}
if (!@$db->exec('BEGIN IMMEDIATE')) dialQuickIconResponse(500, 'icon_write_failed');
try {
    $preference = dialSubmittedIconPreference($db, (int) $userId, $_POST);
    if ($preference === null) throw new InvalidArgumentException('icon_source_invalid');
    dialIconSavePreference($db, (int) $userId, (int) $subscriptionId, $preference);
    if (!@$db->exec('COMMIT')) throw new RuntimeException('icon_write_failed');
    dialQuickIconResponse(200, 'icon_saved');
} catch (Throwable $error) {
    @$db->exec('ROLLBACK');
    if ($error instanceof InvalidArgumentException) {
        $code = $error->getMessage();
        dialQuickIconResponse($code === 'icon_subscription_not_owned' ? 404 : 400, $code);
    }
    error_log('Dial quick icon update failed: ' . $error->getMessage());
    dialQuickIconResponse(500, 'icon_write_failed');
}

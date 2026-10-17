<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';
require_once '../../includes/dial_icon_transaction.php';

header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);
$rawId = is_array($data) ? ($data['id'] ?? null) : null;
if (!(is_int($rawId) || (is_string($rawId) && ctype_digit($rawId))) || (int) $rawId <= 0) {
    echo json_encode(['success'=>false, 'message'=>translate('error', $i18n)]);
    exit();
}
if (getenv('DEMO_MODE') === '1') {
    http_response_code(403);
    echo json_encode(['success'=>false, 'message'=>'The public demo is read-only.']);
    exit();
}
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

try {
    $newId = dialCloneSubscriptionWithIcon($db, (int) $userId, (int) $rawId);
    echo json_encode([
        'success'=>true,
        'message'=>translate('success', $i18n),
        'id'=>$newId,
    ]);
} catch (Throwable $error) {
    error_log('Dial subscription clone failed: ' . $error->getMessage());
    echo json_encode(['success'=>false, 'message'=>translate('error', $i18n)]);
}
$db->close();

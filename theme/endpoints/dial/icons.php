<?php
declare(strict_types=1);
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/dial_icon_repository.php';
require_once '../../includes/dial_icon_normalize.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header('Vary: Cookie');

function dialIconResponse(int $status, string $code, array $extra = []): never
{
    http_response_code($status);
    echo json_encode(array_merge(['success'=>$status < 400, 'code'=>$code], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET','POST'], true)) {
    dialIconResponse(405, 'invalid_method');
}
if (($_SESSION['loggedin'] ?? null) !== true || (int) $userId <= 0) {
    dialIconResponse(401, 'session_expired');
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    session_write_close();
    $assets = array_map(static fn(array $row): array => [
        'id'=>$row['id'], 'label'=>$row['label'], 'width'=>$row['width'],
        'height'=>$row['height'], 'created_at'=>$row['created_at'],
    ], dialIconListAssets($db, (int) $userId));
    dialIconResponse(200, 'ok', ['assets'=>$assets]);
}

require_once '../../libs/csrf.php';
$csrf = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!is_string($csrf) || !verify_csrf_token($csrf)) {
    dialIconResponse(403, 'invalid_csrf');
}
// Validate the same Wallos login and CSRF contract as other POST endpoints.
require_once '../../includes/validate_endpoint.php';
if (getenv('DEMO_MODE') === '1') {
    dialIconResponse(403, 'demo_read_only');
}
session_write_close();

try {
    $action = $_POST['action'] ?? '';
    if ($action === 'upload') {
        $file = $_FILES['icon'] ?? null;
        if (!is_array($file) || !is_string($file['tmp_name'] ?? null) ||
            !is_uploaded_file($file['tmp_name'])) {
            dialIconResponse(400, 'icon_image_invalid');
        }
        $normalized = dialNormalizeIconUpload($file);
        $label = $_POST['label'] ?? '';
        if (!is_string($label)) dialIconResponse(400, 'icon_label_invalid');
        $id = dialIconSaveAsset($db, (int) $userId, $label, $normalized);
        dialIconResponse(200, 'icon_saved', ['id'=>$id]);
    }
    if ($action === 'delete') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if ($id === false || $id === null) dialIconResponse(400, 'invalid_request');
        if (!dialIconDeleteAsset($db, (int) $userId, (int) $id)) {
            dialIconResponse(409, 'icon_in_use_or_missing');
        }
        dialIconResponse(200, 'icon_deleted');
    }
    dialIconResponse(400, 'invalid_action');
} catch (DialIconQuotaError $error) {
    dialIconResponse(409, $error->getMessage(), ['count'=>$error->count, 'bytes'=>$error->bytes]);
} catch (InvalidArgumentException $error) {
    dialIconResponse(400, $error->getMessage());
} catch (Throwable $error) {
    error_log('Dial icon library error: ' . $error->getMessage());
    dialIconResponse(500, 'icon_write_failed');
}

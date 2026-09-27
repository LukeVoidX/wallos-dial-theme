<?php
header('Content-Type: application/json; charset=utf-8');

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';

$language = $_POST['language'] ?? null;
if (!is_string($language) || !isset($languages[$language])) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Unsupported language']);
    exit;
}

// Public demos share one synthetic account. Keep each visitor's language in
// their own cookie without changing the shared account preference.
if (!getenv('DEMO_MODE')) {
    $stmt = $db->prepare('UPDATE user SET language = :language WHERE id = :user_id');
    $stmt->bindValue(':language', $language, SQLITE3_TEXT);
    $stmt->bindValue(':user_id', (int) $userId, SQLITE3_INTEGER);
    if ($stmt->execute() === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Could not save language']);
        exit;
    }
}

$root = str_replace('/endpoints/user', '', dirname($_SERVER['PHP_SELF']));
$root = $root === '' ? '/' : $root;
setcookie('language', $language, [
    'path' => $root,
    'expires' => time() + (30 * 24 * 60 * 60),
    'samesite' => 'Lax',
]);

echo json_encode(['success' => true, 'language' => $language]);

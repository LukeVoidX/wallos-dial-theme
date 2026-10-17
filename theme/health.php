<?php

header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');

// Do not open a missing file: SQLite would create a blank database while the
// startup process is still installing the schema. Docker and reverse proxies
// should see a healthy service only after the Dial migration is recorded.
$databaseFile = __DIR__ . '/db/wallos.db';
if (!is_file($databaseFile)) {
    http_response_code(503);
    echo 'NOT READY';
    exit;
}

try {
    $db = @new SQLite3($databaseFile, SQLITE3_OPEN_READONLY);
    $db->busyTimeout(1000);
    $complete = @$db->querySingle(
        "SELECT COUNT(*) FROM migrations WHERE migration = 'migrations/dial_001_icon_library.php'"
    );
    $db->close();
} catch (Throwable $error) {
    $complete = false;
}

if ((int) $complete !== 1) {
    http_response_code(503);
    echo 'NOT READY';
    exit;
}

http_response_code(200);
echo 'OK';

<?php
if ($argc !== 2) {
    fwrite(STDERR, "Usage: seed-security.php wallos.db\n");
    exit(2);
}
$db = new SQLite3($argv[1]);
$db->enableExceptions(true);
if ((int) $db->querySingle('SELECT COUNT(*) FROM user') !== 0) {
    throw new RuntimeException('The fixture DB already has users');
}

function insertFixture(SQLite3 $db, string $table, array $data): int
{
    $columns = array_keys($data);
    $quoted = array_map(fn(string $column): string => '"' . str_replace('"', '""', $column) . '"', $columns);
    $sql = 'INSERT INTO "' . $table . '" (' . implode(',', $quoted) . ') VALUES (' .
        implode(',', array_fill(0, count($columns), '?')) . ')';
    $statement = $db->prepare($sql);
    foreach (array_values($data) as $index => $value) {
        $statement->bindValue($index + 1, $value, is_int($value) ? SQLITE3_INTEGER : SQLITE3_TEXT);
    }
    if (!$statement->execute()) {
        throw new RuntimeException('Could not insert fixture row in ' . $table);
    }
    return (int) $db->lastInsertRowID();
}

$db->exec('BEGIN IMMEDIATE');
try {
    $password = password_hash('DialFixture-2026!', PASSWORD_DEFAULT);
    $alpha = insertFixture($db, 'user', [
        'username' => 'alpha', 'email' => 'alpha@example.invalid', 'password' => $password,
        'main_currency' => 2, 'language' => 'en',
    ]);
    $beta = insertFixture($db, 'user', [
        'username' => 'beta', 'email' => 'beta@example.invalid', 'password' => $password,
        'main_currency' => 2, 'language' => 'en',
    ]);
    $alphaCurrency = 2;
    $betaCurrency = insertFixture($db, 'currencies', [
        'name' => 'Beta dollar', 'symbol' => '$', 'code' => 'USD', 'rate' => '1', 'user_id' => $beta,
    ]);
    $db->exec('UPDATE user SET main_currency = ' . $betaCurrency . ' WHERE id = ' . $beta);
    $alphaCategory = 2;
    $betaCategory = insertFixture($db, 'categories', [
        'name' => 'Beta category', 'order' => 2, 'user_id' => $beta,
    ]);
    $alphaPayment = 1;
    $betaPayment = insertFixture($db, 'payment_methods', [
        'name' => 'Beta card', 'icon' => 'creditcard.png', 'user_id' => $beta,
    ]);
    $alphaPayer = insertFixture($db, 'household', ['name' => 'Alpha payer', 'user_id' => $alpha]);
    $betaPayer = insertFixture($db, 'household', ['name' => 'Beta payer', 'user_id' => $beta]);
    insertFixture($db, 'settings', ['user_id' => $alpha]);
    insertFixture($db, 'settings', ['user_id' => $beta]);
    $db->exec('UPDATE admin SET login_disabled = 0, registrations_open = 0 WHERE id = 1');
    if ($db->querySingle('PRAGMA integrity_check') !== 'ok') {
        throw new RuntimeException('Fixture integrity check failed');
    }
    $db->exec('COMMIT');
} catch (Throwable $error) {
    $db->exec('ROLLBACK');
    throw $error;
}
echo json_encode([
    'alpha' => $alpha, 'beta' => $beta,
    'alphaCurrency' => $alphaCurrency, 'betaCurrency' => $betaCurrency,
    'alphaCategory' => $alphaCategory, 'betaCategory' => $betaCategory,
    'alphaPayment' => $alphaPayment, 'betaPayment' => $betaPayment,
    'alphaPayer' => $alphaPayer, 'betaPayer' => $betaPayer,
], JSON_THROW_ON_ERROR), "\n";

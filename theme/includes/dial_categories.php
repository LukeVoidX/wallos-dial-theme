<?php
require_once __DIR__ . '/inputvalidation.php';

class DialCategoryError extends RuntimeException
{
}

function dial_category_name_for_input(string $stored): string
{
    return html_entity_decode($stored, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function dial_category_html(string $stored): string
{
    return htmlspecialchars(dial_category_name_for_input($stored), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function dial_category_validate_name(string $raw): string
{
    $name = trim($raw);
    if (!mb_check_encoding($name, 'UTF-8') ||
        mb_strlen($name, 'UTF-8') < 1 || mb_strlen($name, 'UTF-8') > 60 ||
        preg_match('/\p{C}/u', $name)) {
        throw new DialCategoryError('invalid_name');
    }
    return validate($name);
}

/** @return SQLite3Result */
function dial_category_query(SQLite3 $db, string $sql, array $parameters = []): SQLite3Result
{
    $statement = $db->prepare($sql);
    if (!$statement) {
        throw new DialCategoryError('write_failed');
    }
    foreach ($parameters as $key => $value) {
        $type = is_int($value) ? SQLITE3_INTEGER : SQLITE3_TEXT;
        if (!$statement->bindValue($key, $value, $type)) {
            throw new DialCategoryError('write_failed');
        }
    }
    $result = $statement->execute();
    if (!$result) {
        throw new DialCategoryError('write_failed');
    }
    return $result;
}

function dial_category_scalar(SQLite3 $db, string $sql, array $parameters = [])
{
    $row = dial_category_query($db, $sql, $parameters)->fetchArray(SQLITE3_NUM);
    return $row === false ? null : $row[0];
}

function dial_category_transaction(SQLite3 $db, callable $operation)
{
    if (!$db->exec('BEGIN IMMEDIATE')) {
        throw new DialCategoryError('write_failed');
    }
    try {
        $result = $operation();
        if (!$db->exec('COMMIT')) {
            throw new DialCategoryError('write_failed');
        }
        return $result;
    } catch (Throwable $error) {
        $db->exec('ROLLBACK');
        if ($error instanceof DialCategoryError) {
            throw $error;
        }
        throw new DialCategoryError('write_failed', 0, $error);
    }
}

function dial_category_rows(SQLite3 $db, int $userId): array
{
    $result = dial_category_query($db, '
        SELECT c.id, c.name, c."order" AS display_order,
               COUNT(s.id) AS subscription_count
        FROM categories c
        LEFT JOIN subscriptions s ON s.category_id=c.id AND s.user_id=:subscriptionUserId
        WHERE c.user_id=:categoryUserId
        GROUP BY c.id
        ORDER BY c."order", c.id', [
        ':subscriptionUserId' => $userId,
        ':categoryUserId' => $userId,
    ]);
    $rows = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $row['id'] = (int) $row['id'];
        $row['display_order'] = (int) $row['display_order'];
        $row['subscription_count'] = (int) $row['subscription_count'];
        $rows[] = $row;
    }
    return $rows;
}

function dial_category_add(SQLite3 $db, int $userId, ?string $rawName): int
{
    $name = dial_category_validate_name($rawName === null ? 'Category' : $rawName);
    return dial_category_transaction($db, function () use ($db, $userId, $name): int {
        $order = (int) dial_category_scalar($db,
            'SELECT COALESCE(MAX("order"),0)+1 FROM categories WHERE user_id=:userId',
            [':userId' => $userId]);
        dial_category_query($db,
            'INSERT INTO categories (name,"order",user_id) VALUES (:name,:displayOrder,:userId)',
            [':name' => $name, ':displayOrder' => $order, ':userId' => $userId]);
        return (int) $db->lastInsertRowID();
    });
}

function dial_category_rename(SQLite3 $db, int $userId, int $id, string $rawName): void
{
    $name = dial_category_validate_name($rawName);
    dial_category_transaction($db, function () use ($db, $userId, $id, $name): void {
        $owned = dial_category_scalar($db,
            'SELECT id FROM categories WHERE id=:id AND user_id=:userId',
            [':id' => $id, ':userId' => $userId]);
        if ($owned === null) {
            throw new DialCategoryError('foreign_category');
        }
        dial_category_query($db,
            'UPDATE categories SET name=:name WHERE id=:id AND user_id=:userId',
            [':name' => $name, ':id' => $id, ':userId' => $userId]);
        if ($db->changes() !== 1) {
            throw new DialCategoryError('write_failed');
        }
    });
}

function dial_category_delete_empty(SQLite3 $db, int $userId, int $id): void
{
    dial_category_transaction($db, function () use ($db, $userId, $id): void {
        $owned = dial_category_scalar($db,
            'SELECT id FROM categories WHERE id=:id AND user_id=:userId',
            [':id' => $id, ':userId' => $userId]);
        if ($owned === null) {
            throw new DialCategoryError('foreign_category');
        }
        if ($id === 1) {
            throw new DialCategoryError('immutable_category');
        }
        $used = (int) dial_category_scalar($db,
            'SELECT COUNT(*) FROM subscriptions WHERE category_id=:id AND user_id=:userId',
            [':id' => $id, ':userId' => $userId]);
        if ($used > 0) {
            throw new DialCategoryError('category_in_use');
        }
        $count = (int) dial_category_scalar($db,
            'SELECT COUNT(*) FROM categories WHERE user_id=:userId',
            [':userId' => $userId]);
        if ($count <= 1) {
            throw new DialCategoryError('last_category');
        }
        dial_category_query($db,
            'DELETE FROM categories WHERE id=:id AND user_id=:userId',
            [':id' => $id, ':userId' => $userId]);
        if ($db->changes() !== 1) {
            throw new DialCategoryError('write_failed');
        }
    });
}

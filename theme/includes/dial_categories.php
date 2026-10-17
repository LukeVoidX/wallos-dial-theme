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

/** Compare human-visible names, not Wallos's one-layer escaped storage. */
function dial_category_name_key(string $stored): string
{
    return mb_strtolower(trim(dial_category_name_for_input($stored)), 'UTF-8');
}

function dial_category_name_taken(SQLite3 $db, int $userId, string $storedName, ?int $exceptId = null): bool
{
    $key = dial_category_name_key($storedName);
    $result = dial_category_query($db,
        'SELECT id,name FROM categories WHERE user_id=:userId', [':userId' => $userId]);
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        if ((int) $row['id'] !== $exceptId && dial_category_name_key($row['name']) === $key) {
            return true;
        }
    }
    return false;
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
    $result = @$statement->execute();
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
    // Wallos may write next-payment dates or settings at the same time as a
    // category edit. Wait for that writer and retry a bounded number of times.
    // Suppress SQLite warnings so an exhausted lock still returns JSON.
    $begun = false;
    for ($attempt = 0; $attempt < 3; $attempt++) {
        if (@$db->exec('BEGIN IMMEDIATE')) {
            $begun = true;
            break;
        }
        usleep(150000 * ($attempt + 1));
    }
    if (!$begun) {
        throw new DialCategoryError('write_failed');
    }
    try {
        $result = $operation();
        if (!@$db->exec('COMMIT')) {
            throw new DialCategoryError('write_failed');
        }
        return $result;
    } catch (Throwable $error) {
        @$db->exec('ROLLBACK');
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
        if (dial_category_name_taken($db, $userId, $name)) {
            throw new DialCategoryError('duplicate_name');
        }
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
        $owned = dial_category_query($db,
            'SELECT name FROM categories WHERE id=:id AND user_id=:userId',
            [':id' => $id, ':userId' => $userId]);
        $row = $owned->fetchArray(SQLITE3_ASSOC);
        if ($row === false) {
            throw new DialCategoryError('foreign_category');
        }
        // Existing duplicate rows remain readable and can be saved unchanged.
        if ($row['name'] === $name) return;
        if (dial_category_name_taken($db, $userId, $name, $id)) {
            throw new DialCategoryError('duplicate_name');
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

function dial_category_renumber_locked(SQLite3 $db, int $userId): void
{
    $rows = dial_category_rows($db, $userId);
    usort($rows, static function (array $a, array $b): int {
        if ($a['id'] === 1) return -1;
        if ($b['id'] === 1) return 1;
        return $a['display_order'] <=> $b['display_order'] ?: $a['id'] <=> $b['id'];
    });
    foreach ($rows as $index => $row) {
        dial_category_query($db,
            'UPDATE categories SET "order"=:displayOrder WHERE id=:id AND user_id=:userId',
            [':displayOrder' => $index + 1, ':id' => $row['id'], ':userId' => $userId]);
        if ($db->changes() !== 1) {
            throw new DialCategoryError('write_failed');
        }
    }
}

function dial_category_sort(SQLite3 $db, int $userId, array $movableIds): void
{
    dial_category_transaction($db, function () use ($db, $userId, $movableIds): void {
        $rows = dial_category_rows($db, $userId);
        $expected = array_values(array_filter(array_column($rows, 'id'), static fn(int $id): bool => $id !== 1));
        if (count($movableIds) !== count($expected) ||
            count(array_unique($movableIds, SORT_REGULAR)) !== count($movableIds)) {
            throw new DialCategoryError('stale_order');
        }
        foreach ($movableIds as $id) {
            if (!is_int($id)) {
                throw new DialCategoryError('stale_order');
            }
        }
        $submitted = $movableIds;
        sort($submitted, SORT_NUMERIC);
        sort($expected, SORT_NUMERIC);
        if ($submitted !== $expected) {
            throw new DialCategoryError('stale_order');
        }
        $order = 1;
        if (in_array(1, array_column($rows, 'id'), true)) {
            dial_category_query($db,
                'UPDATE categories SET "order"=1 WHERE id=1 AND user_id=:userId',
                [':userId' => $userId]);
            if ($db->changes() !== 1) throw new DialCategoryError('write_failed');
            $order = 2;
        }
        foreach ($movableIds as $id) {
            dial_category_query($db,
                'UPDATE categories SET "order"=:displayOrder WHERE id=:id AND user_id=:userId',
                [':displayOrder' => $order++, ':id' => $id, ':userId' => $userId]);
            if ($db->changes() !== 1) throw new DialCategoryError('write_failed');
        }
    });
}

function dial_category_merge(
    SQLite3 $db, int $userId, int $sourceId, int $targetId,
    ?string $expectedSourceName = null, ?string $expectedTargetName = null,
    ?int $expectedSourceCount = null
): int
{
    return dial_category_transaction($db, function () use (
        $db, $userId, $sourceId, $targetId, $expectedSourceName, $expectedTargetName,
        $expectedSourceCount
    ): int {
        if ($sourceId === $targetId) throw new DialCategoryError('same_category');
        $rows = dial_category_rows($db, $userId);
        $byId = array_column($rows, null, 'id');
        if (!isset($byId[$sourceId], $byId[$targetId])) {
            throw new DialCategoryError('foreign_category');
        }
        if ($sourceId === 1) throw new DialCategoryError('immutable_category');
        if (($expectedSourceName !== null && $byId[$sourceId]['name'] !== $expectedSourceName) ||
            ($expectedTargetName !== null && $byId[$targetId]['name'] !== $expectedTargetName) ||
            ($expectedSourceCount !== null &&
                (int) $byId[$sourceId]['subscription_count'] !== $expectedSourceCount)) {
            throw new DialCategoryError('stale_selection');
        }
        dial_category_query($db,
            'UPDATE subscriptions SET category_id=:target WHERE user_id=:userId AND category_id=:source',
            [':target' => $targetId, ':userId' => $userId, ':source' => $sourceId]);
        $moved = $db->changes();
        dial_category_query($db,
            'DELETE FROM categories WHERE id=:source AND user_id=:userId',
            [':source' => $sourceId, ':userId' => $userId]);
        if ($db->changes() !== 1) throw new DialCategoryError('write_failed');
        dial_category_renumber_locked($db, $userId);
        return $moved;
    });
}

/**
 * @param array $selectedRows Each row is ['id' => int, 'expectedName' => string].
 * @return array{addedIds: int[], deletedIds: int[]}
 */
function dial_category_apply_starter(SQLite3 $db, int $userId, array $selectedRows): array
{
    return dial_category_transaction($db, function () use ($db, $userId, $selectedRows): array {
        $subscriptionCount = (int) dial_category_scalar($db,
            'SELECT COUNT(*) FROM subscriptions WHERE user_id=:userId',
            [':userId' => $userId]);
        if ($subscriptionCount !== 0) throw new DialCategoryError('category_in_use');
        $rows = dial_category_rows($db, $userId);
        $byId = [];
        foreach ($rows as $row) $byId[$row['id']] = $row;
        $deletedIds = [];
        foreach ($selectedRows as $selection) {
            if (!is_array($selection) || !isset($selection['id'], $selection['expectedName']) ||
                !is_int($selection['id']) || !is_string($selection['expectedName']) ||
                !isset($byId[$selection['id']]) ||
                $byId[$selection['id']]['name'] !== $selection['expectedName'] ||
                in_array($selection['id'], $deletedIds, true)) {
                throw new DialCategoryError('stale_selection');
            }
            if ($selection['id'] === 1) throw new DialCategoryError('immutable_category');
            $deletedIds[] = $selection['id'];
        }
        $nameKeys = [];
        foreach ($rows as $row) {
            if (!in_array($row['id'], $deletedIds, true)) {
                $nameKeys[] = dial_category_name_key($row['name']);
            }
        }
        $addedIds = [];
        $order = count($rows) ? max(array_column($rows, 'display_order')) + 1 : 1;
        foreach (['AI', 'Infra', 'Domains', 'Media'] as $starterName) {
            $starterKey = dial_category_name_key($starterName);
            if (in_array($starterKey, $nameKeys, true)) continue;
            dial_category_query($db,
                'INSERT INTO categories (name,"order",user_id) VALUES (:name,:displayOrder,:userId)',
                [':name' => dial_category_validate_name($starterName),
                 ':displayOrder' => $order++, ':userId' => $userId]);
            $addedIds[] = (int) $db->lastInsertRowID();
            $nameKeys[] = $starterKey;
        }
        foreach ($deletedIds as $id) {
            dial_category_query($db,
                'DELETE FROM categories WHERE id=:id AND user_id=:userId',
                [':id' => $id, ':userId' => $userId]);
            if ($db->changes() !== 1) throw new DialCategoryError('write_failed');
        }
        dial_category_renumber_locked($db, $userId);
        return ['addedIds' => $addedIds, 'deletedIds' => $deletedIds];
    });
}

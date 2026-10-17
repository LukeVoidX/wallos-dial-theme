<?php
declare(strict_types=1);
require_once __DIR__ . '/dial_icon_catalog.php';

const DIAL_ICON_MAX_COUNT = 100;
const DIAL_ICON_MAX_BYTES = 33554432;

final class DialIconQuotaError extends RuntimeException
{
    public int $count;
    public int $bytes;
    public function __construct(string $reason, int $count, int $bytes)
    {
        parent::__construct($reason);
        $this->count = $count;
        $this->bytes = $bytes;
    }
}

function dialIconBind(SQLite3Stmt $statement, array $values): void
{
    foreach ($values as $key => $value) {
        $type = $value === null ? SQLITE3_NULL : (is_int($value) ? SQLITE3_INTEGER : SQLITE3_TEXT);
        if (!$statement->bindValue($key, $value, $type)) throw new RuntimeException('icon_bind_failed');
    }
}

function dialIconOne(SQLite3 $db, string $sql, array $values): ?array
{
    $statement = $db->prepare($sql);
    if (!$statement) throw new RuntimeException('icon_query_failed');
    dialIconBind($statement, $values);
    $result = $statement->execute();
    if (!$result) throw new RuntimeException('icon_query_failed');
    $row = $result->fetchArray(SQLITE3_ASSOC) ?: null;
    $result->finalize();
    return $row;
}

function dialIconValidatePreference(SQLite3 $db, int $userId, array $input): array
{
    $source = $input['source'] ?? null;
    if (!in_array($source, ['original', 'catalog', 'asset'], true)) {
        throw new InvalidArgumentException('icon_source_invalid');
    }
    $presentation = $input['presentation'] ?? 'plain';
    $treatment = $input['treatment'] ?? 'color';
    if (!in_array($presentation, ['plain', 'framed'], true)) {
        throw new InvalidArgumentException('icon_presentation_invalid');
    }
    if (!in_array($treatment, ['color', 'mono'], true)) {
        throw new InvalidArgumentException('icon_treatment_invalid');
    }
    $rawScale = $input['scale'] ?? 100;
    if (!(is_int($rawScale) || (is_string($rawScale) && ctype_digit($rawScale))) ||
        (int) $rawScale < 80 || (int) $rawScale > 120) {
        throw new InvalidArgumentException('icon_scale_invalid');
    }
    $catalogKey = $input['catalog_key'] ?? null;
    $catalogKey = $catalogKey === '' ? null : $catalogKey;
    $rawAssetId = $input['asset_id'] ?? null;
    $rawAssetId = $rawAssetId === '' ? null : $rawAssetId;
    if ($source === 'original') {
        if ($catalogKey !== null || $rawAssetId !== null) throw new InvalidArgumentException('icon_source_invalid');
    } elseif ($source === 'catalog') {
        if (!is_string($catalogKey) || dialIconCatalogItem($catalogKey) === null || $rawAssetId !== null) {
            throw new InvalidArgumentException('icon_catalog_invalid');
        }
    } else {
        if ($catalogKey !== null ||
            !(is_int($rawAssetId) || (is_string($rawAssetId) && ctype_digit($rawAssetId))) ||
            (int) $rawAssetId <= 0) {
            throw new InvalidArgumentException('icon_asset_not_owned');
        }
        $asset = dialIconOne($db,
            'SELECT id FROM dial_icon_assets WHERE id=:asset AND user_id=:user',
            [':asset'=>(int) $rawAssetId, ':user'=>$userId]);
        if (!$asset) throw new InvalidArgumentException('icon_asset_not_owned');
        $rawAssetId = (int) $rawAssetId;
    }
    return [
        'source'=>$source, 'catalog_key'=>$catalogKey, 'asset_id'=>$rawAssetId,
        'presentation'=>$presentation, 'treatment'=>$treatment, 'scale'=>(int) $rawScale,
    ];
}

function dialIconSavePreference(SQLite3 $db, int $userId, int $subscriptionId, array $normalized): void
{
    $owned = dialIconOne($db,
        'SELECT id FROM subscriptions WHERE id=:subscription AND user_id=:user',
        [':subscription'=>$subscriptionId, ':user'=>$userId]);
    if (!$owned) throw new InvalidArgumentException('icon_subscription_not_owned');
    $normalized = dialIconValidatePreference($db, $userId, $normalized);
    $statement = $db->prepare(<<<'SQL'
INSERT INTO dial_subscription_icons
    (user_id,subscription_id,source,catalog_key,asset_id,presentation,treatment,scale)
VALUES (:user,:subscription,:source,:catalog,:asset,:presentation,:treatment,:scale)
ON CONFLICT(user_id,subscription_id) DO UPDATE SET
    source=excluded.source,catalog_key=excluded.catalog_key,asset_id=excluded.asset_id,
    presentation=excluded.presentation,treatment=excluded.treatment,scale=excluded.scale,
    updated_at=CURRENT_TIMESTAMP
SQL);
    if (!$statement) throw new RuntimeException('icon_preference_write_failed');
    dialIconBind($statement, [
        ':user'=>$userId, ':subscription'=>$subscriptionId, ':source'=>$normalized['source'],
        ':catalog'=>$normalized['catalog_key'], ':asset'=>$normalized['asset_id'],
        ':presentation'=>$normalized['presentation'], ':treatment'=>$normalized['treatment'],
        ':scale'=>$normalized['scale'],
    ]);
    if (!$statement->execute()) throw new RuntimeException('icon_preference_write_failed');
}

function dialIconGetPreference(SQLite3 $db, int $userId, int $subscriptionId): ?array
{
    $row = dialIconOne($db, <<<'SQL'
SELECT p.source,p.catalog_key,p.asset_id,p.presentation,p.treatment,p.scale,p.updated_at
FROM dial_subscription_icons p
JOIN subscriptions s ON s.id=p.subscription_id AND s.user_id=p.user_id
WHERE p.user_id=:user AND p.subscription_id=:subscription
SQL, [':user'=>$userId, ':subscription'=>$subscriptionId]);
    if (!$row) return null;
    $row['asset_id'] = $row['asset_id'] === null ? null : (int) $row['asset_id'];
    $row['scale'] = (int) $row['scale'];
    return $row;
}

function dialIconListAssets(SQLite3 $db, int $userId): array
{
    $statement = $db->prepare('SELECT id,label,mime,width,height,created_at FROM dial_icon_assets WHERE user_id=:user ORDER BY id DESC');
    if (!$statement) throw new RuntimeException('icon_query_failed');
    dialIconBind($statement, [':user'=>$userId]);
    $result = $statement->execute();
    if (!$result) throw new RuntimeException('icon_query_failed');
    $rows = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $row['id'] = (int) $row['id'];
        $row['width'] = (int) $row['width'];
        $row['height'] = (int) $row['height'];
        $rows[] = $row;
    }
    $result->finalize();
    return $rows;
}

function dialIconAssetUsage(SQLite3 $db, int $userId): array
{
    $row = dialIconOne($db,
        'SELECT COUNT(*) AS count,COALESCE(SUM(length(bytes)),0) AS bytes FROM dial_icon_assets WHERE user_id=:user',
        [':user'=>$userId]);
    return [
        'count'=>(int) ($row['count'] ?? 0),
        'bytes'=>(int) ($row['bytes'] ?? 0),
        'limit_count'=>DIAL_ICON_MAX_COUNT,
        'limit_bytes'=>DIAL_ICON_MAX_BYTES,
    ];
}

function dialIconAsset(SQLite3 $db, int $userId, int $assetId): ?array
{
    return dialIconOne($db,
        'SELECT id,label,mime,bytes,sha256,width,height,created_at FROM dial_icon_assets WHERE id=:asset AND user_id=:user',
        [':asset'=>$assetId, ':user'=>$userId]);
}

function dialIconSaveAsset(SQLite3 $db, int $userId, string $label, array $normalized): int
{
    $label = trim($label);
    if ($label === '' || preg_match('//u', $label) !== 1 ||
        preg_match('/\p{C}/u', $label) || preg_match_all('/./us', $label) > 60) {
        throw new InvalidArgumentException('icon_label_invalid');
    }
    $mime = $normalized['mime'] ?? null;
    $bytes = $normalized['bytes'] ?? null;
    $sha = $normalized['sha256'] ?? null;
    $width = $normalized['width'] ?? null;
    $height = $normalized['height'] ?? null;
    if (!in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true) ||
        !is_string($bytes) || $bytes === '' || strlen($bytes) > 524288 ||
        !is_string($sha) || !hash_equals(hash('sha256', $bytes), $sha) ||
        !is_int($width) || $width < 1 || $width > 1024 ||
        !is_int($height) || $height < 1 || $height > 1024) {
        throw new InvalidArgumentException('icon_image_invalid');
    }
    if (!@$db->exec('BEGIN IMMEDIATE')) throw new RuntimeException('icon_asset_write_failed');
    try {
        $existing = dialIconOne($db,
            'SELECT id FROM dial_icon_assets WHERE user_id=:user AND sha256=:sha',
            [':user'=>$userId, ':sha'=>$sha]);
        if ($existing) {
            if (!@$db->exec('COMMIT')) throw new RuntimeException('icon_asset_write_failed');
            return (int) $existing['id'];
        }
        $usage = dialIconAssetUsage($db, $userId);
        $count = $usage['count'];
        $usedBytes = $usage['bytes'];
        if ($count >= $usage['limit_count']) throw new DialIconQuotaError('icon_quota_count', $count, $usedBytes);
        if ($usedBytes + strlen($bytes) > $usage['limit_bytes']) {
            throw new DialIconQuotaError('icon_quota_bytes', $count, $usedBytes);
        }
        $statement = $db->prepare(<<<'SQL'
INSERT INTO dial_icon_assets(user_id,label,mime,bytes,sha256,width,height)
VALUES(:user,:label,:mime,:bytes,:sha,:width,:height)
SQL);
        if (!$statement) throw new RuntimeException('icon_asset_write_failed');
        dialIconBind($statement, [':user'=>$userId, ':label'=>$label, ':mime'=>$mime,
            ':sha'=>$sha, ':width'=>$width, ':height'=>$height]);
        if (!$statement->bindValue(':bytes', $bytes, SQLITE3_BLOB) || !$statement->execute()) {
            throw new RuntimeException('icon_asset_write_failed');
        }
        $id = (int) $db->lastInsertRowID();
        if (!@$db->exec('COMMIT')) throw new RuntimeException('icon_asset_write_failed');
        return $id;
    } catch (Throwable $error) {
        @$db->exec('ROLLBACK');
        throw $error;
    }
}

function dialIconDeleteAsset(SQLite3 $db, int $userId, int $assetId): bool
{
    if (!@$db->exec('BEGIN IMMEDIATE')) throw new RuntimeException('icon_asset_write_failed');
    try {
        $owned = dialIconOne($db, 'SELECT id FROM dial_icon_assets WHERE id=:asset AND user_id=:user',
            [':asset'=>$assetId, ':user'=>$userId]);
        $used = $owned ? dialIconOne($db,
            'SELECT 1 AS used FROM dial_subscription_icons WHERE user_id=:user AND asset_id=:asset LIMIT 1',
            [':user'=>$userId, ':asset'=>$assetId]) : null;
        if (!$owned || $used) {
            if (!@$db->exec('COMMIT')) throw new RuntimeException('icon_asset_write_failed');
            return false;
        }
        $statement = $db->prepare('DELETE FROM dial_icon_assets WHERE id=:asset AND user_id=:user');
        if (!$statement) throw new RuntimeException('icon_asset_write_failed');
        dialIconBind($statement, [':asset'=>$assetId, ':user'=>$userId]);
        if (!$statement->execute() || $db->changes() !== 1) throw new RuntimeException('icon_asset_write_failed');
        if (!@$db->exec('COMMIT')) throw new RuntimeException('icon_asset_write_failed');
        return true;
    } catch (Throwable $error) {
        @$db->exec('ROLLBACK');
        throw $error;
    }
}

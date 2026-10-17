<?php
/** Dial-owned state. Keep this migration safe to rerun after an interrupted startup. */
$statements = [
    <<<'SQL'
CREATE TABLE IF NOT EXISTS dial_icon_assets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    label TEXT NOT NULL,
    mime TEXT NOT NULL CHECK (mime IN ('image/png','image/jpeg','image/webp')),
    bytes BLOB NOT NULL,
    sha256 TEXT NOT NULL,
    width INTEGER NOT NULL CHECK (width BETWEEN 1 AND 1024),
    height INTEGER NOT NULL CHECK (height BETWEEN 1 AND 1024),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, sha256)
)
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS dial_subscription_icons (
    user_id INTEGER NOT NULL,
    subscription_id INTEGER NOT NULL,
    source TEXT NOT NULL CHECK (source IN ('original','catalog','asset')),
    catalog_key TEXT,
    asset_id INTEGER,
    presentation TEXT NOT NULL CHECK (presentation IN ('plain','framed')),
    treatment TEXT NOT NULL CHECK (treatment IN ('color','mono')),
    scale INTEGER NOT NULL CHECK (scale BETWEEN 80 AND 120),
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, subscription_id),
    CHECK (
      (source = 'original' AND catalog_key IS NULL AND asset_id IS NULL) OR
      (source = 'catalog' AND catalog_key IS NOT NULL AND catalog_key <> '' AND asset_id IS NULL) OR
      (source = 'asset' AND catalog_key IS NULL AND asset_id IS NOT NULL AND asset_id > 0)
    )
)
SQL,
    'CREATE INDEX IF NOT EXISTS dial_subscription_icons_asset ON dial_subscription_icons (user_id, asset_id)',
    <<<'SQL'
CREATE TRIGGER IF NOT EXISTS dial_icon_subscription_deleted
AFTER DELETE ON subscriptions BEGIN
    DELETE FROM dial_subscription_icons
    WHERE user_id = OLD.user_id AND subscription_id = OLD.id;
END
SQL,
    <<<'SQL'
CREATE TRIGGER IF NOT EXISTS dial_icon_user_deleted
AFTER DELETE ON user BEGIN
    DELETE FROM dial_subscription_icons WHERE user_id = OLD.id;
    DELETE FROM dial_icon_assets WHERE user_id = OLD.id;
END
SQL,
];

if (!$db->exec('BEGIN IMMEDIATE')) {
    throw new RuntimeException('Dial icon migration could not start: ' . $db->lastErrorMsg());
}
try {
    foreach ($statements as $statement) {
        if (!$db->exec($statement)) {
            throw new RuntimeException('Dial icon migration failed: ' . $db->lastErrorMsg());
        }
    }
    if (!$db->exec('COMMIT')) {
        throw new RuntimeException('Dial icon migration could not commit: ' . $db->lastErrorMsg());
    }
} catch (Throwable $error) {
    $db->exec('ROLLBACK');
    throw $error;
}

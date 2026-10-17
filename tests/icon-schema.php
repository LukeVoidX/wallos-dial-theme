<?php
declare(strict_types=1);

function must(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
function database(): SQLite3 {
    $db = new SQLite3(':memory:');
    $db->enableExceptions(true);
    $db->exec('CREATE TABLE user(id INTEGER PRIMARY KEY)');
    $db->exec('CREATE TABLE subscriptions(id INTEGER PRIMARY KEY,user_id INTEGER NOT NULL)');
    require __DIR__ . '/../theme/migrations/dial_001_icon_library.php';
    require __DIR__ . '/../theme/migrations/dial_001_icon_library.php';
    return $db;
}

$db = database();
$db->exec('INSERT INTO user(id) VALUES(1),(2)');
$db->exec('INSERT INTO subscriptions(id,user_id) VALUES(11,1),(22,2)');
$db->exec("INSERT INTO dial_icon_assets(id,user_id,label,mime,bytes,sha256,width,height)
    VALUES(111,1,'Owner','image/png',x'89504e47','a',1,1),(222,2,'Other','image/png',x'89504e47','b',1,1)");
$db->exec("INSERT INTO dial_subscription_icons(user_id,subscription_id,source,presentation,treatment,scale)
    VALUES(1,11,'original','plain','color',100),(2,22,'original','plain','color',100)");
$db->exec('DELETE FROM subscriptions WHERE id=11');
must((int) $db->querySingle('SELECT COUNT(*) FROM dial_subscription_icons WHERE subscription_id=11') === 0,
    'old-image subscription deletion leaves preference');
must((int) $db->querySingle('SELECT COUNT(*) FROM dial_subscription_icons WHERE subscription_id=22') === 1,
    'other account preference changed');
$db->exec('DELETE FROM user WHERE id=1');
must((int) $db->querySingle('SELECT COUNT(*) FROM dial_icon_assets WHERE user_id=1') === 0,
    'old-image account deletion leaves asset');
must((int) $db->querySingle('SELECT COUNT(*) FROM dial_icon_assets WHERE user_id=2') === 1,
    'other account asset changed');
$db = database();
$db->exec('INSERT INTO user(id) VALUES(1),(2)');
$db->exec('INSERT INTO subscriptions(id,user_id) VALUES(11,1),(22,2)');
$db->exec("INSERT INTO dial_icon_assets(id,user_id,label,mime,bytes,sha256,width,height)
    VALUES(111,1,'Owner','image/png',x'89504e47','a',1,1),(222,2,'Other','image/png',x'89504e47','b',1,1)");
$db->exec("INSERT INTO dial_subscription_icons(user_id,subscription_id,source,presentation,treatment,scale)
    VALUES(1,11,'original','plain','color',100),(2,22,'original','plain','color',100)");
$db->exec('DELETE FROM user WHERE id=1');
must((int) $db->querySingle('SELECT COUNT(*) FROM dial_icon_assets WHERE user_id=1') === 0 &&
     (int) $db->querySingle('SELECT COUNT(*) FROM dial_subscription_icons WHERE user_id=1') === 0,
    'old-image account deletion left preference or asset when subscription row remains');
must((int) $db->querySingle('SELECT COUNT(*) FROM dial_subscription_icons WHERE user_id=2') === 1,
    'account trigger removed another account preference');

require __DIR__ . '/../theme/includes/dial_icon_repository.php';
$db = database();
$db->exec('INSERT INTO user(id) VALUES(1),(2)');
$db->exec('INSERT INTO subscriptions(id,user_id) VALUES(11,1),(22,2)');
$normalized = ['mime'=>'image/png', 'bytes'=>"\x89PNG", 'sha256'=>hash('sha256', "\x89PNG"), 'width'=>1, 'height'=>1];
try {
    dialIconSaveAsset($db, 1, "\xC3\x28", $normalized);
    throw new RuntimeException('invalid UTF-8 label accepted');
} catch (InvalidArgumentException $error) {
    must($error->getMessage() === 'icon_label_invalid', 'wrong invalid label error');
}
$asset1 = dialIconSaveAsset($db, 1, 'Owner icon', $normalized);
$asset2 = dialIconSaveAsset($db, 2, 'Other icon', $normalized);
must($asset1 !== $asset2, 'asset dedup crossed accounts');
must(dialIconSaveAsset($db, 1, 'Repeat', $normalized) === $asset1, 'same account did not dedup');
must(count(dialIconListAssets($db, 1)) === 1 && count(dialIconListAssets($db, 2)) === 1,
    'library list not account-scoped');
must(dialIconAsset($db, 1, $asset1)['bytes'] === $normalized['bytes'], 'owned asset not retrievable');
must(dialIconAsset($db, 1, $asset2) === null, 'foreign asset bytes disclosed');
try {
    dialIconValidatePreference($db, 1, ['source'=>'asset', 'asset_id'=>$asset2,
        'presentation'=>'plain', 'treatment'=>'color', 'scale'=>100]);
    throw new RuntimeException('foreign asset accepted');
} catch (InvalidArgumentException $error) {
    must($error->getMessage() === 'icon_asset_not_owned', 'wrong foreign asset error');
}
$catalog = dialIconValidatePreference($db, 1, ['source'=>'catalog', 'catalog_key'=>'chatgpt',
    'presentation'=>'framed', 'treatment'=>'mono', 'scale'=>'110']);
must($catalog['scale'] === 110, 'scale not canonical integer');
try {
    dialIconValidatePreference($db, 1, ['source'=>'catalog', 'catalog_key'=>'../outside', 'scale'=>100]);
    throw new RuntimeException('invalid catalog key accepted');
} catch (InvalidArgumentException $error) {
    must($error->getMessage() === 'icon_catalog_invalid', 'wrong unknown catalog error');
}
foreach ([79, 121, '99.5'] as $scale) {
    try {
        dialIconValidatePreference($db, 1, ['source'=>'original', 'scale'=>$scale]);
        throw new RuntimeException('invalid scale accepted');
    } catch (InvalidArgumentException $error) {
        must($error->getMessage() === 'icon_scale_invalid', 'wrong scale error');
    }
}
dialIconSavePreference($db, 1, 11, $catalog);
must(dialIconGetPreference($db, 1, 11)['catalog_key'] === 'chatgpt', 'catalog preference not stored');
$assetPreference = dialIconValidatePreference($db, 1, ['source'=>'asset', 'asset_id'=>(string)$asset1]);
dialIconSavePreference($db, 1, 11, $assetPreference);
must(dialIconGetPreference($db, 1, 11)['asset_id'] === $asset1, 'asset preference did not upsert');
must(dialIconGetPreference($db, 2, 11) === null, 'foreign preference disclosed');
must(dialIconDeleteAsset($db, 1, $asset1) === false, 'selected asset deleted');
try {
    dialIconSavePreference($db, 1, 22, $assetPreference);
    throw new RuntimeException('foreign subscription accepted');
} catch (InvalidArgumentException $error) {
    must($error->getMessage() === 'icon_subscription_not_owned', 'wrong foreign subscription error');
}
dialIconSavePreference($db, 1, 11, dialIconValidatePreference($db, 1, ['source'=>'original']));
must(dialIconDeleteAsset($db, 1, $asset1), 'unused owned asset not deleted');
must(dialIconDeleteAsset($db, 1, $asset2) === false, 'foreign asset deleted');

$quotaDb = database();
$quotaDb->exec('INSERT INTO user(id) VALUES(1)');
$first = dialIconSaveAsset($quotaDb, 1, 'First', $normalized);
for ($i = 1; $i < 100; $i++) {
    $statement = $quotaDb->prepare("INSERT INTO dial_icon_assets(user_id,label,mime,bytes,sha256,width,height)
        VALUES(1,:label,'image/png',x'00',:hash,1,1)");
    $statement->bindValue(':label', "Fixture $i", SQLITE3_TEXT);
    $statement->bindValue(':hash', hash('sha256', "fixture-$i"), SQLITE3_TEXT);
    must($statement->execute() !== false, 'quota fixture insert failed');
}
must(dialIconSaveAsset($quotaDb, 1, 'Duplicate at quota', $normalized) === $first,
    'duplicate rejected at count quota');
try {
    $other = ['mime'=>'image/png','bytes'=>'DIFFERENT','sha256'=>hash('sha256','DIFFERENT'),'width'=>1,'height'=>1];
    dialIconSaveAsset($quotaDb, 1, 'New at quota', $other);
    throw new RuntimeException('count quota accepted new asset');
} catch (DialIconQuotaError $error) {
    must($error->getMessage() === 'icon_quota_count' && $error->count === 100,
        'wrong count quota details');
}
$bytesDb = database();
$bytesDb->exec('INSERT INTO user(id) VALUES(1)');
$bytesDb->exec("INSERT INTO dial_icon_assets(user_id,label,mime,bytes,sha256,width,height)
    VALUES(1,'Near cap','image/png',zeroblob(33554430),'near-cap',1,1)");
try {
    dialIconSaveAsset($bytesDb, 1, 'Over bytes', $normalized);
    throw new RuntimeException('byte quota accepted new asset');
} catch (DialIconQuotaError $error) {
    must($error->getMessage() === 'icon_quota_bytes' && $error->bytes === 33554430,
        'wrong byte quota details');
}
echo "Icon migration, account scope, preferences, and quota checks passed.\n";

<?php
declare(strict_types=1);

require __DIR__ . '/../theme/includes/dial_icon_transaction.php';

function same($actual, $expected, string $label): void
{
    if ($actual !== $expected) {
        throw new RuntimeException($label . ': ' . var_export($actual, true) . ' != ' . var_export($expected, true));
    }
}

function fails(callable $callback, string $label): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        return;
    }
    throw new RuntimeException($label . ' unexpectedly succeeded');
}

$db = new SQLite3(':memory:');
$db->enableExceptions(true);
$db->exec(<<<'SQL'
CREATE TABLE user(id INTEGER PRIMARY KEY);
CREATE TABLE currencies(id INTEGER PRIMARY KEY,user_id INTEGER);
CREATE TABLE categories(id INTEGER PRIMARY KEY,user_id INTEGER);
CREATE TABLE household(id INTEGER PRIMARY KEY,user_id INTEGER);
CREATE TABLE payment_methods(id INTEGER PRIMARY KEY,user_id INTEGER,icon TEXT);
CREATE TABLE subscriptions(
 id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL, name TEXT, logo TEXT, logo_variant TEXT,
 logo_text_color TEXT, price REAL, currency_id INTEGER, next_payment TEXT, auto_renew INTEGER,
 start_date TEXT, cycle INTEGER, frequency INTEGER, notes TEXT, payment_method_id INTEGER,
 payer_user_id INTEGER, category_id INTEGER, notify INTEGER, url TEXT, inactive INTEGER,
 notify_days_before INTEGER, cancellation_date TEXT, replacement_subscription_id INTEGER
);
INSERT INTO user VALUES(1),(2);
INSERT INTO currencies VALUES(1,1),(2,2);
INSERT INTO categories VALUES(1,1),(2,2);
INSERT INTO household VALUES(1,1),(2,2);
INSERT INTO payment_methods VALUES(1,1,NULL),(2,2,NULL),(3,0,NULL);
INSERT INTO subscriptions(id,user_id,name,logo,logo_variant,logo_text_color,price,currency_id,category_id,payer_user_id,payment_method_id)
VALUES(11,1,'Original','old.png','old-variant.png','dark',9,1,1,1,1),
      (22,2,'Other','other.png',NULL,NULL,10,2,2,2,2);
SQL);
require __DIR__ . '/../theme/migrations/dial_001_icon_library.php';

$catalog = dialIconValidatePreference($db, 1, [
    'source'=>'catalog', 'catalog_key'=>'chatgpt', 'asset_id'=>null,
    'presentation'=>'framed', 'treatment'=>'mono', 'scale'=>95,
]);
$original = dialIconValidatePreference($db, 1, ['source'=>'original']);
same(dialSubmittedIconPreference($db, 1, []), null, 'legacy form leaves preference');
same(dialSubmittedIconPreference($db, 1, ['dial_icon_source'=>'original'])['source'], 'original', 'explicit original form');
fails(fn() => dialSubmittedIconPreference($db, 1, ['dial_icon_asset_id'=>'1']), 'incomplete icon form');
$foreignAsset = $db->exec("INSERT INTO dial_icon_assets(user_id,label,mime,bytes,sha256,width,height) VALUES(2,'Foreign','image/png',x'89504e47','test-hash',1,1)");
same($foreignAsset, true, 'foreign fixture');
$foreignAssetId = (int) $db->lastInsertRowID();
fails(fn() => dialIconValidatePreference($db, 1, ['source'=>'asset','asset_id'=>$foreignAssetId]), 'foreign asset');

$dir = sys_get_temp_dir() . '/dial-atomic-' . bin2hex(random_bytes(8));
mkdir($dir, 0700);
$old = $dir . '/old.png';
$oldVariant = $dir . '/old-variant.png';
file_put_contents($old, 'previous-logo');
file_put_contents($oldVariant, 'previous-variant');

function stageFiles(string $dir): array
{
    $temporary = $dir . '/.dial-stage-' . bin2hex(random_bytes(8)) . '.png';
    $final = $dir . '/dial-' . bin2hex(random_bytes(8)) . '.png';
    file_put_contents($temporary, 'new-logo');
    return [['temporary'=>$temporary,'final'=>$final]];
}

$edit = static function(SQLite3 $db, array $files): int {
    $statement = $db->prepare('UPDATE subscriptions SET name=:name,logo=:logo WHERE id=11 AND user_id=1');
    $statement->bindValue(':name', 'Edited');
    $statement->bindValue(':logo', basename($files[0]['final']));
    if (!$statement->execute() || $db->changes() !== 1) throw new RuntimeException('write_failed');
    return 11;
};

foreach (['after_stage','after_move','before_commit'] as $point) {
    $files = stageFiles($dir);
    fails(static function() use ($db,$edit,$catalog,$files,$point): void {
        dialWriteSubscriptionWithIcon($db, 1, fn(SQLite3 $db): int => $edit($db,$files),
            $catalog, $files, static function(string $at) use ($point): void {
                if ($at === $point) throw new RuntimeException('injected_' . $point);
            });
    }, $point);
    same($db->querySingle('SELECT name FROM subscriptions WHERE id=11'), 'Original', $point . ' row');
    same($db->querySingle('SELECT COUNT(*) FROM dial_subscription_icons WHERE subscription_id=11'), 0, $point . ' preference');
    same(is_file($old), true, $point . ' prior file');
    same(is_file($files[0]['temporary']), false, $point . ' temporary cleanup');
    same(is_file($files[0]['final']), false, $point . ' final cleanup');
}

// A pre-existing Logo at the proposed final path must never be deleted when
// the no-overwrite link refuses a collision, including after a partial move.
$files = stageFiles($dir);
file_put_contents($files[0]['final'], 'someone-elses-logo');
fails(fn() => dialWriteSubscriptionWithIcon($db, 1,
    fn(SQLite3 $db): int => $edit($db, $files), $catalog, $files), 'first-file collision');
same(file_get_contents($files[0]['final']), 'someone-elses-logo', 'collision did not delete pre-existing logo');
same(is_file($files[0]['temporary']), false, 'collision temporary removed');
same($db->querySingle('SELECT name FROM subscriptions WHERE id=11'), 'Original', 'collision row rolled back');
unlink($files[0]['final']);
$first = stageFiles($dir)[0];
$second = stageFiles($dir)[0];
file_put_contents($second['final'], 'other-existing-logo');
fails(fn() => dialWriteSubscriptionWithIcon($db, 1,
    fn(SQLite3 $db): int => $edit($db, [$first]), $catalog, [$first,$second]), 'second-file collision');
same(is_file($first['final']), false, 'partial move newly created file removed');
same(file_get_contents($second['final']), 'other-existing-logo', 'partial move existing file retained');
same(is_file($first['temporary']), false, 'partial move first temp removed');
same(is_file($second['temporary']), false, 'partial move second temp removed');
unlink($second['final']);

$db->exec("CREATE TEMP TRIGGER reject_preference BEFORE INSERT ON dial_subscription_icons BEGIN SELECT RAISE(ABORT,'forced_preference_failure'); END");
$files = stageFiles($dir);
fails(fn() => dialWriteSubscriptionWithIcon($db, 1, fn(SQLite3 $db): int => $edit($db,$files), $catalog, $files), 'preference INSERT failure');
same($db->querySingle('SELECT name FROM subscriptions WHERE id=11'), 'Original', 'preference failure row');
same(is_file($files[0]['final']), false, 'preference failure final cleanup');
$db->exec('DROP TRIGGER reject_preference');

$files = stageFiles($dir);
$id = dialWriteSubscriptionWithIcon($db, 1, fn(SQLite3 $db): int => $edit($db,$files), $catalog, $files);
same($id, 11, 'edited id');
same(is_file($files[0]['final']), true, 'committed new file');
same(is_file($old), true, 'old file retained until postcommit cleanup');
same(dialIconGetPreference($db, 1, 11)['source'], 'catalog', 'catalog preference');
$newLogo = basename($files[0]['final']);

// Cleanup is a postcommit activity. Failure must not undo or corrupt the row.
$cleanup = dialCleanupPreviousLogoFiles($db, [$old], $dir, static function(): void {
    throw new RuntimeException('forced_unlink_failure');
});
same($cleanup['retry_count'], 1, 'cleanup retry scheduled');
same($cleanup['unscheduled_count'], 0, 'cleanup retry journal write');
same($db->querySingle('SELECT logo FROM subscriptions WHERE id=11'), $newLogo, 'committed row survived cleanup failure');
same(is_file($old), true, 'old file retained on cleanup failure');
$queue = $dir . '/.dial-logo-cleanup-retry.jsonl';
same(is_file($queue), true, 'retry journal exists');
same(fileperms($queue) & 0777, 0600, 'retry journal private');
dialRetryLogoCleanup($db, $dir);
same(is_file($old), false, 'retry removes unreferenced old logo');
same(trim((string) file_get_contents($queue)), '', 'retry journal drained');

// Explicit Original suppresses the legacy map and leaves the new Logo intact.
$id = dialWriteSubscriptionWithIcon($db, 1, static function(SQLite3 $db): int {
    if (!$db->exec("UPDATE subscriptions SET name='Original icon' WHERE id=11 AND user_id=1")) throw new RuntimeException('write_failed');
    return 11;
}, $original, []);
same($id, 11, 'original edit id');
same(dialIconGetPreference($db, 1, 11)['source'], 'original', 'explicit original');
same($db->querySingle('SELECT logo FROM subscriptions WHERE id=11'), $newLogo, 'original logo preserved');

// New subscription and clone both preserve the source Logo presentation.
$newId = dialWriteSubscriptionWithIcon($db, 1, static function(SQLite3 $db): int {
    if (!$db->exec("INSERT INTO subscriptions(user_id,name,price,currency_id,category_id,payer_user_id,payment_method_id) VALUES(1,'New',8,1,1,1,1)")) throw new RuntimeException('write_failed');
    return (int) $db->lastInsertRowID();
}, $catalog, []);
same(dialIconGetPreference($db, 1, $newId)['catalog_key'], 'chatgpt', 'new row preference');

$assetCountBefore = (int) $db->querySingle('SELECT COUNT(*) FROM dial_icon_assets');
$cloneId = dialCloneSubscriptionWithIcon($db, 1, 11);
same($db->querySingle('SELECT logo FROM subscriptions WHERE id=' . $cloneId), $newLogo, 'clone logo');
same($db->querySingle('SELECT logo_variant FROM subscriptions WHERE id=' . $cloneId), 'old-variant.png', 'clone variant');
same($db->querySingle('SELECT logo_text_color FROM subscriptions WHERE id=' . $cloneId), 'dark', 'clone text color');
same(dialIconGetPreference($db, 1, $cloneId)['source'], 'original', 'clone preference');
same((int) $db->querySingle('SELECT COUNT(*) FROM dial_icon_assets'), $assetCountBefore, 'clone BLOB count');

$before = (int) $db->querySingle('SELECT COUNT(*) FROM subscriptions');
$db->exec('UPDATE subscriptions SET category_id=2 WHERE id=11');
fails(fn() => dialCloneSubscriptionWithIcon($db, 1, 11), 'foreign category clone');
$db->exec('UPDATE subscriptions SET category_id=1,payment_method_id=2 WHERE id=11');
fails(fn() => dialCloneSubscriptionWithIcon($db, 1, 11), 'foreign payment clone');
$db->exec('UPDATE subscriptions SET payment_method_id=1,currency_id=NULL WHERE id=11');
fails(fn() => dialCloneSubscriptionWithIcon($db, 1, 11), 'missing currency clone');
$db->exec('UPDATE subscriptions SET currency_id=2 WHERE id=11');
fails(fn() => dialCloneSubscriptionWithIcon($db, 1, 11), 'foreign currency clone');
$db->exec('UPDATE subscriptions SET currency_id=1,payer_user_id=2 WHERE id=11');
fails(fn() => dialCloneSubscriptionWithIcon($db, 1, 11), 'foreign payer clone');
$db->exec('UPDATE subscriptions SET payer_user_id=1,replacement_subscription_id=22 WHERE id=11');
fails(fn() => dialCloneSubscriptionWithIcon($db, 1, 11), 'foreign replacement clone');
fails(fn() => dialCloneSubscriptionWithIcon($db, 1, 22), 'foreign source clone');
same((int) $db->querySingle('SELECT COUNT(*) FROM subscriptions'), $before, 'invalid clone row count');

// Two separate SQLite connections model edits opened from two tabs. The
// second form has stale L0 state; its cleanup must still target committed L1.
$raceDb = $dir . '/concurrent.sqlite';
$left = new SQLite3($raceDb);
$left->enableExceptions(true);
$left->exec("CREATE TABLE subscriptions(id INTEGER PRIMARY KEY,user_id INTEGER,logo TEXT,logo_variant TEXT);
             CREATE TABLE payment_methods(id INTEGER PRIMARY KEY,icon TEXT);
             INSERT INTO subscriptions VALUES(1,1,'race-0.png',NULL);");
$right = new SQLite3($raceDb);
$right->enableExceptions(true);
$left->busyTimeout(5000);
$right->busyTimeout(5000);
file_put_contents($dir . '/race-0.png', 'race-zero');
$staleFormLogo = $right->querySingle('SELECT logo FROM subscriptions WHERE id=1');
$raceFirst = stageFiles($dir);
$raceOld1 = null;
dialWriteSubscriptionWithIcon($left, 1, static function(SQLite3 $lockedDb) use (&$raceOld1,$raceFirst): int {
    $raceOld1 = dialCurrentLogoFiles($lockedDb, 1, 1)['logo'];
    $statement = $lockedDb->prepare('UPDATE subscriptions SET logo=:logo WHERE id=1 AND user_id=1');
    $statement->bindValue(':logo', basename($raceFirst[0]['final']));
    if (!$statement->execute()) throw new RuntimeException('race_first_write_failed');
    return 1;
}, null, $raceFirst);
dialCleanupPreviousLogoFiles($left, [$raceOld1], $dir);
$raceSecond = stageFiles($dir);
$raceOld2 = null;
dialWriteSubscriptionWithIcon($right, 1, static function(SQLite3 $lockedDb) use (&$raceOld2,$raceSecond): int {
    $raceOld2 = dialCurrentLogoFiles($lockedDb, 1, 1)['logo'];
    $statement = $lockedDb->prepare('UPDATE subscriptions SET logo=:logo WHERE id=1 AND user_id=1');
    $statement->bindValue(':logo', basename($raceSecond[0]['final']));
    if (!$statement->execute()) throw new RuntimeException('race_second_write_failed');
    return 1;
}, null, $raceSecond);
dialCleanupPreviousLogoFiles($right, [$raceOld2], $dir);
same($staleFormLogo, 'race-0.png', 'stale form fixture');
same($raceOld2, basename($raceFirst[0]['final']), 'second transaction read latest logo');
same(is_file($raceFirst[0]['final']), false, 'intermediate logo reclaimed');
same(is_file($raceSecond[0]['final']), true, 'latest logo retained');
$left->close();
$right->close();
unlink($raceDb);

foreach (glob($dir . '/*') as $file) unlink($file);
foreach (glob($dir . '/.*') as $file) {
    if (is_file($file)) unlink($file);
}
rmdir($dir);
echo "Atomic subscription/icon tests passed.\n";

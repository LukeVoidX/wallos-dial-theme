<?php
declare(strict_types=1);
putenv('WALLOS_DIAL_LOGO_MAP=' . __DIR__ . '/fixtures/legacy-map.php');
require __DIR__ . '/../theme/includes/dial_icon_resolver.php';

function must(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$db = new SQLite3(':memory:');
$db->enableExceptions(true);
$db->exec('CREATE TABLE user(id INTEGER PRIMARY KEY)');
$db->exec('CREATE TABLE subscriptions(id INTEGER PRIMARY KEY,user_id INTEGER NOT NULL)');
require __DIR__ . '/../theme/migrations/dial_001_icon_library.php';
$db->exec('INSERT INTO user(id) VALUES(1),(2)');
$db->exec('INSERT INTO subscriptions(id,user_id) VALUES(11,1),(22,2)');
$subscription = [
    'id'=>11, 'user_id'=>1, 'name'=>'Fixture service',
    'logo'=>'fixture-original.png', 'logo_variant'=>'fixture-variant.png', 'logo_text_color'=>'dark',
];

$legacy = dialResolveIcon($db, 1, $subscription);
must($legacy['kind'] === 'legacy' && $legacy['src'] === 'images/dial-logos/fixture.svg',
    'missing preference did not use legacy map');
must(dialResolveIcon($db, 2, $subscription)['kind'] === 'fallback',
    'foreign subscription disclosed logo');

$original = dialIconValidatePreference($db, 1, ['source'=>'original']);
dialIconSavePreference($db, 1, 11, $original);
$resolved = dialResolveIcon($db, 1, $subscription);
must($resolved['kind'] === 'original' && $resolved['src'] === 'images/uploads/logos/fixture-original.png',
    'explicit Original failed to bypass legacy map');
must($resolved['variant_src'] === 'images/uploads/logos/fixture-variant.png' &&
     $resolved['native_text_color'] === 'dark', 'original theme variant lost');

$catalog = dialIconValidatePreference($db, 1, ['source'=>'catalog','catalog_key'=>'chatgpt',
    'presentation'=>'framed','treatment'=>'mono','scale'=>110]);
dialIconSavePreference($db, 1, 11, $catalog);
$resolved = dialResolveIcon($db, 1, $subscription);
must($resolved['kind'] === 'catalog' && $resolved['src'] === 'images/dial-catalog/chatgpt.svg',
    'catalog icon resolution failed');
$html = dialRenderIcon($resolved, 'dial-card-icon');
must(str_contains($html, 'data-dial-icon-presentation="framed"') &&
     str_contains($html, 'data-dial-icon-treatment="mono"') &&
     str_contains($html, 'data-dial-icon-scale="110"'), 'presentation attributes missing');

$bytes = "\x89PNG";
$assetId = dialIconSaveAsset($db, 1, 'Fixture icon', [
    'mime'=>'image/png','bytes'=>$bytes,'sha256'=>hash('sha256',$bytes),'width'=>1,'height'=>1,
]);
dialIconSavePreference($db, 1, 11,
    dialIconValidatePreference($db, 1, ['source'=>'asset','asset_id'=>$assetId]));
$resolved = dialResolveIcon($db, 1, $subscription);
must($resolved['kind'] === 'asset' && $resolved['src'] === '/endpoints/dial/icon.php?id=' . $assetId,
    'owned library image resolution failed');

$db->exec("UPDATE dial_subscription_icons SET source='catalog',catalog_key='removed-key',asset_id=NULL WHERE user_id=1 AND subscription_id=11");
must(dialResolveIcon($db, 1, $subscription)['kind'] === 'original', 'missing catalog key did not fall back');
$db->exec("UPDATE dial_subscription_icons SET source='asset',catalog_key=NULL,asset_id=999 WHERE user_id=1 AND subscription_id=11");
must(dialResolveIcon($db, 1, $subscription)['kind'] === 'original', 'missing asset did not fall back');

$badHtml = dialRenderIcon(['kind'=>'original','src'=>'images/uploads/logos/a" onerror="bad.png',
    'variant_src'=>null,'native_text_color'=>null,'presentation'=>'plain','treatment'=>'color','scale'=>100],
    'dial-service-icon');
must(!str_contains($badHtml, ' onerror="') && str_contains($badHtml, '&quot; onerror=&quot;'),
    'untrusted icon URL escaped incorrectly');
$noLogo = dialResolveIcon($db, 1, ['id'=>11,'user_id'=>1,'name'=>'Fixture service','logo'=>'']);
must($noLogo['kind'] === 'fallback', 'missing original Logo did not produce a fallback');
echo "Icon resolver, legacy map, fallback, and HTML escaping checks passed.\n";

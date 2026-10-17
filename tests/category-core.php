<?php
require '/var/www/html/includes/dial_categories.php';
require '/var/www/html/includes/dial_category_i18n.php';

function must(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function rejects(string $code, callable $operation): void
{
    try {
        $operation();
    } catch (DialCategoryError $error) {
        must($error->getMessage() === $code, "Expected $code, got " . $error->getMessage());
        return;
    }
    throw new RuntimeException("Expected $code error");
}

function fixture(): SQLite3
{
    $db = new SQLite3(':memory:');
    $db->exec('CREATE TABLE categories (id INTEGER PRIMARY KEY, name TEXT NOT NULL, "order" INTEGER DEFAULT 0, user_id INTEGER DEFAULT 1)');
    $db->exec('CREATE TABLE subscriptions (id INTEGER PRIMARY KEY, name TEXT, category_id INTEGER, user_id INTEGER, inactive INTEGER DEFAULT 0, cycle INTEGER DEFAULT 3, price REAL DEFAULT 0)');
    $db->exec('INSERT INTO categories (id,name,"order",user_id) VALUES
        (1,"No category",1,1),(2,"AI &amp; Tools",2,1),(3,"Infra",3,1),(4,"Empty",4,1),
        (5,"No category",1,2),(6,"Private",2,2)');
    $db->exec('INSERT INTO subscriptions (id,name,category_id,user_id,inactive,cycle,price) VALUES
        (10,"Aster",2,1,0,3,19),(11,"Archive",2,1,1,5,49),(12,"Other",6,2,0,3,7)');
    return $db;
}

$db = fixture();
must(dial_category_name_for_input('AI &amp; Tools') === 'AI & Tools', 'decode one stored layer');
must(dial_category_html('AI &amp; Tools') === 'AI &amp; Tools', 'escape output once');
must(dial_category_name_for_input('AI &amp;amp; Tools') === 'AI &amp; Tools', 'do not change historical literal');
must(dial_category_validate_name('AI & Tools') === 'AI &amp; Tools', 'Wallos storage contract');
must(dial_category_validate_name(str_repeat('中', 60)) === str_repeat('中', 60), '60 Unicode codepoints');
rejects('invalid_name', fn() => dial_category_validate_name('  '));
rejects('invalid_name', fn() => dial_category_validate_name("Hidden\x01Control"));
rejects('invalid_name', fn() => dial_category_validate_name(str_repeat('中', 61)));
rejects('invalid_name', fn() => dial_category_validate_name("Bad\xC3\x28UTF8"));
must(count(dial_category_rows($db, 1)) === 4, 'account-scoped rows');
must(dial_category_rows($db, 1)[1]['subscription_count'] === 2, 'count includes disabled one-time item');
must(array_column(dial_category_rows($db, 2), 'id') === [5, 6], 'foreign rows excluded');

// Names that differ only by case or surrounding space must not create two
// indistinguishable choices for a single account. Other accounts stay separate.
rejects('duplicate_name', fn() => dial_category_add($db, 1, '  infra  '));
rejects('duplicate_name', fn() => dial_category_rename($db, 1, 4, 'INFRA'));
must((string) $db->querySingle('SELECT name FROM categories WHERE id=4') === 'Empty',
    'duplicate rename left the previous category intact');
$otherAccountId = dial_category_add($db, 2, 'Infra');
must((int) $db->querySingle("SELECT user_id FROM categories WHERE id=$otherAccountId") === 2,
    'same display name remains valid in a different account');
foreach (['en' => 'This category name is in use. Rename it or choose another name.',
          'zh_cn' => '这个分类名称已在使用，请先重命名或换一个名称。',
          'zh_tw' => '此分類名稱已在使用，請先重新命名或換一個名稱。'] as $language => $message) {
    must(dial_category_message('duplicate_name', $language) === $message,
        "missing $language duplicate-name guidance");
}

$newId = dial_category_add($db, 1, 'Books & Media');
must((string) $db->querySingle("SELECT name FROM categories WHERE id=$newId") === 'Books &amp; Media', 'named add escaped once');
$unnamedId = dial_category_add($db, 1, null);
must((string) $db->querySingle("SELECT name FROM categories WHERE id=$unnamedId") === 'Category', 'Settings unnamed add');
rejects('duplicate_name', fn() => dial_category_add($db, 1, null));
must((int) $db->querySingle("SELECT count(*) FROM categories WHERE user_id=1 AND name='Category'") === 1,
    'Settings cannot create an ambiguous second placeholder');
dial_category_rename($db, 1, 2, 'AI & Tools');
must((string) $db->querySingle('SELECT name FROM categories WHERE id=2') === 'AI &amp; Tools', 'repeat edit has no double encoding');
rejects('foreign_category', fn() => dial_category_rename($db, 1, 6, 'Stolen'));
must((string) $db->querySingle('SELECT name FROM categories WHERE id=6') === 'Private', 'foreign rename unchanged');
rejects('category_in_use', fn() => dial_category_delete_empty($db, 1, 2));
rejects('immutable_category', fn() => dial_category_delete_empty($db, 1, 1));
rejects('foreign_category', fn() => dial_category_delete_empty($db, 1, 6));
dial_category_delete_empty($db, 1, 4);
must((int) $db->querySingle('SELECT count(*) FROM categories WHERE id=4') === 0, 'empty category removed');
must((int) $db->querySingle('SELECT count(*) FROM categories WHERE id=6') === 1, 'other account untouched');

$db->exec('INSERT INTO categories (id,name,"order",user_id) VALUES (100,"Only",1,3)');
rejects('last_category', fn() => dial_category_delete_empty($db, 3, 100));
must((int) $db->querySingle('SELECT count(*) FROM categories WHERE user_id=3') === 1, 'last category kept');
must($db->querySingle('PRAGMA integrity_check') === 'ok', 'SQLite integrity');

$db = fixture();
$db->exec('INSERT INTO categories (id,name,"order",user_id) VALUES
    (100,"Legacy",5,1),(101,"legacy",6,1)');
must(count(dial_category_rows($db, 1)) === 6, 'historical duplicate rows stay readable');
dial_category_rename($db, 1, 100, 'Legacy');
must((string) $db->querySingle('SELECT name FROM categories WHERE id=100') === 'Legacy',
    'saving a historical duplicate unchanged remains possible');
rejects('duplicate_name', fn() => dial_category_rename($db, 1, 4, 'LEGACY'));

$db = fixture();
dial_category_sort($db, 1, [4, 3, 2]);
must(array_column(dial_category_rows($db, 1), 'id') === [1, 4, 3, 2], 'id 1 stays first');
$before = json_encode(dial_category_rows($db, 1));
rejects('stale_order', fn() => dial_category_sort($db, 1, [4, 4, 2]));
rejects('stale_order', fn() => dial_category_sort($db, 1, [4, 3, 6]));
must(json_encode(dial_category_rows($db, 1)) === $before, 'bad sort made no write');
rejects('foreign_category', fn() => dial_category_merge($db, 1, 2, 6));
rejects('immutable_category', fn() => dial_category_merge($db, 1, 1, 3));
rejects('same_category', fn() => dial_category_merge($db, 1, 2, 2));
rejects('foreign_category', fn() => dial_category_merge($db, 1, 999, 3));
must(json_encode(dial_category_rows($db, 1)) === $before, 'bad merge made no write');
rejects('stale_selection', fn() => dial_category_merge($db, 1, 2, 3, 'AI &amp; Tools', 'Old Infra'));
rejects('stale_selection', fn() => dial_category_merge($db, 1, 2, 3, 'AI &amp; Tools', 'Infra', 1));
must(json_encode(dial_category_rows($db, 1)) === $before, 'stale merge made no write');
$moved = dial_category_merge($db, 1, 2, 3);
must($moved === 2, 'inactive and one-time subscriptions move');
must((int) $db->querySingle('SELECT count(*) FROM subscriptions WHERE user_id=1 AND category_id=3') === 2, 'rows preserved');
must((int) $db->querySingle('SELECT count(*) FROM subscriptions WHERE user_id=2 AND category_id=6') === 1, 'other account unchanged');
must(array_column(dial_category_rows($db, 1), 'display_order') === [1, 2, 3], 'merge renumbers order');

$db = fixture();
$db->exec("CREATE TRIGGER reject_category_delete BEFORE DELETE ON categories
           WHEN OLD.id=2 BEGIN SELECT RAISE(ABORT, 'fixture failure'); END");
rejects('write_failed', fn() => dial_category_merge($db, 1, 2, 3));
must((int) $db->querySingle('SELECT count(*) FROM subscriptions WHERE user_id=1 AND category_id=2') === 2,
    'subscription move rolled back after delete failure');

$db = fixture();
dial_category_sort($db, 2, [6, 5]);
must(array_column(dial_category_rows($db, 2), 'id') === [6, 5], 'user 2 may sort own rows');
must(array_column(dial_category_rows($db, 2), 'display_order') === [1, 2], 'user 2 has no protected id 1');

$db = fixture();
$db->exec('DELETE FROM subscriptions WHERE user_id=1');
$before = json_encode(dial_category_rows($db, 1));
rejects('stale_selection', fn() => dial_category_apply_starter($db, 1,
    [['id' => 4, 'expectedName' => 'Old Empty']]));
must(json_encode(dial_category_rows($db, 1)) === $before, 'stale starter preview rolled back');
rejects('immutable_category', fn() => dial_category_apply_starter($db, 1,
    [['id' => 1, 'expectedName' => 'No category']]));
rejects('stale_selection', fn() => dial_category_apply_starter($db, 1,
    [['id' => 6, 'expectedName' => 'Private']]));
must(json_encode(dial_category_rows($db, 1)) === $before, 'invalid starter selection made no write');
$result = dial_category_apply_starter($db, 1, [['id' => 4, 'expectedName' => 'Empty']]);
must(count($result['addedIds']) === 3 && $result['deletedIds'] === [4], 'adds missing starter names and deletes only selected empty');
$names = array_column(dial_category_rows($db, 1), 'name');
must(in_array('AI &amp; Tools', $names, true) && in_array('Infra', $names, true), 'unselected custom and existing starter retained');
must(in_array('AI', $names, true) && in_array('Domains', $names, true) && in_array('Media', $names, true), 'missing starter names added');
must((int) $db->querySingle('SELECT count(*) FROM categories WHERE user_id=2') === 2, 'starter does not touch another account');

$db = fixture();
$before = json_encode(dial_category_rows($db, 1));
rejects('category_in_use', fn() => dial_category_apply_starter($db, 1, []));
must(json_encode(dial_category_rows($db, 1)) === $before, 'nonempty account unchanged');

$db = fixture();
$db->exec('INSERT INTO categories (id,name,"order",user_id) VALUES
    (100,"Old Empty",1,3),(101,"Custom",2,3),(102,"ai",3,3)');
$result = dial_category_apply_starter($db, 3, [['id' => 100, 'expectedName' => 'Old Empty']]);
must(count($result['addedIds']) === 3 && $result['deletedIds'] === [100],
    'starter respects existing category names regardless of case');
must(in_array('Custom', array_column(dial_category_rows($db, 3), 'name'), true), 'unselected custom row survives');
must(in_array('ai', array_column(dial_category_rows($db, 3), 'name'), true), 'existing lowercase AI survives');
must(!in_array('AI', array_column(dial_category_rows($db, 3), 'name'), true), 'starter does not duplicate lowercase AI');
must(array_column(dial_category_rows($db, 3), 'display_order') === [1, 2, 3, 4, 5], 'starter order contiguous');
must($db->querySingle('PRAGMA integrity_check') === 'ok', 'SQLite integrity after transactions');
echo "Category name, ownership, and basic operation checks passed.\n";

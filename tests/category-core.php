<?php
require '/var/www/html/includes/dial_categories.php';

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

$newId = dial_category_add($db, 1, 'Books & Media');
must((string) $db->querySingle("SELECT name FROM categories WHERE id=$newId") === 'Books &amp; Media', 'named add escaped once');
$unnamedId = dial_category_add($db, 1, null);
must((string) $db->querySingle("SELECT name FROM categories WHERE id=$unnamedId") === 'Category', 'Settings unnamed add');
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
echo "Category name, ownership, and basic operation checks passed.\n";

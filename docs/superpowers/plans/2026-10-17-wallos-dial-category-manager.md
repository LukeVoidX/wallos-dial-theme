# Wallos Dial 1.0 Category Manager Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let each Wallos Dial user add, rename, reorder, merge, and remove their own subscription categories through a safe, accessible interface.

**Architecture:** Keep Wallos's categories table as the single source of truth. A small Dial domain helper owns category validation, display decoding, and transactional operations; the inherited category endpoint retains its Settings contract, while a new endpoint handles merge and the optional starter set. A dedicated page provides the management UI, and existing subscription and Settings views link to it and display stored names consistently.

**Tech Stack:** PHP and SQLite3 inside the pinned Wallos 5.8.3 Docker image; vanilla JavaScript, SortableJS already bundled by Wallos, existing Wallos CSS and CSRF/session helpers; shell and PHP tests against disposable databases.

**Spec:** docs/superpowers/specs/2026-10-17-wallos-dial-1.0-design.md, especially Category manager and Acceptance evidence items 2, 4, 5, and 7.

## Global Constraints

- Execute this plan after the separate Wallos 5.8.3 compatibility plan has pinned and reviewed the multi-architecture base image. Do not copy files from the older local v5.8.1 checkout.
- Keep Dial's versioned Docker source overlay, upstream-file hash gate, and user-owned Wallos categories(id, name, order, user_id) table; create no second category store and no numbered upstream migration.
- Do not touch a private or production Wallos database. All write tests use an isolated, disposable, synthetic database.
- Do not alter the private Wallos deployment or publish a GitHub release while executing this plan.
- Keep the public Demo read-only at both reverse proxy and application mutation endpoints. It may show GET /categories.php with disabled controls.
- Render management copy in English, Simplified Chinese, and Traditional Chinese; fall back to English for other Wallos languages. Category names are user data and must not be translated.
- Existing Settings calls to POST /endpoints/categories/category.php must keep their success, message, and categoryId JSON fields. Its unnamed add action must keep inserting Category.
- Wallos 5.8.3 already checks category_id ownership on subscription add and edit. The later icon-system plan owns its add.php overlay and must preserve that exact upstream check; this plan does not overlay add.php.
- This plan owns the first 1.0 edits to shared theme/subscriptions.php and theme/styles/dial.css. Execute the icon-system plan afterward and preserve this plan's toolbar, grouping, and scoped category CSS.

## File Map

| File | Responsibility |
| --- | --- |
| theme/includes/dial_categories.php | Name boundary, account-scoped reads, CRUD, sort, merge, starter transaction |
| theme/includes/dial_category_i18n.php | English, Simplified Chinese, Traditional Chinese management labels and error copy |
| theme/endpoints/categories/category.php | Reviewed 5.8.3 endpoint overlay retaining Settings' add/edit/delete/sort interface |
| theme/endpoints/categories/dial_manage.php | POST-only merge and starter actions using the shared domain helper |
| theme/categories.php | Authenticated category page and read-only Demo presentation |
| theme/scripts/dial-categories.js | Forms, dialogs, keyboard/drag order, focus and status feedback |
| theme/service-worker.php | Append the manager and refresh scripts to the compatibility plan's static cache without changing its version key or private-image bypass |
| theme/includes/header.php | Categories page navigation state and back/forward-cache revision refresh |
| theme/settings.php | Reviewed 5.8.3 Settings overlay, manager link, one-layer-decoded category inputs |
| theme/subscriptions.php | Manage categories toolbar link and safe category option labels |
| theme/includes/list_subscriptions.php | Safe grouped and ungrouped category labels |
| theme/styles/dial.css | Category manager layout and interaction states scoped under .dial-category-manager |
| compat/upstream-files.sha256 | Reviewed hashes for Settings and category endpoint replacements |
| demo/Caddyfile.site.example | Read-only GET /categories.php, no category write route |
| demo/smoke.sh | Demo manager and write-block checks |
| tests/category-core.php | Disposable SQLite transaction, ownership, encoding, and starter checks |
| tests/category-http.sh | Isolated HTTP session, CSRF, owner, Demo, and rendered page checks |
| docs/CATEGORIES.md | User instructions and category deletion/merge behavior |
| scripts/check-package.sh, .github/workflows/verify.yml | Include new files and run category tests in the built image |

## Review Focus

1. A category named AI & Tools, Unicode text, or a historically doubled entity must display safely and predictably after repeated edits; Task 1 tests the one-layer contract and Task 5 tests Settings and grouped output.
2. Another tab can rename or attach a subscription between a starter preview and confirmation; Task 2 tests that stale expected names or a nonempty ledger abort the entire operation.
3. A category from another account, duplicate sort IDs, or a disappearing merge target must never change either account's data; Task 2 tests exact ID sets and rollback, and Task 3 tests HTTP ownership.
4. Returning with the browser Back button may restore stale filters and form options from bfcache; Task 5 tests a revision-based full refresh.
5. A Demo visitor may call category mutation URLs directly, bypassing the visible disabled controls; Task 3 tests application rejection and Task 6 tests the reverse-proxy deny rule.

---

### Task 1: Category name boundary and safe basic operations

**Files:**
- Create: theme/includes/dial_categories.php
- Create: tests/category-core.php

**Interfaces:**
- Consumes: SQLite3, Wallos includes/inputvalidation.php::validate(), categories(id, name, order, user_id), and subscriptions(id, category_id, user_id).
- Produces: dial_category_name_for_input(string $stored): string; dial_category_html(string $stored): string; dial_category_validate_name(string $raw): string; dial_category_rows(SQLite3 $db, int $userId): array; dial_category_add(SQLite3 $db, int $userId, ?string $rawName): int; dial_category_rename(SQLite3 $db, int $userId, int $id, string $rawName): void; dial_category_delete_empty(SQLite3 $db, int $userId, int $id): void. Business failures throw DialCategoryError with a stable code such as invalid_name, foreign_category, last_category, immutable_category, or category_in_use.

- [ ] **Step 1: Write the failing in-memory test.** Put this setup and the named assertions in tests/category-core.php. Keep the fixture function reusable by later tasks.

~~~php
<?php
require '/var/www/html/includes/dial_categories.php';
function must(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
function fixture(): SQLite3 {
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
must(dial_category_name_for_input('AI &amp;amp; Tools') === 'AI &amp; Tools', 'do not silently change historical literal');
must(dial_category_validate_name('AI & Tools') === 'AI &amp; Tools', 'Wallos storage contract');
must(count(dial_category_rows($db, 1)) === 4, 'account-scoped rows');
must(dial_category_rows($db, 1)[1]['subscription_count'] === 2, 'count includes disabled one-time item');
~~~

Also assert blank, ASCII control, and 61-codepoint names fail; 60 Chinese codepoints pass. Test rename of id 2, reject a rename of foreign id 6, reject deletion of used id 2 and immutable id 1, delete empty id 4, and reject deletion of the final category for a one-row account.

- [ ] **Step 2: Verify red.** Build the compatibility image, then run the test before creating the helper.

~~~bash
docker build -t wallos-dial:category .
docker run --rm -v "$PWD/tests:/tests:ro" --entrypoint php wallos-dial:category /tests/category-core.php
~~~

Expected: nonzero because dial_categories.php is absent.

- [ ] **Step 3: Implement the smallest domain helper.** Require inputvalidation.php from the helper. Decode exactly one entity layer for editing; then escape at the HTML boundary. Validate after trimming, reject control codepoints and names outside 1–60 Unicode codepoints, and call Wallos validate() exactly once before storing. Query rows with a LEFT JOIN that counts all subscription states while scoping both sides by user_id. Use prepared statements for every mutation. Empty delete takes BEGIN IMMEDIATE, checks ownership, id 1, all-state reference count, and account category count before deleting exactly one row; roll back on every failed check or write.

~~~php
function dial_category_name_for_input(string $stored): string {
    return html_entity_decode($stored, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
function dial_category_html(string $stored): string {
    return htmlspecialchars(dial_category_name_for_input($stored), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
// dial_category_rows uses:
// SELECT c.id,c.name,c."order" AS display_order,COUNT(s.id) AS subscription_count
// FROM categories c LEFT JOIN subscriptions s
// ON s.category_id=c.id AND s.user_id=:userId
// WHERE c.user_id=:userId GROUP BY c.id ORDER BY c."order",c.id
~~~

- [ ] **Step 4: Verify green and lint.**

~~~bash
docker build -t wallos-dial:category .
docker run --rm -v "$PWD/tests:/tests:ro" --entrypoint php wallos-dial:category /tests/category-core.php
docker run --rm --entrypoint php wallos-dial:category -l /var/www/html/includes/dial_categories.php
~~~

Expected: category-core passes; PHP reports no syntax errors.

- [ ] **Step 5: Commit this independently testable unit.**

~~~bash
git add theme/includes/dial_categories.php tests/category-core.php
git commit -m "feat: add safe category domain operations"
~~~

### Task 2: Atomic sort, merge, and optional starter set

**Files:**
- Modify: theme/includes/dial_categories.php
- Modify: tests/category-core.php

**Interfaces:**
- Consumes: Task 1's DialCategoryError, name functions, and account-scoped rows.
- Produces: dial_category_sort(SQLite3 $db, int $userId, array $movableIds): void; dial_category_merge(SQLite3 $db, int $userId, int $sourceId, int $targetId): int; dial_category_apply_starter(SQLite3 $db, int $userId, array $selectedRows): array. Each selected starter row has exact integer id and expected stored name; the result lists added IDs and deleted IDs.

- [ ] **Step 1: Extend category-core.php with failing transaction assertions.** Use a fresh fixture per case and check both categories and subscriptions after each expected failure.

~~~php
$db = fixture();
dial_category_sort($db, 1, [4, 3, 2]);
must(array_column(dial_category_rows($db, 1), 'id') === [1, 4, 3, 2], 'id 1 stays first');
$before = json_encode(dial_category_rows($db, 1));
try { dial_category_sort($db, 1, [4, 4, 2]); throw new RuntimeException('accepted duplicate'); }
catch (DialCategoryError $error) { must($error->getMessage() === 'stale_order', 'sort error'); }
must(json_encode(dial_category_rows($db, 1)) === $before, 'rejected sort made no write');
$moved = dial_category_merge($db, 1, 2, 3);
must($moved === 2, 'inactive and one-time subscriptions move');
must((int) $db->querySingle('SELECT count(*) FROM subscriptions WHERE user_id=1 AND category_id=3') === 2, 'rows preserved');
must((int) $db->querySingle('SELECT count(*) FROM subscriptions WHERE user_id=2 AND category_id=6') === 1, 'other account unchanged');
~~~

Add cases for a foreign target id 6, source id 1, source equals target, missing source, a database write failure inside a transaction, and user 2 sorting with no id 1 (its movable IDs receive order 1..N). For starter, use a new account with no subscriptions and both empty default and custom rows: it adds missing AI/Infra/Domains/Media, removes only selected id/name pairs, leaves unselected custom rows, protects id 1, rejects stale names, rejects an account that gained a subscription, and never partially applies a failed preview.

~~~php
$db = fixture();
$db->exec("CREATE TRIGGER reject_category_delete BEFORE DELETE ON categories
           WHEN OLD.id=2 BEGIN SELECT RAISE(ABORT, 'fixture failure'); END");
try { dial_category_merge($db, 1, 2, 3); throw new RuntimeException('merge succeeded'); }
catch (DialCategoryError $error) { must($error->getMessage() === 'write_failed', 'write failure reported'); }
must((int) $db->querySingle('SELECT count(*) FROM subscriptions WHERE user_id=1 AND category_id=2') === 2,
     'UPDATE rolled back after DELETE failure');
$db = fixture();
$db->exec('DELETE FROM subscriptions WHERE user_id=1');
$before = json_encode(dial_category_rows($db, 1));
try {
    dial_category_apply_starter($db, 1, [['id' => 4, 'expectedName' => 'Old Empty']]);
    throw new RuntimeException('stale starter selection accepted');
} catch (DialCategoryError $error) {
    must($error->getMessage() === 'stale_selection', 'stale preview rejected');
}
must(json_encode(dial_category_rows($db, 1)) === $before, 'starter rollback');
~~~

For the id 1 starter protection case, use the fixture's user 1 after removing its subscriptions; a new account cannot own global id 1.

- [ ] **Step 2: Verify red.**

~~~bash
docker run --rm -v "$PWD/tests:/tests:ro" --entrypoint php wallos-dial:category /tests/category-core.php
~~~

Expected: nonzero because sort, merge, and starter functions do not exist.

- [ ] **Step 3: Implement the transactions.** For every operation, start BEGIN IMMEDIATE and fetch the current account rows under the write lock. Sort compares the submitted movable ID set with the current set exactly, rejects duplicates and foreign IDs, keeps id 1 first only when owned by this account, and writes contiguous order. Merge validates distinct owned source/target, moves only subscriptions with matching user_id and source, deletes exactly one owned source, renumbers remaining rows, then commits. Starter requires zero subscriptions within the lock, compares each selected row's id and stored name with the current row, excludes selected rows when finding missing starter names, inserts missing names, removes only selected empty rows, renumbers, and commits. All SQL failures and exceptions roll back. Do not rely on foreign-key enforcement.

~~~sql
BEGIN IMMEDIATE;
UPDATE subscriptions SET category_id = :target
 WHERE user_id = :userId AND category_id = :source;
DELETE FROM categories WHERE id = :source AND user_id = :userId;
-- Verify changes() is exactly 1 for the DELETE before renumbering and COMMIT.
~~~

The starter preview lists exactly which IDs and names the user selected for deletion; the server rejects a stale expected name and applies add/delete in one transaction. No category is preselected for deletion.

- [ ] **Step 4: Rebuild and verify every case.**

~~~bash
docker build -t wallos-dial:category .
docker run --rm -v "$PWD/tests:/tests:ro" --entrypoint php wallos-dial:category /tests/category-core.php
~~~

Expected: all transaction assertions pass, including unchanged state after errors.

- [ ] **Step 5: Commit the transaction layer.**

~~~bash
git add theme/includes/dial_categories.php tests/category-core.php
git commit -m "feat: make category sort merge and starter atomic"
~~~

### Task 3: Bind domain operations to authenticated endpoints

**Files:**
- Create from the reviewed 5.8.3 base, then modify: theme/endpoints/categories/category.php
- Create: theme/endpoints/categories/dial_manage.php
- Modify: compat/upstream-files.sha256
- Create: tests/category-http.sh

**Interfaces:**
- Consumes: Task 1 and Task 2 domain functions; Wallos includes/connect_endpoint.php, includes/validate_endpoint.php, session userId, and X-CSRF-Token.
- Produces: POST /endpoints/categories/category.php with actions add, edit, delete, sort; POST /endpoints/categories/dial_manage.php with actions merge and starter. Responses are JSON with success:boolean, code:string, message:string; add also returns categoryId:int, merge returns movedCount:int, and starter returns addedIds:int[] and deletedIds:int[]. The existing Settings fields success, message, and categoryId stay available.

- [ ] **Step 1: Write the failing HTTP test driver.** tests/category-http.sh must create a disposable Docker DB/logo volume, let the image finish first-boot migration, stop it, run demo/seed.py against the empty migrated DB, add a synthetic second user and foreign category using Python sqlite3, then restart with DEMO_MODE=0. The script obtains an account-1 cookie with GET /, extracts window.csrfToken from HTML, and exercises both endpoints. It removes the container and temporary directory on exit. The seed must be synthetic and must refuse a nonempty DB.

~~~bash
#!/usr/bin/env bash
set -euo pipefail
image=wallos-dial:category
if test "$#" -ge 1; then image=$1; fi
mkdir -p data
scratch=$(mktemp -d "$PWD/data/.dial-category.XXXXXX")
name="dial-category-http-$$"
cleanup() {
  docker rm -f "$name" "$name-demo" >/dev/null 2>&1 || true
  rm -rf "$scratch"
}
trap cleanup EXIT
mkdir -p "$scratch/db" "$scratch/logos"
chmod 0777 "$scratch/db" "$scratch/logos"
docker run -d --name "$name" -p 127.0.0.1::80 \
  -v "$scratch/db:/var/www/html/db" \
  -v "$scratch/logos:/var/www/html/images/uploads/logos" "$image" >/dev/null
address=$(docker port "$name" 80/tcp | head -n 1)
for attempt in $(seq 1 40); do
  if test -s "$scratch/db/wallos.db" &&
     curl -fsS "http://$address/health.php" >/dev/null 2>&1 &&
     docker logs "$name" 2>&1 | grep -F 'Updated next payment dates' >/dev/null; then break; fi
  sleep 1
done
test -s "$scratch/db/wallos.db"
docker stop "$name" >/dev/null
python3 demo/seed.py "$scratch/db/wallos.db" "$scratch/logos"
python3 - "$scratch/db/wallos.db" <<'PY'
import sqlite3, sys
db = sqlite3.connect(sys.argv[1])
db.execute("INSERT INTO user (id,username,email,password,main_currency,firstname,language,budget) VALUES (2,'other','other@example.invalid','!disabled!',2,'Other','en',0)")
db.execute('INSERT INTO categories (id,name,"order",user_id) VALUES (900,"Private",1,2)')
db.commit()
PY
docker start "$name" >/dev/null
for attempt in $(seq 1 40); do
  if curl -fsS "http://$address/" >/dev/null 2>&1; then break; fi
  sleep 1
done
python3 - "$address" "$scratch/db/wallos.db" <<'PY'
import http.cookiejar, json, re, sqlite3, sys, urllib.parse, urllib.request
from urllib.error import HTTPError
base, dbpath = 'http://' + sys.argv[1], sys.argv[2]
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
page = opener.open(base + '/').read().decode()
csrf = re.search(r'window\.csrfToken\s*=\s*"([^"]+)"', page).group(1)
def post(path, data, token=csrf):
    body = urllib.parse.urlencode(data, doseq=True).encode()
    headers = {'Content-Type': 'application/x-www-form-urlencoded'}
    if token: headers['X-CSRF-Token'] = token
    request = urllib.request.Request(base + path, body, headers)
    try: response = opener.open(request)
    except HTTPError as error: response = error
    return json.loads(response.read().decode())
category = '/endpoints/categories/category.php'
manage = '/endpoints/categories/dial_manage.php'
db = sqlite3.connect(dbpath)
named = post(category, {'action':'add','name':'AI & Tools'})
assert named['success'] and isinstance(named['categoryId'], int), named
new_id = named['categoryId']
assert db.execute('SELECT name FROM categories WHERE id=?',(new_id,)).fetchone()[0] == 'AI &amp; Tools'
legacy = post(category, {'action':'add'})
assert legacy['success'] and legacy['categoryId'], legacy
assert db.execute('SELECT name FROM categories WHERE id=?',(legacy['categoryId'],)).fetchone()[0] == 'Category'
assert not post(category, {'action':'edit','categoryId':900,'name':'stolen'})['success']
assert db.execute('SELECT name FROM categories WHERE id=900').fetchone()[0] == 'Private'
assert not post(category, {'action':'edit','categoryId':new_id,'name':'Denied'}, token=None)['success']
owned = [row[0] for row in db.execute('SELECT id FROM categories WHERE user_id=1 AND id!=1 ORDER BY "order",id')]
before = list(db.execute('SELECT id,"order" FROM categories WHERE user_id=1 ORDER BY id'))
assert not post(category, {'action':'sort','categoryIds[]':[owned[0],owned[0]]})['success']
assert list(db.execute('SELECT id,"order" FROM categories WHERE user_id=1 ORDER BY id')) == before
source = db.execute("SELECT id FROM categories WHERE user_id=1 AND name='AI'").fetchone()[0]
target = db.execute("SELECT id FROM categories WHERE user_id=1 AND name='Infra'").fetchone()[0]
before_subs = list(db.execute('SELECT id,category_id FROM subscriptions WHERE user_id=1 ORDER BY id'))
assert not post(manage, {'action':'merge','sourceId':source,'targetId':900})['success']
assert list(db.execute('SELECT id,category_id FROM subscriptions WHERE user_id=1 ORDER BY id')) == before_subs
merged = post(manage, {'action':'merge','sourceId':source,'targetId':target})
assert merged['success'] and merged['movedCount'] == sum(cat == source for _,cat in before_subs), merged
assert db.execute('SELECT count(*) FROM subscriptions WHERE user_id=1 AND category_id=?',(source,)).fetchone()[0] == 0
assert not post(manage, {'action':'starter','confirm':1})['success']
db.close()
PY
docker stop "$name" >/dev/null
docker run -d --name "$name-demo" -e DEMO_MODE=1 -p 127.0.0.1::80 \
  -v "$scratch/db:/var/www/html/db" \
  -v "$scratch/logos:/var/www/html/images/uploads/logos" "$image" >/dev/null
demo_address=$(docker port "$name-demo" 80/tcp | head -n 1)
for attempt in $(seq 1 40); do
  if curl -fsS "http://$demo_address/" >/dev/null 2>&1; then break; fi
  sleep 1
done
python3 - "$demo_address" <<'PY'
import http.cookiejar, json, re, sys, urllib.parse, urllib.request
from urllib.error import HTTPError
base = 'http://' + sys.argv[1]
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
page = opener.open(base + '/').read().decode()
csrf = re.search(r'window\.csrfToken\s*=\s*"([^"]+)"', page).group(1)
for path, data in [
    ('/endpoints/categories/category.php', {'action':'add','name':'Denied'}),
    ('/endpoints/categories/dial_manage.php', {'action':'merge','sourceId':2,'targetId':3})
]:
    request = urllib.request.Request(base + path, urllib.parse.urlencode(data).encode(),
                                     {'X-CSRF-Token':csrf})
    try: response = opener.open(request)
    except HTTPError as error: response = error
    result = json.loads(response.read().decode())
    assert result['success'] is False and result['code'] == 'demo_read_only', result
PY
~~~

The test uses urllib.parse.urlencode so ampersands in names are preserved. Keep additional assertions for invalid blank names and missing IDs in the same script. This script deliberately sends a known foreign id 900; the direct SQLite query proves that the row was not modified.

- [ ] **Step 2: Verify red against the compatibility image.**

~~~bash
docker build -t wallos-dial:category .
bash tests/category-http.sh
~~~

Expected: nonzero because the named add/merge routes and Demo write guard are not implemented.

- [ ] **Step 3: Review the 5.8.3 base and implement endpoint adapters.** Compare the pinned base's endpoints/categories/category.php with the prior overlay before copying. Add its exact base hash to compat/upstream-files.sha256, preserving every existing hash. In the category.php overlay retain action names and existing Settings JSON fields. A missing name in add uses Category; a present but blank or invalid name fails after trimming. Call the Task 1/2 helpers for ownership and transaction checks. Both endpoints require connect_endpoint.php and validate_endpoint.php, return application/json, and reject getenv('DEMO_MODE') === '1' before writes. Do not accept a userId from POST.

~~~php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';
require_once '../../includes/dial_categories.php';
header('Content-Type: application/json; charset=UTF-8');
if (getenv('DEMO_MODE') === '1') {
    http_response_code(403);
    echo json_encode(['success' => false, 'code' => 'demo_read_only',
                      'message' => 'The public demo is read-only.']);
    exit;
}
~~~

For dial_manage.php, merge accepts positive sourceId and targetId. Starter accepts action=starter, confirm=1, and arrays deleteIds[] plus expectedNames[id]; it passes exact id/name pairs to dial_category_apply_starter(). Return a stable stale_selection error when another tab changed a selected name. Map DialCategoryError codes to localized messages while preserving the old endpoint's success/message fields. Never report success after a failed SQLite operation.

- [ ] **Step 4: Verify endpoints and the compatibility gate.**

~~~bash
docker build -t wallos-dial:category .
docker run --rm --entrypoint php wallos-dial:category -l /var/www/html/endpoints/categories/category.php
docker run --rm --entrypoint php wallos-dial:category -l /var/www/html/endpoints/categories/dial_manage.php
bash tests/category-http.sh
~~~

Expected: build hash check, PHP lint, and all HTTP assertions pass.

- [ ] **Step 5: Commit the authenticated interface.**

~~~bash
git add theme/endpoints/categories/category.php theme/endpoints/categories/dial_manage.php compat/upstream-files.sha256 tests/category-http.sh
git commit -m "feat: expose account-safe category endpoints"
~~~

### Task 4: Build the multilingual, keyboard-operable category page

**Files:**
- Create: theme/categories.php
- Create: theme/includes/dial_category_i18n.php
- Create: theme/scripts/dial-categories.js
- Modify: theme/styles/dial.css
- Modify: tests/category-http.sh
- Create: tests/category-ui-checklist.md

**Interfaces:**
- Consumes: Task 1 row and display-name helpers, Task 3 endpoint JSON, header.php's db/userId/lang/csrfToken globals, and Wallos scripts/libs/sortable.min.js.
- Produces: GET /categories.php and a page with #dial-category-list, #dial-category-status, a direct-name add form, one editor per category, keyboard Move up/Move down controls, a pointer drag handle, a Merge dialog, an empty-delete confirmation, and a starter preview with unchecked exact row deletions.
- Stable browser contract, independent of locale: main[data-testid="dial-category-manager"]; rows[data-testid="dial-category-row"][data-category-id]; input[data-testid="dial-category-new-name"] and input[data-testid="dial-category-name"]; buttons[data-testid="dial-category-add"], [data-testid="dial-category-save"], [data-testid="dial-category-move-up"], [data-testid="dial-category-move-down"], [data-testid="dial-category-merge"], [data-testid="dial-category-delete"], [data-testid="dial-category-starter"]; dialog[data-testid="dial-category-merge-dialog"] with select[data-testid="dial-category-merge-target"] and button[data-testid="dial-category-merge-confirm"]; dialog[data-testid="dial-category-starter-dialog"] with input[data-testid="dial-category-starter-delete"][data-category-id] and button[data-testid="dial-category-starter-confirm"]; button[data-testid="dial-category-delete-confirm"]; [data-testid="dial-category-status"][role="status"]; [data-testid="dial-category-readonly"] in Demo. Each control also has a translated accessible name, dialogs have aria-labelledby, and each row action is scoped to its row by data-category-id.

- [ ] **Step 1: Add a failing rendered-page assertion and the UI checklist.** Extend the self-contained tests/category-http.sh to GET /categories.php in its writable fixture, require all owned category IDs and their counts, reject account-2 Private, and require AI &amp; Tools as one source entity layer (AI &amp; Tools), never two layers (AI &amp;amp; Tools). The browser textContent must be AI & Tools. Require the stable data-testid contract from this task's Interfaces block. GET the same page in DEMO_MODE=1 and require a read-only explanation with no enabled mutation controls. Record these browser checks in tests/category-ui-checklist.md: keyboard-only add/rename/up/down/merge, pointer drag, Escape and focus return from both dialogs, explicit starter preview deletion choices, Demo disabled controls, narrow desktop and 125%/150% zoom, and all three UI languages. The final integration plan's separate two-account login fixture supplies the authenticated browser URL; this plan's HTTP script remains independent and self-cleaning.

~~~bash
bash tests/category-http.sh
~~~

Expected: nonzero because /categories.php does not exist.

- [ ] **Step 2: Create the translation map and safe server markup.** dial_category_copy(string $language): array supplies keys title, manage, add, save, move_up, move_down, merge, merge_target, merge_confirm, delete, delete_confirm, starter, starter_confirm, readonly, saved, invalid_name, foreign_category, category_in_use, immutable_category, last_category, stale_order, stale_selection, demo_read_only, and write_failed to PHP and JavaScript. Provide en, zh_cn, and zh_tw tables with an English fallback. categories.php includes header.php, dial_categories.php, and dial_category_i18n.php; it renders rows from dial_category_rows() and uses dial_category_html() for visible names and input values. Include every account category, including empty rows. For id 1 show a fixed-category explanation and suppress rename/delete/reorder controls; do not apply that rule to the first category of other accounts. If DEMO_MODE=1, show names/counts and installation guidance without forms or enabled write buttons. JSON for JavaScript copy uses JSON_HEX_TAG, JSON_HEX_AMP, JSON_HEX_QUOT, and JSON_HEX_APOS.

~~~php
$copy = dial_category_copy($lang);
$rows = dial_category_rows($db, (int) $userId);
$demoReadOnly = getenv('DEMO_MODE') === '1';
?>
<main class="contain dial-category-manager" data-testid="dial-category-manager">
  <h1><?= htmlspecialchars($copy['title'], ENT_QUOTES, 'UTF-8') ?></h1>
  <p id="dial-category-status" data-testid="dial-category-status" role="status" aria-live="polite"></p>
  <ol id="dial-category-list">
    <?php foreach ($rows as $row): ?>
      <li data-testid="dial-category-row" data-category-id="<?= (int) $row['id'] ?>">
        <span class="dial-category-count"><?= (int) $row['subscription_count'] ?></span>
        <span class="dial-category-label"><?= dial_category_html($row['name']) ?></span>
      </li>
    <?php endforeach; ?>
  </ol>
</main>
~~~

- [ ] **Step 3: Implement the client interactions.** JavaScript posts URLSearchParams with X-CSRF-Token. It inserts user-entered names with textContent, never innerHTML. The Add control sends a name in one request. Both keyboard buttons and SortableJS's onEnd send the complete list of movable IDs to action=sort; reverse an optimistic DOM move on failure. Merge requires a distinct target and shows the number of items moved before confirmation. Starter requires zero subscriptions, previews the four missing names and each selected exact id/name deletion, and sends only checked rows. A successful write stores a per-tab dialCategoryRevision marker in sessionStorage before reloading; an error retains focus and announces its translated message in #dial-category-status. On reload restore focus to the affected row or Add control.

~~~javascript
async function postCategory(endpoint, fields) {
  const response = await fetch(endpoint, {
    method: 'POST',
    headers: {'X-CSRF-Token': window.csrfToken},
    body: new URLSearchParams(fields)
  });
  const result = await response.json();
  if (!result.success) throw new Error(result.code || 'write_failed');
  sessionStorage.setItem('dialCategoryRevision', String(Date.now()));
  return result;
}
~~~

Use button elements for Move up/Move down rather than attempting to make a drag handle the sole keyboard operation. Scope new selectors under .dial-category-manager; preserve existing subscription styles.

- [ ] **Step 4: Verify markup, browser behavior, and PHP/JS syntax.**

~~~bash
docker build -t wallos-dial:category .
bash tests/category-http.sh
docker run --rm --entrypoint php wallos-dial:category -l /var/www/html/categories.php
node --check theme/scripts/dial-categories.js
~~~

Expected: all commands pass. Then perform every test in tests/category-ui-checklist.md against an isolated local account and save the observed result; a passing HTTP request alone does not establish keyboard or visual acceptance.

- [ ] **Step 5: Commit the working manager page.**

~~~bash
git add theme/categories.php theme/includes/dial_category_i18n.php theme/scripts/dial-categories.js theme/styles/dial.css tests/category-http.sh tests/category-ui-checklist.md
git commit -m "feat: add accessible category manager"
~~~

### Task 5: Connect Settings, subscription grouping, and fresh navigation

**Files:**
- Create from the reviewed 5.8.3 base, then modify: theme/settings.php
- Modify: theme/subscriptions.php
- Modify: theme/includes/list_subscriptions.php
- Modify: theme/includes/header.php
- Create: theme/scripts/dial-category-refresh.js
- Modify: theme/service-worker.php, tests/compatibility/service-worker.sh (append category scripts only)
- Modify: compat/upstream-files.sha256
- Modify: tests/category-http.sh
- Modify: tests/category-ui-checklist.md

**Interfaces:**
- Consumes: Task 1 dial_category_html(), Task 4's /categories.php and sessionStorage dialCategoryRevision marker.
- Produces: a visible Manage categories link in the subscriptions toolbar and Settings category section; correctly displayed category names in Settings input, subscription select, grouped/un-grouped headings, and filters; an updated page after bfcache return.
- Stable browser contract: both Settings and Subscriptions use a[href="categories.php"][data-testid="dial-category-manage-link"], with a translated accessible name.

- [ ] **Step 1: Extend tests before editing views.** In tests/category-http.sh rename a category to AI & Tools through the endpoint, then GET /settings.php, /subscriptions.php, /stats.php, and GET /endpoints/subscriptions/get.php. Assert Settings input and subscription option show one HTML entity layer in source, grouped heading renders the same visible label, and the filter menu contains the category ID and displayed label. Test a second save with the same visible name and require the stored DB value to remain AI &amp; Tools. In tests/category-ui-checklist.md add: open subscriptions in a browser, visit Manage categories in the same tab, rename, go Back, verify the filter and Add/Edit category options update even if the browser restores the page from bfcache. Also check the Settings link reaches the manager and that the page navigation state is clear.

~~~bash
bash tests/category-http.sh
~~~

Expected: nonzero on the Settings double-encoding and missing manager links.

- [ ] **Step 2: Review and overlay Settings without losing upstream features.** Use the pinned 5.8.3 settings.php as the source. Preserve every original section, script, and form action; change only the category input value to dial_category_html($category['name']) and add a localized link to categories.php beside the Categories heading. Require dial_categories.php and dial_category_i18n.php near the top. Add the reviewed 5.8.3 settings.php hash to compat/upstream-files.sha256. The inherited scripts/settings.js remains untouched and can continue sending its unnamed add action and edit name value.

~~~php
<input type="text" name="category" autocomplete="off"
       value="<?= dial_category_html($category['name']) ?>">
<a href="categories.php"><?= htmlspecialchars($categoryCopy['manage'], ENT_QUOTES, 'UTF-8') ?></a>
~~~

- [ ] **Step 3: Make subscription and header changes.** In theme/subscriptions.php require the category helper, add a localized Manage categories link in the toolbar, and use dial_category_html() for form options. In theme/includes/list_subscriptions.php require the helper once and use dial_category_html() for the grouped category heading and legacy sort-by-category heading. Do not output a stored category value through a second htmlspecialchars() call. In theme/includes/header.php mark categories.php as a Settings-related page, and load dial-category-refresh.js with the current Dial asset version. Append `scripts/dial-categories.js` and `scripts/dial-category-refresh.js` exactly once to the compatibility worker's `staticAssets` list; extend `tests/compatibility/service-worker.sh` to assert both names. Leave the existing combined version key and `/endpoints/dial/icon.php` network-only branch intact. This script records the revision present at page load; on pageshow with persisted=true, it reloads only if sessionStorage.dialCategoryRevision changed while the page was in bfcache.

~~~javascript
const dialCategoryRevisionAtLoad = sessionStorage.getItem('dialCategoryRevision');
window.addEventListener('pageshow', function (event) {
  if (event.persisted &&
      sessionStorage.getItem('dialCategoryRevision') !== dialCategoryRevisionAtLoad) {
    window.location.reload();
  }
});
~~~

Keep the existing category filter renderer when it safely displays newly saved encoded names; assert its output in the HTTP test before deciding whether an extra override is needed. Do not add a second copy of category state in JavaScript.

- [ ] **Step 4: Build, run HTTP checks, and perform the bfcache browser check.**

~~~bash
docker build -t wallos-dial:category .
bash tests/category-http.sh
node --check theme/scripts/dial-category-refresh.js
docker run --rm --entrypoint php wallos-dial:category -l /var/www/html/settings.php
docker run --rm --entrypoint php wallos-dial:category -l /var/www/html/subscriptions.php
bash tests/compatibility/service-worker.sh wallos-dial:category
~~~

Expected: build and tests pass. Complete the browser check in tests/category-ui-checklist.md; normal navigation and bfcache restoration must both show the committed category list.

- [ ] **Step 5: Commit the integrated views.**

~~~bash
git add theme/settings.php theme/subscriptions.php theme/includes/list_subscriptions.php theme/includes/header.php theme/scripts/dial-category-refresh.js theme/service-worker.php tests/compatibility/service-worker.sh compat/upstream-files.sha256 tests/category-http.sh tests/category-ui-checklist.md
git commit -m "feat: connect category management across Wallos views"
~~~

### Task 6: Read-only Demo, user guide, and final category gate

**Files:**
- Modify: demo/Caddyfile.site.example
- Modify: demo/smoke.sh
- Modify: scripts/check-package.sh
- Modify: .github/workflows/verify.yml
- Create: docs/CATEGORIES.md
- Modify: README.md
- Modify: README.zh-CN.md
- Modify: tests/category-http.sh
- Modify: tests/category-ui-checklist.md

**Interfaces:**
- Consumes: Tasks 1–5 package and tests.
- Produces: a documented, CI-checked category manager in the installable image and a readable but immutable Demo manager page.

- [ ] **Step 1: Write the failing Demo and package assertions.** Keep demo/smoke.sh's existing first URL argument for direct origin checks used by demo/reset.sh. Add an optional second URL argument for the public Demo edge: GET /categories.php must show read-only copy and both POST category endpoints must return 404 at that edge. Direct origin with DEMO_MODE=1 must instead reject writes through the application endpoint, as tests/category-http.sh asserts. In scripts/check-package.sh require the manager page/helper/JS/endpoints and both category test entrypoints. Add tests/category-core.php and tests/category-http.sh as steps after image build in verify.yml.

~~~bash
# CI after docker build -t wallos-dial:ci .
docker run --rm -v "$PWD/tests:/tests:ro" --entrypoint php wallos-dial:ci /tests/category-core.php
bash tests/category-http.sh wallos-dial:ci
# During an approved Demo deployment, retain direct-origin smoke and also check the edge.
bash demo/smoke.sh http://127.0.0.1:8296/ https://wallos-demo.mostai.org/
~~~

~~~bash
./scripts/check-package.sh
docker build -t wallos-dial:category .
docker run --rm -v "$PWD/tests:/tests:ro" --entrypoint php wallos-dial:category /tests/category-core.php
bash tests/category-http.sh
~~~

Expected: package and Demo checks fail before the allowlist and required-file updates.

- [ ] **Step 2: Allow only manager GET at the Demo edge.** Add /categories.php to the GET/HEAD read-page matcher in demo/Caddyfile.site.example. Do not add either category mutation endpoint to any reverse-proxy allowlist. Keep the application DEMO_MODE guard from Task 3, because direct origin access must also fail. The actual public Caddy site file is changed only in the separately approved Demo deployment; this repository file is the reviewed candidate.

~~~caddyfile
@read_page {
    path / /index.php /login.php /subscriptions.php /categories.php /calendar.php /stats.php /about.php /manifest.php /service-worker.js /health.php /favicon.ico /styles/* /scripts/* /images/* /webfonts/*
    method GET HEAD
}
~~~

- [ ] **Step 3: Explain what users can safely change.** docs/CATEGORIES.md must give the route from Subscriptions and Settings; show Add, Rename, keyboard/drag Sort, Merge, Delete empty, and optional starter set; explain that Merge changes category_id but retains every subscription ID, payment, date, and Logo; explain that starter selection names every deletion and is optional; state that the public Demo is read-only. Link this guide from both READMEs. Do not imply that the four Demo groups replace an installer's Wallos categories automatically.

- [ ] **Step 4: Run the complete category gate and focused regression checks.**

~~~bash
./scripts/check-package.sh
docker compose config -q
docker compose -f compose.build.yaml config -q
docker build -t wallos-dial:category .
docker run --rm -v "$PWD/tests:/tests:ro" --entrypoint php wallos-dial:category /tests/category-core.php
bash tests/category-http.sh
./scripts/smoke-image.sh wallos-dial:category
git diff --check
~~~

Expected: all commands pass. Exercise a real isolated browser flow from tests/category-ui-checklist.md, including all three languages, keyboard controls, starter preview, bfcache return, and 125%/150% zoom. Confirm add/edit of a subscription rejects another account's category ID with the 5.8.3 ownership error, not merely a generic validation error; no row or Logo should change. The later icon-system plan must repeat this test after it overlays add.php. On a local reverse-proxy clone, GET /categories.php succeeds while both POST endpoints return 404; on direct origin with DEMO_MODE=1, both return a read-only failure. Record those as distinct observations.

- [ ] **Step 5: Commit the category deliverable without a release tag.**

~~~bash
git add demo/Caddyfile.site.example demo/smoke.sh scripts/check-package.sh .github/workflows/verify.yml docs/CATEGORIES.md README.md README.zh-CN.md tests/category-http.sh tests/category-ui-checklist.md
git commit -m "docs: ship tested category management workflow"
~~~

## Handoff to the other 1.0 plans

The compatibility plan must finish first and supply a reviewed Wallos 5.8.3 digest, upstream diff, and working image. This category plan can then build and test by itself. The icon plan runs afterward: its add.php overlay must preserve Wallos 5.8.3's category_id/user_id ownership check and pass tests/category-http.sh; its edits to theme/subscriptions.php and theme/styles/dial.css must retain the manager link and scoped category styles. The final integration plan reruns all category tests, the browser checklist, and Demo route checks before any 1.0.0 publication decision.

## Self-review checklist for this plan

- [ ] Each category-manager requirement in the approved spec maps to a task and an observation above.
- [ ] No private DB, upload, SVG map, or live service is touched by a category test.
- [ ] Every mutation includes account scope, POST, CSRF, Demo rejection, and a clear failure path.
- [ ] Merge, delete, sort, and starter have rollback tests; old Settings add/edit behavior remains.
- [ ] Output is safely encoded once, and historic double-encoded values remain manually correctable.
- [ ] Shared files and the add.php ownership check have explicit cross-plan owners.

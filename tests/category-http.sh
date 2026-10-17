#!/usr/bin/env bash
# Runs only against a fresh disposable synthetic Wallos database.
set -euo pipefail
cd "$(dirname "$0")/.."
image=${1:-wallos-dial:category}
mkdir -p data
scratch=$(mktemp -d "$PWD/data/.dial-category.XXXXXX")
name="dial-category-http-$$"
trap 'docker rm -f "$name" "$name-demo" >/dev/null 2>&1 || true; rm -r "$scratch"' EXIT
mkdir -p "$scratch/db" "$scratch/logos"

start() {
  local container=$1 demo=$2
  docker run -d --name "$container" -e "DEMO_MODE=$demo" -p 127.0.0.1::80 \
    -v "$scratch/db:/var/www/html/db" \
    -v "$scratch/logos:/var/www/html/images/uploads/logos" "$image" >/dev/null
  address=$(docker port "$container" 80/tcp | head -1)
  for attempt in $(seq 1 45); do
    if curl -fsS "http://$address/health.php" >/dev/null 2>&1 &&
       docker logs "$container" 2>&1 | grep -F 'Updated next payment dates' >/dev/null; then
      return
    fi
    sleep 1
  done
  echo "Synthetic category container failed: $container" >&2
  docker logs "$container" >&2 || true
  exit 1
}

start "$name" 0
docker exec "$name" sh -c 'chmod a+rwX /var/www/html/db /var/www/html/db/wallos.db /var/www/html/images/uploads/logos'
docker rm -f "$name" >/dev/null
python3 demo/seed.py "$scratch/db/wallos.db" "$scratch/logos"
python3 - "$scratch/db/wallos.db" <<'PY'
import sqlite3, sys
db = sqlite3.connect(sys.argv[1])
db.execute("INSERT INTO user (id,username,email,password,main_currency,firstname,language,budget) VALUES (2,'other','other@example.invalid','!disabled!',2,'Other','en',0)")
db.execute('INSERT INTO categories (id,name,"order",user_id) VALUES (900,"Private",1,2)')
db.commit()
db.close()
PY

start "$name" 0
python3 - "$address" "$scratch/db/wallos.db" <<'PY'
import http.cookiejar, json, re, sqlite3, sys, urllib.parse, urllib.request
from urllib.error import HTTPError
base, dbpath = 'http://' + sys.argv[1], sys.argv[2]
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
page = opener.open(base + '/').read().decode()
match = re.search(r'window\.csrfToken\s*=\s*"([^"]+)"', page)
assert match, 'synthetic auto-login did not reach dashboard'
csrf = match.group(1)

def post(path, data, token=csrf, client=opener):
    body = urllib.parse.urlencode(data, doseq=True).encode()
    headers = {'Content-Type': 'application/x-www-form-urlencoded'}
    if token: headers['X-CSRF-Token'] = token
    request = urllib.request.Request(base + path, body, headers)
    try: response = client.open(request)
    except HTTPError as error: response = error
    raw = response.read().decode()
    try: return json.loads(raw)
    except json.JSONDecodeError: raise AssertionError(f'{path} returned non-JSON: {raw[:1000]}')

category = '/endpoints/categories/category.php'
manage = '/endpoints/categories/dial_manage.php'
db = sqlite3.connect(dbpath)
db.execute('PRAGMA busy_timeout=5000')
named = post(category, {'action':'add', 'name':'AI & Tools'})
assert named['success'] and isinstance(named['categoryId'], int), named
new_id = named['categoryId']
assert db.execute('SELECT name FROM categories WHERE id=?',(new_id,)).fetchone()[0] == 'AI &amp; Tools'
renamed = post(category, {'action':'edit','categoryId':new_id,'name':'AI & Tools revised'})
assert renamed['success'], renamed
assert db.execute('SELECT name FROM categories WHERE id=?',(new_id,)).fetchone()[0] == 'AI &amp; Tools revised'
legacy = post(category, {'action':'add'})
assert legacy['success'] and legacy['categoryId'], legacy
assert db.execute('SELECT name FROM categories WHERE id=?',(legacy['categoryId'],)).fetchone()[0] == 'Category'
assert not post(category, {'action':'add','name':'  '})['success']
assert not post(category, {'action':'edit','categoryId':900,'name':'stolen'})['success']
assert db.execute('SELECT name FROM categories WHERE id=900').fetchone()[0] == 'Private'
assert not post(category, {'action':'edit','categoryId':new_id,'name':'Denied'}, token=None)['success']
assert not post(category, {'action':'edit','name':'Missing ID'})['success']
assert not post(category, {'action':'delete','categoryId':900})['success']
anonymous = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
assert not post(category, {'action':'add','name':'No session'}, client=anonymous)['success']
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
manager = opener.open(base + '/categories.php').read().decode()
for category_id, count in db.execute('SELECT c.id, COUNT(s.id) FROM categories c LEFT JOIN subscriptions s ON s.category_id=c.id AND s.user_id=1 WHERE c.user_id=1 GROUP BY c.id'):
    assert f'data-category-id="{category_id}"' in manager, category_id
    assert f'data-category-count="{count}"' in manager, (category_id, count)
assert '>Private<' not in manager
assert '>AI &amp; Tools revised</span>' in manager and '>AI &amp;amp; Tools revised</span>' not in manager
for testid in ('dial-category-manager','dial-category-row','dial-category-new-name',
               'dial-category-name','dial-category-add','dial-category-save',
               'dial-category-move-up','dial-category-move-down','dial-category-merge',
               'dial-category-delete','dial-category-starter','dial-category-merge-dialog',
               'dial-category-merge-target','dial-category-merge-confirm',
               'dial-category-starter-dialog','dial-category-starter-delete',
               'dial-category-starter-confirm','dial-category-delete-confirm','dial-category-status'):
    assert f'data-testid="{testid}"' in manager, testid
assert db.execute('PRAGMA integrity_check').fetchone()[0] == 'ok'
db.close()
PY

docker rm -f "$name" >/dev/null
start "$name-demo" 1
python3 - "$address" <<'PY'
import http.cookiejar, json, re, sys, urllib.parse, urllib.request
from urllib.error import HTTPError
base = 'http://' + sys.argv[1]
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
page = opener.open(base + '/').read().decode()
match = re.search(r'window\.csrfToken\s*=\s*"([^"]+)"', page)
assert match, 'synthetic demo did not reach dashboard'
csrf = match.group(1)
manager = opener.open(base + '/categories.php').read().decode()
assert 'data-testid="dial-category-readonly"' in manager
assert 'data-testid="dial-category-add"' not in manager
assert 'data-testid="dial-category-save"' not in manager
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
echo 'Category HTTP ownership, CSRF, transactions, and Demo read-only checks passed.'

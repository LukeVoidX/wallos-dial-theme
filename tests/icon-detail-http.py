#!/usr/bin/env python3
"""Detail API icon contract on a disposable two-account fixture."""
import argparse
import http.cookiejar
import json
import re
import sqlite3
import urllib.parse
import urllib.request
from urllib.error import HTTPError

parser = argparse.ArgumentParser()
parser.add_argument('--base-url', required=True)
parser.add_argument('--database', required=True)
args = parser.parse_args()
base = args.base_url.rstrip('/')

db = sqlite3.connect(args.database)
db.execute('PRAGMA busy_timeout=5000')
owned = db.execute("SELECT id FROM subscriptions WHERE user_id=1 AND name='Cinema Club'").fetchone()[0]
db.execute("""INSERT INTO dial_subscription_icons
    (user_id,subscription_id,source,catalog_key,presentation,treatment,scale)
    VALUES (1,?,'catalog','chatgpt','framed','mono',110)
    ON CONFLICT(user_id,subscription_id) DO UPDATE SET
      source=excluded.source,catalog_key=excluded.catalog_key,
      asset_id=NULL,presentation=excluded.presentation,
      treatment=excluded.treatment,scale=excluded.scale""", (owned,))
db.commit()

def client(username):
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    opener.open(base + '/login.php').read()
    opener.open(urllib.request.Request(base + '/login.php', urllib.parse.urlencode({
        'username': username, 'password': 'dial-fixture-only'
    }).encode())).read()
    page = opener.open(base + '/').read().decode()
    assert re.search(r'window\.csrfToken\s*=\s*"[^"]+"', page), username
    return opener

one = client('demo')
two = client('second')
response = one.open(base + f'/endpoints/subscription/get.php?id={owned}')
data = json.load(response)
assert 'no-store' in response.headers.get('Cache-Control','')
assert data['id'] == owned and data['dial_icon']['kind'] == 'catalog', data
assert data['dial_icon']['src'] == 'images/dial-catalog/chatgpt.svg'
assert data['dial_icon']['presentation'] == 'framed' and data['dial_icon']['treatment'] == 'mono'
assert data['dial_icon']['scale'] == 110
assert data['dial_icon_preference'] == {
    'source':'catalog', 'catalog_key':'chatgpt', 'asset_id':None,
    'presentation':'framed', 'treatment':'mono', 'scale':110,
}
try: foreign = two.open(base + f'/endpoints/subscription/get.php?id={owned}')
except HTTPError as error: foreign = error
assert b'dial_icon' not in foreign.read()
print('Owned detail descriptor and foreign-account isolation passed.')

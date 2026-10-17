#!/usr/bin/env bash
# Disposable synthetic data checks every server-rendered icon path.
set -euo pipefail
cd "$(dirname "$0")/.."
image=${1:-wallos-dial:compat}
mkdir -p data
scratch=$(mktemp -d "$PWD/data/.dial-icon-render.XXXXXX")
name="dial-icon-render-$$"
trap 'docker rm -f "$name" >/dev/null 2>&1 || true; rm -r "$scratch"' EXIT
mkdir -p "$scratch/db" "$scratch/logos"

start() {
  docker run -d --name "$name" -e DEMO_MODE=0 \
    -e "PUID=$(id -u)" -e "PGID=$(id -g)" -p 127.0.0.1::80 \
    -v "$scratch/db:/var/www/html/db" \
    -v "$scratch/logos:/var/www/html/images/uploads/logos" "$image" >/dev/null
  address=$(docker port "$name" 80/tcp | head -1)
  for attempt in $(seq 1 45); do
    if curl -fsS "http://$address/health.php" >/dev/null 2>&1 &&
       docker logs "$name" 2>&1 | grep -Fq 'Updated next payment dates'; then return; fi
    sleep 1
  done
  docker logs "$name" >&2 || true
  exit 1
}
start
docker rm -f "$name" >/dev/null
python3 demo/seed.py "$scratch/db/wallos.db" "$scratch/logos"
python3 - "$scratch/db/wallos.db" <<'PY'
import sqlite3,sys
db=sqlite3.connect(sys.argv[1])
for name in ('Cinema Club','Northstar VPS','Orbit Models'):
    row=db.execute('SELECT id FROM subscriptions WHERE user_id=1 AND name=?',(name,)).fetchone()
    assert row, name
    db.execute("INSERT INTO dial_subscription_icons(user_id,subscription_id,source,catalog_key,presentation,treatment,scale) VALUES(1,?,'catalog','chatgpt','framed','mono',110)",(row[0],))
db.commit();db.close()
PY
start
python3 - "$scratch/db/wallos.db" <<'PY'
from datetime import date,timedelta
import sqlite3,sys
db=sqlite3.connect(sys.argv[1])
db.execute("UPDATE subscriptions SET auto_renew=0,next_payment=? WHERE name='Northstar VPS'",((date.today()-timedelta(days=1)).isoformat(),))
db.execute("UPDATE subscriptions SET cancellation_date=? WHERE name='Orbit Models'",((date.today()+timedelta(days=2)).isoformat(),))
db.commit();db.close()
PY
python3 - "$address" <<'PY'
import http.cookiejar,re,sys,urllib.request
base='http://'+sys.argv[1]
jar=http.cookiejar.CookieJar()
client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
home=client.open(base+'/').read().decode()
assert re.search(r'class="dial-payment-row".{0,1500}?data-dial-icon-kind="catalog"',home,re.S), 'upcoming icon unresolved'
assert re.search(r'class="overdue-subscriptions".{0,6000}?data-dial-icon-kind="catalog"',home,re.S), 'overdue icon unresolved'
assert re.search(r'class="cancellation-subscriptions".{0,6000}?data-dial-icon-kind="catalog"',home,re.S), 'cancellation icon unresolved'
subscriptions=client.open(base+'/subscriptions.php').read().decode()
assert subscriptions.count('data-dial-icon-kind="catalog"') >= 3, 'grouped cards unresolved'
fragment=client.open(base+'/endpoints/subscriptions/get.php').read().decode()
assert fragment.count('data-dial-icon-kind="catalog"') >= 3, 'AJAX cards unresolved'
print('Grouped, AJAX, upcoming, overdue, and cancellation icon rendering passed.')
PY

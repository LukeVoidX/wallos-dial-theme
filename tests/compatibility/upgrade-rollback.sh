#!/usr/bin/env bash
# Exercises disposable data only. Never accept an external database path.
set -euo pipefail
cd "$(dirname "$0")/../.."
candidate=${1:?Usage: upgrade-rollback.sh IMAGE}
old=ghcr.io/lukevoidx/wallos-dial:0.1.1
mkdir -p data
scratch=$(mktemp -d "$PWD/data/.dial-upgrade.XXXXXX")
name="dial-upgrade-$$"
trap 'docker container rm -f "$name" >/dev/null 2>&1 || true; rm -r "$scratch"' EXIT
mkdir -p "$scratch/db" "$scratch/logos"

start_image() {
  local image=$1
  docker run -d --name "$name" -e "PUID=$(id -u)" -e "PGID=$(id -g)" \
    -p 127.0.0.1::80 \
    -v "$scratch/db:/var/www/html/db" \
    -v "$scratch/logos:/var/www/html/images/uploads/logos" "$image" >/dev/null
  base="http://$(docker port "$name" 80/tcp | head -1)"
  for attempt in $(seq 1 45); do
    if curl -fsS "$base/health.php" >/dev/null 2>&1 &&
       docker logs "$name" 2>&1 | grep -F 'Updated next payment dates' >/dev/null; then
      return
    fi
    sleep 1
  done
  echo "Startup failed for $image" >&2
  docker logs "$name" >&2 || true
  exit 1
}
stop_image() { docker container rm -f "$name" >/dev/null; }

check_snapshot() {
  python3 - "$scratch/db/wallos.db" "$scratch/logos/demo-01.svg" "$scratch/baseline.json" <<'PY'
import hashlib, json, sqlite3, sys
from pathlib import Path
db = sqlite3.connect(sys.argv[1])
snapshot = {
    "subscriptions": db.execute(
        "SELECT id,name,category_id,logo,price,next_payment FROM subscriptions ORDER BY id"
    ).fetchall(),
    "categories": db.execute(
        'SELECT id,name,"order" FROM categories WHERE user_id=1 ORDER BY id'
    ).fetchall(),
    "settings": db.execute(
        "SELECT user_id,monthly_price,upcoming_payments_limit,convert_currency FROM settings WHERE user_id=1"
    ).fetchall(),
    "admin": db.execute(
        "SELECT id,login_disabled,registrations_open FROM admin WHERE id=1"
    ).fetchall(),
    "email_notifications": db.execute(
        "SELECT enabled,from_email,user_id FROM email_notifications WHERE user_id=1"
    ).fetchall(),
    "notification_settings": db.execute(
        "SELECT days,user_id FROM notification_settings WHERE user_id=1"
    ).fetchall(),
    "logo_sha256": hashlib.sha256(Path(sys.argv[2]).read_bytes()).hexdigest(),
}

assert db.execute("PRAGMA integrity_check").fetchone()[0] == "ok"
assert len(snapshot["subscriptions"]) == 30
baseline = Path(sys.argv[3])
if baseline.exists():
    before = json.loads(baseline.read_text())
    comparable = json.loads(json.dumps(snapshot))
    changed = [key for key in comparable if comparable[key] != before[key]]
    assert not changed, f"Wallos data changed across image switch: {changed}"
else:
    baseline.write_text(json.dumps(snapshot))
    db.backup(sqlite3.connect(baseline.with_suffix(".db")))
db.close()
PY
}
create_icon_state() {
  python3 - "$scratch/db/wallos.db" "theme/images/dial-icon/favicon-32.png" "$scratch/icon-state.json" <<'PY'
import hashlib, json, pathlib, sqlite3, sys
db = sqlite3.connect(sys.argv[1], timeout=5)
payload = pathlib.Path(sys.argv[2]).read_bytes()
sha = hashlib.sha256(payload).hexdigest()
subscription = db.execute("SELECT id,logo FROM subscriptions WHERE user_id=1 AND name='Cinema Club'").fetchone()
assert subscription and subscription[1], subscription
db.execute('BEGIN IMMEDIATE')
asset = db.execute('INSERT INTO dial_icon_assets(user_id,label,mime,bytes,sha256,width,height) VALUES(1,?,?,?,?,32,32)',
                   ('Synthetic test asset','image/png',payload,sha)).lastrowid
db.execute("INSERT INTO dial_subscription_icons(user_id,subscription_id,source,catalog_key,presentation,treatment,scale) VALUES(1,?,'catalog','chatgpt','plain','color',100)",
           (subscription[0],))
db.commit()
pathlib.Path(sys.argv[3]).write_text(json.dumps({'asset':asset,'sha256':sha,
    'subscription':subscription[0], 'original_logo':subscription[1]}))
db.close()
PY
}

check_icon_state() {
  python3 - "$scratch/db/wallos.db" "$scratch/icon-state.json" "$scratch/logos" <<'PY'
import hashlib, json, pathlib, sqlite3, sys
db = sqlite3.connect(sys.argv[1], timeout=5)
saved = json.loads(pathlib.Path(sys.argv[2]).read_text())
asset = db.execute('SELECT sha256,bytes FROM dial_icon_assets WHERE id=? AND user_id=1',
                   (saved['asset'],)).fetchone()
assert asset and asset[0] == saved['sha256'] and hashlib.sha256(asset[1]).hexdigest() == saved['sha256']
subscription = db.execute('SELECT logo FROM subscriptions WHERE id=? AND user_id=1',
                          (saved['subscription'],)).fetchone()
assert subscription and subscription[0] == saved['original_logo']
assert (pathlib.Path(sys.argv[3]) / subscription[0]).is_file()
preference = db.execute('SELECT source,catalog_key FROM dial_subscription_icons WHERE user_id=1 AND subscription_id=?',
                        (saved['subscription'],)).fetchone()
assert preference == ('catalog','chatgpt'), preference
assert db.execute('PRAGMA integrity_check').fetchone()[0] == 'ok'
db.close()
PY
}

# Let the historical image create and migrate the schema before seeding it.
start_image "$old"
stop_image
python3 demo/seed.py "$scratch/db/wallos.db" "$scratch/logos"
password_hash=$(docker run --rm --entrypoint php "$old" -r 'echo password_hash("DialFixture-2026!", PASSWORD_DEFAULT);')
python3 - "$scratch/db/wallos.db" "$password_hash" <<'PY'
import sqlite3, sys
db = sqlite3.connect(sys.argv[1])
db.execute("UPDATE user SET password=? WHERE id=1", (sys.argv[2],))
db.execute("UPDATE admin SET login_disabled=0 WHERE id=1")
db.execute("INSERT INTO email_notifications(enabled,from_email,user_id) VALUES(0,'fixture@example.invalid',1)")
db.execute("INSERT INTO notification_settings(days,user_id) VALUES(17,1)")
db.commit()
db.close()
PY
check_snapshot

start_image "$candidate"
create_icon_state
stop_image
check_snapshot
check_icon_state
start_image "$old"
curl -fsSL -c "$scratch/old.cookies" -b "$scratch/old.cookies" \
  --data-urlencode username=demo --data-urlencode 'password=DialFixture-2026!' \
  "$base/login.php" > "$scratch/old.html"
csrf=$(sed -n 's/.*window.csrfToken = "\([^"]*\)".*/\1/p' "$scratch/old.html" | head -1)
test -n "$csrf"
category=$(docker exec "$name" sqlite3 /var/www/html/db/wallos.db "SELECT id FROM categories WHERE name='AI' AND user_id=1")
payment=$(docker exec "$name" sqlite3 /var/www/html/db/wallos.db "SELECT id FROM payment_methods WHERE name='Demo Card' AND user_id=1")
post_subscription() {
  local record_name=$1 record_id=${2:-}
  curl -fsS -b "$scratch/old.cookies" -H "X-CSRF-Token: $csrf" \
    --data-urlencode "id=$record_id" --data-urlencode "name=$record_name" \
    --data-urlencode 'price=9.99' --data-urlencode 'currency_id=2' \
    --data-urlencode 'frequency=1' --data-urlencode 'cycle=3' \
    --data-urlencode 'next_payment=2026-11-15' \
    --data-urlencode 'start_date=2026-10-17' \
    --data-urlencode "payment_method_id=$payment" --data-urlencode 'payer_user_id=1' \
    --data-urlencode "category_id=$category" --data-urlencode 'notes=' \
    --data-urlencode 'url=' --data-urlencode 'logo-url=' \
    --data-urlencode 'notify_days_before=-1' --data-urlencode 'cancellation_date=' \
    --data-urlencode 'replacement_subscription_id=0' \
    "$base/endpoints/subscription/add.php"
}
add=$(post_subscription 'Temporary rollback record')
test "$(jq -r .status <<<"$add")" = Success
id=$(docker exec "$name" sqlite3 /var/www/html/db/wallos.db \
  "SELECT id FROM subscriptions WHERE name='Temporary rollback record' AND user_id=1")
test -n "$id"
python3 - "$scratch/db/wallos.db" "$id" <<'PY'
import sqlite3, sys
db = sqlite3.connect(sys.argv[1], timeout=5)
db.execute("INSERT INTO dial_subscription_icons(user_id,subscription_id,source,catalog_key,presentation,treatment,scale) VALUES(1,?,'catalog','chatgpt','plain','color',100)",
           (int(sys.argv[2]),))
db.commit()
db.close()
PY
edit=$(post_subscription 'Temporary rollback edited' "$id")
test "$(jq -r .status <<<"$edit")" = Success
test "$(docker exec "$name" sqlite3 /var/www/html/db/wallos.db \
  "SELECT COUNT(*) FROM subscriptions WHERE id=$id AND name='Temporary rollback edited'")" -eq 1
deleted=$(curl -fsS -b "$scratch/old.cookies" -H "X-CSRF-Token: $csrf" \
  -H 'Content-Type: application/json' -d "{\"id\":$id}" \
  "$base/endpoints/subscription/delete.php")
test "$(jq -r .success <<<"$deleted")" = true
test "$(docker exec "$name" sqlite3 /var/www/html/db/wallos.db \
  "SELECT COUNT(*) FROM dial_subscription_icons WHERE subscription_id=$id")" -eq 0
stop_image
check_snapshot
check_icon_state
start_image "$candidate"
stop_image
check_snapshot
check_icon_state
echo 'Disposable upgrade, old-image writes, rollback, and re-upgrade passed.'

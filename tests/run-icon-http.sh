#!/usr/bin/env bash
# Isolated, fictional-data HTTP fixture for the private icon library.
set -euo pipefail
cd "$(dirname "$0")/.."
image=${1:-wallos-dial:compat}
mode=${2:-}
mkdir -p data
scratch=$(mktemp -d "$PWD/data/.dial-icon-http.XXXXXX")
name="dial-icon-http-$$"
trap 'docker rm -f "$name" >/dev/null 2>&1 || true; rm -r "$scratch"' EXIT
mkdir -p "$scratch/db" "$scratch/logos"

start() {
  docker run -d --name "$name" -e "DEMO_MODE=$1" \
    -e "PUID=${DIAL_TEST_PUID:-$(id -u)}" -e "PGID=${DIAL_TEST_PGID:-$(id -g)}" \
    -p 127.0.0.1::80 \
    -v "$scratch/db:/var/www/html/db" \
    -v "$scratch/logos:/var/www/html/images/uploads/logos" "$image" >/dev/null
  address=$(docker port "$name" 80/tcp | head -1)
  for attempt in $(seq 1 50); do
    if curl -fsS "http://$address/health.php" >/dev/null 2>&1 &&
       docker logs "$name" 2>&1 | grep -F 'Updated next payment dates' >/dev/null; then
      return
    fi
    sleep 1
  done
  echo "Icon HTTP fixture failed to start" >&2
  docker logs "$name" >&2 || true
  exit 1
}

start 0
docker rm -f "$name" >/dev/null
if [[ "$mode" == --serve ]]; then
  python3 demo/seed.py "$scratch/db/wallos.db" "$scratch/logos" --showcase-icons
else
  python3 demo/seed.py "$scratch/db/wallos.db" "$scratch/logos"
fi
test_hash=$(docker run --rm --entrypoint php "$image" -r 'echo password_hash("dial-fixture-only", PASSWORD_DEFAULT);')
python3 - "$scratch/db/wallos.db" "$test_hash" <<'PY'
import sqlite3, sys
db = sqlite3.connect(sys.argv[1])
db.execute('UPDATE admin SET login_disabled=0, max_users=3 WHERE id=1')
db.execute('UPDATE user SET password=? WHERE id=1', (sys.argv[2],))
db.execute("INSERT INTO user (id,username,email,password,main_currency,firstname,language,budget) VALUES (2,'second','second@example.invalid',?,2,'Second','en',0)", (sys.argv[2],))
db.commit()
db.close()
PY

start 0
base_url="http://$address"
if [[ "$mode" == --serve ]]; then
  if [[ "${3:-}" != --fixture-out || -z "${4:-}" ]]; then
    echo 'Usage: run-icon-http.sh IMAGE --serve --fixture-out PATH' >&2
    exit 2
  fi
  python3 - "$4" "$base_url" "$scratch/db/wallos.db" <<'PY'
import json, pathlib, sys
path = pathlib.Path(sys.argv[1]); path.write_text(json.dumps({
    'base_url': sys.argv[2], 'database': sys.argv[3],
    'accounts': [
        {'role': 'owner', 'username': 'demo', 'password': 'dial-fixture-only'},
        {'role': 'secondary', 'username': 'second', 'password': 'dial-fixture-only'},
    ],
}))
path.chmod(0o600)
PY
  echo "Icon fixture: $base_url"
  while :; do sleep 2; done
fi
python3 tests/icon-http.py --base-url "$base_url" --database "$scratch/db/wallos.db"
docker rm -f "$name" >/dev/null
start 1
python3 tests/icon-http.py --base-url "http://$address" --database "$scratch/db/wallos.db" --demo
echo 'Icon HTTP ownership, CSRF, upload, no-store, and Demo checks passed.'

#!/usr/bin/env bash
# Run the example Caddy policy against an isolated, fictional Demo instance.
set -euo pipefail
cd "$(dirname "$0")/.."
image=${1:-wallos-dial:category}
site=${2:-demo/Caddyfile.site.example}
mkdir -p data
scratch=$(mktemp -d "$PWD/data/.dial-category-edge.XXXXXX")
network="dial-category-edge-$$"
app="dial-category-edge-app-$$"
edge="dial-category-edge-caddy-$$"
cleanup() {
  docker rm -f "$edge" "$app" >/dev/null 2>&1 || true
  docker network rm "$network" >/dev/null 2>&1 || true
  rm -rf "$scratch"
}
trap cleanup EXIT
mkdir -p "$scratch/db" "$scratch/logos"
docker network create "$network" >/dev/null

start_app() {
  local demo=$1
  docker run -d --name "$app" --network "$network" -e "DEMO_MODE=$demo" \
    -p 127.0.0.1::80 -v "$scratch/db:/var/www/html/db" \
    -v "$scratch/logos:/var/www/html/images/uploads/logos" "$image" >/dev/null
  origin=$(docker port "$app" 80/tcp | head -1)
  for attempt in $(seq 1 45); do
    if curl -fsS "http://$origin/health.php" >/dev/null 2>&1 &&
       docker logs "$app" 2>&1 | grep -Fq 'Updated next payment dates'; then return; fi
    sleep 1
  done
  docker logs "$app" >&2 || true
  echo 'Isolated Demo app failed to start' >&2
  exit 1
}

start_app 0
docker exec "$app" sh -c 'chmod a+rwX /var/www/html/db /var/www/html/db/wallos.db /var/www/html/images/uploads/logos'
docker rm -f "$app" >/dev/null
python3 demo/seed.py "$scratch/db/wallos.db" "$scratch/logos"
printf '<svg xmlns="http://www.w3.org/2000/svg"></svg>\n' > "$scratch/logos/private-mark.svg"
printf '<svg xmlns="http://www.w3.org/2000/svg"></svg>\n' > "$scratch/logos/demo-99.svg"
python3 - "$scratch/db/wallos.db" "theme/images/dial-icon/favicon-32.png" <<'PY'
import hashlib, pathlib, sqlite3, sys
payload = pathlib.Path(sys.argv[2]).read_bytes()
db = sqlite3.connect(sys.argv[1])
db.execute('INSERT INTO dial_icon_assets(user_id,label,mime,bytes,sha256,width,height) VALUES(1,?,?,?,?,32,32)',
           ('Fictional icon','image/png',payload,hashlib.sha256(payload).hexdigest()))
db.commit()
db.close()
PY
start_app 1

python3 - "$site" "$scratch/Caddyfile" "$app" <<'PY'
from pathlib import Path
import sys
source, target, app = Path(sys.argv[1]), Path(sys.argv[2]), sys.argv[3]
lines, skipping = [], False
for line in source.read_text().splitlines():
    stripped = line.strip()
    if stripped.startswith('@not_cloudflare {'):
        skipping = True
        continue
    if skipping:
        if stripped == '}': skipping = False
        continue
    if stripped.startswith('import ') or stripped == 'respond @not_cloudflare 403':
        continue
    if stripped == 'wallos-demo.mostai.org {': line = ':80 {'
    line = line.replace('reverse_proxy 127.0.0.1:8296', f'reverse_proxy {app}:80')
    lines.append(line)
target.write_text('\n'.join(lines) + '\n')
PY

docker run -d --name "$edge" --network "$network" -p 127.0.0.1::80 \
  -v "$scratch/Caddyfile:/etc/caddy/Caddyfile:ro" caddy:2-alpine >/dev/null
edge_address=$(docker port "$edge" 80/tcp | head -1)
for attempt in $(seq 1 30); do
  if curl -fsS "http://$edge_address/health.php" >/dev/null 2>&1; then break; fi
  sleep 1
done
bash demo/smoke.sh "http://$origin/" "http://$edge_address/"
curl -fsSL --cookie-jar "$scratch/edge-icon-cookies" --cookie "$scratch/edge-icon-cookies" \
  "http://$edge_address/" >/dev/null
for method in GET POST; do
  for endpoint in icons.php 'icon.php?id=1'; do
    request=(curl -sS -o /dev/null -w '%{http_code}' --cookie "$scratch/edge-icon-cookies")
    if [[ "$method" == POST ]]; then request+=(--data 'action=delete&id=1'); fi
    status=$("${request[@]}" "http://$edge_address/endpoints/dial/$endpoint")
    [[ "$status" == 404 ]] || { echo "Demo edge exposed My Icons $method $endpoint ($status)" >&2; exit 1; }
  done
done
curl -fsSL "http://$edge_address/images/dial-catalog/chatgpt.svg" > "$scratch/catalog.svg"
grep -Fq '<svg' "$scratch/catalog.svg" || { echo 'Demo edge blocked a public catalog pictogram' >&2; exit 1; }
curl -fsSL "http://$edge_address/images/uploads/logos/demo-01.svg" > "$scratch/demo-logo.svg"
grep -Fq '<svg' "$scratch/demo-logo.svg" || { echo 'Demo edge blocked a synthetic service mark' >&2; exit 1; }
status=$(curl -sS -o /dev/null -w '%{http_code}' "http://$edge_address/images/uploads/logos/private-mark.svg")
[[ "$status" == 404 ]] || { echo "Demo edge exposed a non-synthetic uploaded Logo ($status)" >&2; exit 1; }
status=$(curl -sS -o /dev/null -w '%{http_code}' "http://$edge_address/images/uploads/logos/demo-99.svg")
[[ "$status" == 404 ]] || { echo "Demo edge exposed an unseeded Demo-looking Logo ($status)" >&2; exit 1; }
status=$(curl -sS -o /dev/null -w '%{http_code}' --data 'subscription_id=1&dial_icon_source=original' \
  "http://$edge_address/endpoints/dial/subscription_icon.php")
[[ "$status" == 404 ]] || { echo "Demo edge allowed quick icon write ($status)" >&2; exit 1; }
echo 'Isolated Demo Caddy policy passed.'

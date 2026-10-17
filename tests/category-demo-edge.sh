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
echo 'Isolated Demo Caddy policy passed.'

#!/usr/bin/env bash
set -euo pipefail
image=${1:-wallos-dial:0.1.0}
name="dial-smoke-$$"
cleanup() { docker rm -f "$name" >/dev/null 2>&1 || true; }
trap cleanup EXIT

docker run -d --name "$name" -p 127.0.0.1::80 "$image" >/dev/null
address=$(docker port "$name" 80/tcp | head -n 1)
for attempt in $(seq 1 40); do
  if curl -fsS "http://$address/health.php" >/dev/null 2>&1; then break; fi
  sleep 1
done
curl -fsS "http://$address/health.php" >/dev/null
css=$(curl -fsS "http://$address/styles/dial.css")
script=$(curl -fsS "http://$address/scripts/dial-interaction.js")
[[ "$css" == *wallos-dial* ]]
[[ "$script" == *dial-timeline* ]]
printf 'Runtime smoke passed: health and themed assets.\n'

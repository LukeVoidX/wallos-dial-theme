#!/usr/bin/env bash
set -euo pipefail

# Restore only an explicitly marked, isolated demo volume. The synthetic
# baseline must be a migrated Wallos 5.8.1 database with zero users.
root=${1:?Usage: reset.sh /absolute/path/to/demo-root}
root=$(cd "$root" && pwd -P)
container=${WALLOS_DEMO_CONTAINER:-wallos-dial-public-demo}
health_url=${WALLOS_DEMO_HEALTH_URL:-http://127.0.0.1:8296/health.php}
seed_script=$(cd "$(dirname "$0")" && pwd -P)/seed.py

[[ -f "$root/.wallos-dial-demo-only" ]] || { echo 'Missing demo-only marker' >&2; exit 1; }
[[ -f "$root/template/wallos.db" ]] || { echo 'Missing clean migrated template' >&2; exit 1; }
[[ -d "$root/db" && -d "$root/logos" ]] || { echo 'Missing isolated runtime directories' >&2; exit 1; }
mounts=$(docker inspect --format '{{range .Mounts}}{{.Source}}:{{.Destination}}{{println}}{{end}}' "$container")
grep -Fxq "$root/db:/var/www/html/db" <<< "$mounts" || { echo 'Container DB mount is not the marked demo directory' >&2; exit 1; }
grep -Fxq "$root/logos:/var/www/html/images/uploads/logos" <<< "$mounts" || { echo 'Container logo mount is not the marked demo directory' >&2; exit 1; }

scratch=$(mktemp -d "$root/.reset.XXXXXX")
trap 'rm -rf "$scratch"' EXIT
cp "$root/template/wallos.db" "$scratch/wallos.db"
python3 "$seed_script" "$scratch/wallos.db" "$scratch/logos"

docker stop --time 15 "$container" >/dev/null
mv "$root/db/wallos.db" "$scratch/previous.db"
mv "$scratch/wallos.db" "$root/db/wallos.db"
cp "$scratch"/logos/demo-*.svg "$root/logos/"
if ! docker start "$container" >/dev/null; then
    mv "$scratch/previous.db" "$root/db/wallos.db"
    docker start "$container" >/dev/null || true
    echo 'Demo restart failed; previous synthetic database restored' >&2
    exit 1
fi
for attempt in {1..25}; do
    if curl -fsS "$health_url" >/dev/null 2>&1; then
        echo 'Isolated demo reset and health check passed.'
        exit 0
    fi
    sleep 1
done
docker stop --time 15 "$container" >/dev/null || true
mv "$scratch/previous.db" "$root/db/wallos.db"
docker start "$container" >/dev/null || true
echo 'Demo health check failed; previous synthetic database restored' >&2
exit 1

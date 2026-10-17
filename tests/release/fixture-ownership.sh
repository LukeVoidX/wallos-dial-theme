#!/usr/bin/env bash
# Prove a non-root Linux runner can seed and remove its disposable bind mounts.
set -euo pipefail
cd "$(dirname "$0")/../.."
image=${1:?Usage: fixture-ownership.sh LOCAL_IMAGE_TAG}
mkdir -p data
scratch=$(mktemp -d "$PWD/data/.dial-fixture-ownership.XXXXXX")
name="dial-fixture-ownership-$$"
cleanup() {
  docker rm -f "$name" >/dev/null 2>&1 || true
  rm -rf "$scratch"
}
trap cleanup EXIT
mkdir -p "$scratch/db" "$scratch/logos"

docker run -d --name "$name" -e "PUID=$(id -u)" -e "PGID=$(id -g)" \
  -v "$scratch/db:/var/www/html/db" \
  -v "$scratch/logos:/var/www/html/images/uploads/logos" "$image" >/dev/null
ready=0
for attempt in $(seq 1 60); do
  if docker exec "$name" curl -fsS http://127.0.0.1/health.php >/dev/null 2>&1 &&
     docker logs "$name" 2>&1 | grep -Fq 'Updated next payment dates'; then
    ready=1
    break
  fi
  sleep 1
done
if [[ "$ready" -ne 1 ]]; then
  docker logs "$name" >&2 || true
  echo 'Disposable ownership fixture did not start' >&2
  exit 1
fi

# Wallos itself creates avatars/ on startup. The host runner must be able to
# write inside that nested path, write SQLite, and remove the entire fixture.
python3 - "$scratch/db/wallos.db" "$scratch/logos/avatars/host-write.svg" <<'PY'
import sqlite3, sys
from pathlib import Path
db = sqlite3.connect(sys.argv[1], timeout=5)
db.execute('CREATE TABLE dial_fixture_ownership(id INTEGER)')
db.execute('DROP TABLE dial_fixture_ownership')
db.commit()
db.close()
Path(sys.argv[2]).write_text('<svg xmlns="http://www.w3.org/2000/svg"/>\n')
PY
docker rm -f "$name" >/dev/null
rm -r "$scratch"
trap - EXIT
test ! -e "$scratch"
echo 'Disposable bind-mount host ownership and cleanup passed.'

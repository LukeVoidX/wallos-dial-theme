#!/usr/bin/env bash
set -euo pipefail

# Restore only an explicitly marked, isolated demo volume. The synthetic
# baseline must be a migrated database for the selected Dial image with zero users.
root=${1:?Usage: reset.sh /absolute/path/to/demo-root}
root=$(cd "$root" && pwd -P)
container=${WALLOS_DEMO_CONTAINER:-wallos-dial-public-demo}
expected_image=${WALLOS_DEMO_IMAGE:?Set WALLOS_DEMO_IMAGE to the exact Demo image before reset}
public_url=${WALLOS_DEMO_PUBLIC_URL:?Set WALLOS_DEMO_PUBLIC_URL to the HTTPS public Demo URL before reset}
health_url=${WALLOS_DEMO_HEALTH_URL:-http://127.0.0.1:8296/health.php}
seed_script=$(cd "$(dirname "$0")" && pwd -P)/seed.py
smoke_script=$(cd "$(dirname "$0")" && pwd -P)/smoke.sh

[[ -f "$root/.wallos-dial-demo-only" ]] || { echo 'Missing demo-only marker' >&2; exit 1; }
[[ -f "$root/template/wallos.db" ]] || { echo 'Missing clean migrated template' >&2; exit 1; }
[[ -d "$root/db" && -d "$root/logos" ]] || { echo 'Missing isolated runtime directories' >&2; exit 1; }
mounts=$(docker inspect --format '{{range .Mounts}}{{.Source}}:{{.Destination}}{{println}}{{end}}' "$container")
grep -Fxq "$root/db:/var/www/html/db" <<< "$mounts" || { echo 'Container DB mount is not the marked demo directory' >&2; exit 1; }
grep -Fxq "$root/logos:/var/www/html/images/uploads/logos" <<< "$mounts" || { echo 'Container logo mount is not the marked demo directory' >&2; exit 1; }
container_env=$(docker inspect --format '{{range .Config.Env}}{{println .}}{{end}}' "$container")
grep -Fxq 'DEMO_MODE=1' <<< "$container_env" || { echo 'Container must run with DEMO_MODE=1' >&2; exit 1; }
running_image=$(docker inspect --format '{{.Config.Image}}' "$container")
[[ "$running_image" == "$expected_image" ]] || {
    echo "Demo image mismatch: expected $expected_image; found $running_image" >&2
    exit 1
}
[[ "$public_url" == https://* ]] || { echo 'Public Demo target must use HTTPS' >&2; exit 1; }

scratch=$(mktemp -d "$root/.reset.XXXXXX")
stopped=0
logos_mutated=0
cleanup() {
    local status=$? preserve=0
    trap - EXIT
    set +e
    if [[ "$status" -ne 0 && "$stopped" -eq 1 ]]; then
        docker stop --time 15 "$container" >/dev/null 2>&1 || true
        if [[ -f "$scratch/previous.db" ]]; then
            if ! mv -f "$scratch/previous.db" "$root/db/wallos.db"; then
                echo "Demo DB rollback failed; recover $scratch/previous.db manually" >&2
                preserve=1
            fi
        fi
        if [[ "$logos_mutated" -eq 1 ]]; then
            if ! find "$root/logos" -maxdepth 1 -name 'demo-*.svg' -type f -delete ||
               ! cp -a "$scratch/previous-logos/." "$root/logos/"; then
                echo "Demo Logo rollback failed; recover $scratch/previous-logos manually" >&2
                preserve=1
            fi
        fi
        if ! docker start "$container" >/dev/null; then
            echo 'Demo container restart failed after reset error' >&2
        else
            echo 'Demo reset failed; previous synthetic database and Logos restored where available' >&2
        fi
    fi
    if [[ "$preserve" -eq 0 ]]; then rm -rf "$scratch"; fi
    exit "$status"
}
trap cleanup EXIT
mkdir "$scratch/previous-logos"
cp -a "$root/logos/." "$scratch/previous-logos/"
cp "$root/template/wallos.db" "$scratch/wallos.db"
python3 "$seed_script" "$scratch/wallos.db" "$scratch/logos" --showcase-icons

stopped=1
docker stop --time 15 "$container" >/dev/null
mv "$root/db/wallos.db" "$scratch/previous.db"
mv "$scratch/wallos.db" "$root/db/wallos.db"
logos_mutated=1
cp "$scratch"/logos/demo-*.svg "$root/logos/"
if ! docker start "$container" >/dev/null; then
    echo 'Demo restart failed after database swap' >&2
    exit 1
fi
for attempt in {1..25}; do
    if curl -fsS "$health_url" >/dev/null 2>&1 &&
       bash "$smoke_script" "${health_url%/health.php}/" '' "$public_url" >/dev/null 2>&1; then
        echo 'Isolated demo reset, health, read-only, 30-record, and public HTTPS checks passed.'
        exit 0
    fi
    sleep 1
done
echo 'Demo health or public HTTPS smoke check failed' >&2
exit 1

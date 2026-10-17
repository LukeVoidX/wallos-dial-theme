#!/usr/bin/env bash
# One local, nonpublishing gate for CI and release-tag preflight.
set -euo pipefail
cd "$(dirname "$0")/.."
image=${1:?Usage: verify-1.0-candidate.sh LOCAL_IMAGE_TAG}

for command in docker node python3 curl jq; do
  command -v "$command" >/dev/null || {
    echo "Required test command is unavailable: $command" >&2
    exit 1
  }
done
if docker compose version >/dev/null 2>&1; then
  compose=(docker compose)
else
  command -v docker-compose >/dev/null || {
    echo 'Docker Compose is unavailable' >&2
    exit 1
  }
  compose=(docker-compose)
fi

./scripts/check-package.sh
bash tests/release/version-contract.sh
bash tests/release/ci-contract.sh
python3 tests/release/demo-seed-icons.py
python3 tests/release/docs-contract.py
python3 tests/demo-reset-safety.py
python3 tests/demo-smoke-safety.py
"${compose[@]}" config -q
"${compose[@]}" -f compose.build.yaml config -q

while IFS= read -r -d '' file; do
  node --check "$file"
done < <(find theme -type f -name '*.js' -print0)

docker build -t "$image" .
bash tests/release/version-contract.sh "$image"

# The image supplies PHP and its extensions; inspect the entire mounted overlay,
# including new files that have not yet been added to the package checklist.
docker run --rm --entrypoint sh -v "$PWD:/app:ro" -w /app "$image" -ec '
  find theme -type f -name "*.php" -print | while IFS= read -r file; do
    php -l "/app/$file" >/dev/null
  done
'
./scripts/smoke-image.sh "$image"

# All SMTP routes and local webhook receipts use a Docker --internal network,
# fictional recipients, and a pinned Mailpit image. No external mail is sent.
for locale in en zh_cn zh_tw; do
  DIAL_MAIL_QA_LANGUAGE="$locale" python3 tests/release/mail-capture.py "$image"
done

for case in tests/mail-theme.php tests/mail-mime.php tests/category-core.php \
  tests/icon-catalog.php tests/icon-schema.php tests/icon-resolver.php \
  tests/icon-normalize.php tests/icon-atomic.php tests/logo-limits.php; do
  docker run --rm --user 82:82 --entrypoint php -v "$PWD:/app:ro" \
    -v "$PWD/tests/fixtures/legacy-logos:/app/theme/images/dial-logos:ro" \
    -w /app "$image" \
    -d zend.assertions=1 "$case"
done

tests/compatibility/upstream-security.sh "$image"
tests/compatibility/security-http.sh "$image"
tests/compatibility/service-worker.sh "$image"
tests/compatibility/upgrade-rollback.sh "$image"
tests/compatibility/hash-gate.sh
tests/category-http.sh "$image"
tests/category-demo-edge.sh "$image"
tests/run-icon-http.sh "$image"
tests/icon-render-http.sh "$image"
bash tests/browser/run.sh "$image"

echo "Complete local Dial candidate gate passed: $image"

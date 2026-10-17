#!/usr/bin/env bash
# Full-browser acceptance against an isolated, fictional Wallos fixture.
set -euo pipefail
cd "$(dirname "$0")/../.."
image=${1:-wallos-dial:compat}
mkdir -p data
scratch=$(mktemp -d "$PWD/data/.dial-release-browser.XXXXXX")
fixture="$scratch/fixture.json"
output=${DIAL_QA_OUTPUT:-"$PWD/data/qa-1.0"}
mkdir -p "$output"

tests/run-icon-http.sh "$image" --serve --fixture-out "$fixture" >"$scratch/fixture.log" 2>&1 &
fixture_pid=$!
cleanup() {
  local result=$?
  if test "$result" -ne 0; then
    echo 'Synthetic fixture application log:' >&2
    docker logs "dial-icon-http-$fixture_pid" 2>&1 | tail -n 80 >&2 || true
    echo 'Synthetic fixture runner log:' >&2
    tail -n 40 "$scratch/fixture.log" >&2 || true
  fi
  kill "$fixture_pid" 2>/dev/null || true
  wait "$fixture_pid" 2>/dev/null || true
  rm -r "$scratch"
}
trap cleanup EXIT
for attempt in $(seq 1 80); do
  if test -s "$fixture"; then break; fi
  if ! kill -0 "$fixture_pid" 2>/dev/null; then
    cat "$scratch/fixture.log" >&2
    echo 'Synthetic browser fixture stopped before producing credentials.' >&2
    exit 1
  fi
  sleep 0.25
done
if ! test -s "$fixture"; then
  cat "$scratch/fixture.log" >&2
  echo 'Synthetic browser fixture timed out.' >&2
  exit 1
fi

npm ci --prefix tests/browser --no-audit --no-fund
if test "$(uname -s)" = Linux; then
  tests/browser/node_modules/.bin/playwright install --with-deps chromium chrome
  browser_channel=chromium
else
  # The macOS development host already has Chrome; the locked Playwright API
  # drives that browser. CI uses its own pinned Chromium download above.
  browser_channel=chrome
fi
DIAL_QA_IMAGE="$image" DIAL_BROWSER_CHANNEL="$browser_channel" \
  node tests/browser/release-journey.mjs "$fixture" "$output"
NODE_PATH="$PWD/tests/browser/node_modules" DIAL_BROWSER_CHANNEL="$browser_channel" \
  node tests/category-browser.cjs "$image"
NODE_PATH="$PWD/tests/browser/node_modules" DIAL_BROWSER_CHANNEL="$browser_channel" \
  node tests/stats-legend-browser.cjs "$image"
NODE_PATH="$PWD/tests/browser/node_modules" node tests/icon-chooser-browser.cjs "$image"
NODE_PATH="$PWD/tests/browser/node_modules" node tests/icon-detail-browser.cjs "$image"
NODE_PATH="$PWD/tests/browser/node_modules" node tests/icon-cache-browser.cjs "$image"
node tests/browser/first-run-readiness.mjs "$image"
DIAL_BROWSER_CHANNEL="$browser_channel" node tests/browser/auth-first-run.mjs "$image"
printf 'Synthetic browser evidence: %s\n' "$output"

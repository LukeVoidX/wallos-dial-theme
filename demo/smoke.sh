#!/usr/bin/env bash
set -euo pipefail

# Exercise the public demo's actual anonymous login path, not just health.php.
url=${1:-http://127.0.0.1:8296/}
scratch=$(mktemp -d)
trap 'rm -rf "$scratch"' EXIT

curl -fsSL --max-time 15 --max-redirs 4 \
    --cookie-jar "$scratch/cookies" --cookie "$scratch/cookies" \
    "${url%/}/" > "$scratch/page"
grep -Fq 'class="demo-banner"' "$scratch/page" || {
    echo 'Demo dashboard did not render for an anonymous visitor' >&2
    exit 1
}

# PHP sessions can expire while a visitor still has Wallos's 30-day cookie.
# The demo must recover that session after each scheduled database reset.
curl -fsSL --max-time 15 --max-redirs 4 \
    --cookie-jar "$scratch/remember-cookies" \
    --cookie 'wallos_login=demo%7Cabc123ABC%7C2' \
    "${url%/}/" > "$scratch/remember-page"
grep -Fq 'class="demo-banner"' "$scratch/remember-page" || {
    echo 'Demo dashboard did not recover an expired session' >&2
    exit 1
}
echo 'Anonymous demo dashboard smoke check passed.'

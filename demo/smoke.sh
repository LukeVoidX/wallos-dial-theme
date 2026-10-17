#!/usr/bin/env bash
set -euo pipefail

# Exercise the public demo's actual anonymous login path, not just health.php.
url=${1:-http://127.0.0.1:8296/}
edge_url=${2:-}
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

if [[ -n "$edge_url" ]]; then
    curl -fsSL --max-time 15 --max-redirs 4 \
        --cookie-jar "$scratch/edge-cookies" --cookie "$scratch/edge-cookies" \
        "${edge_url%/}/" > "$scratch/edge-home"
    curl -fsSL --max-time 15 --max-redirs 4 \
        --cookie-jar "$scratch/edge-cookies" --cookie "$scratch/edge-cookies" \
        "${edge_url%/}/categories.php" > "$scratch/edge-categories"
    grep -Fq 'data-testid="dial-category-readonly"' "$scratch/edge-categories" || {
        echo 'Demo edge did not render the read-only category manager' >&2
        exit 1
    }
    for endpoint in category.php dial_manage.php; do
        status=$(curl -sS --max-time 15 -o "$scratch/edge-post" -w '%{http_code}' \
            --data 'action=add&name=Denied' "${edge_url%/}/endpoints/categories/$endpoint")
        [[ "$status" == 404 ]] || {
            echo "Demo edge returned $status for category mutation $endpoint (expected 404)" >&2
            exit 1
        }
    done
    echo 'Read-only demo edge category checks passed.'
fi

#!/usr/bin/env bash
set -euo pipefail

# URL 1 is the isolated origin, URL 2 is an optional local read-only edge,
# URL 3 is the actual public HTTPS hostname. A reset must supply URL 3.
origin_url=${1:-http://127.0.0.1:8296/}
edge_url=${2:-}
public_url=${3:-}
scratch=$(mktemp -d)
trap 'rm -rf "$scratch"' EXIT

if [[ -n "$public_url" && "$public_url" != https://* ]]; then
    echo 'Public Demo target must use HTTPS' >&2
    exit 1
fi

check_subscription_count() {
    local page=$1 label=$2
    python3 - "$page" "$label" <<'PY'
import re, sys
from pathlib import Path
ids = re.findall(r'data-testid="dial-subscription-(\d+)"', Path(sys.argv[1]).read_text())
if len(ids) != 30 or len(set(ids)) != 30:
    raise SystemExit(f'{sys.argv[2]} did not render 30 fictional subscriptions ({len(ids)} rows, {len(set(ids))} unique)')
PY
}

probe_demo() {
    local base=$1 label=$2 require_https=$3 check_edge=$4
    local prefix="$scratch/${label//[^a-zA-Z0-9]/-}"
    local cookies="$prefix-cookies"
    local -a tls=(--globoff)
    if [[ "$require_https" == 1 ]]; then
        tls+=(--proto '=https' --proto-redir '=https')
    fi
    if ! curl -fsSL --max-time 15 --max-redirs 4 "${tls[@]}" \
        --cookie-jar "$cookies" --cookie "$cookies" "${base%/}/" > "$prefix-home"; then
        echo "$label Demo dashboard failed" >&2
        return 1
    fi
    grep -Fq 'class="demo-banner"' "$prefix-home" || {
        echo "$label Demo dashboard did not render for an anonymous visitor" >&2
        return 1
    }
    curl -fsSL --max-time 15 --max-redirs 4 "${tls[@]}" \
        --cookie-jar "$cookies" --cookie "$cookies" \
        "${base%/}/subscriptions.php" > "$prefix-subscriptions"
    check_subscription_count "$prefix-subscriptions" "$label Demo"

    if [[ "$check_edge" != 1 ]]; then return 0; fi
    for page in categories.php calendar.php stats.php; do
        curl -fsSL --max-time 15 --max-redirs 4 "${tls[@]}" \
            --cookie-jar "$cookies" --cookie "$cookies" \
            "${base%/}/$page" > "$prefix-$page"
    done
    grep -Fq 'data-testid="dial-category-readonly"' "$prefix-categories.php" || {
        echo "$label Demo category manager is not read-only" >&2
        return 1
    }
    curl -fsSL --max-time 15 "${tls[@]}" --cookie-jar "$cookies" --cookie "$cookies" \
        "${base%/}/service-worker.php" > "$prefix-worker"
    grep -Fq 'static-cache-v5.8.3-dial-' "$prefix-worker" || {
        echo "$label Demo did not serve the Dial Service Worker" >&2
        return 1
    }
    curl -fsSL --max-time 15 "${tls[@]}" \
        "${base%/}/images/dial-catalog/chatgpt.svg" > "$prefix-catalog"
    grep -Fq '<svg' "$prefix-catalog" || {
        echo "$label Demo did not serve a public catalog pictogram" >&2
        return 1
    }
    for path in \
        endpoints/dial/icons.php \
        'endpoints/dial/icon.php?id=1' \
        admin.php db/wallos.db images/uploads/logos/private-mark.svg; do
        status=$(curl -sS --max-time 15 "${tls[@]}" \
            --cookie-jar "$cookies" --cookie "$cookies" -o /dev/null -w '%{http_code}' \
            "${base%/}/$path")
        [[ "$status" == 404 ]] || {
            echo "$label Demo exposed My Icons or a private path: $path ($status)" >&2
            return 1
        }
    done
    for path in \
        endpoints/categories/category.php endpoints/categories/dial_manage.php \
        endpoints/dial/icons.php endpoints/dial/subscription_icon.php \
        endpoints/subscription/add.php endpoints/subscription/clone.php; do
        status=$(curl -sS --max-time 15 "${tls[@]}" \
            --cookie-jar "$cookies" --cookie "$cookies" -o /dev/null -w '%{http_code}' \
            --data 'action=add&name=Denied' "${base%/}/$path")
        [[ "$status" == 404 ]] || {
            echo "$label Demo allowed a mutation: $path ($status)" >&2
            return 1
        }
    done
    echo "$label read-only, 30-record, and static catalog checks passed."
}

probe_demo "$origin_url" 'Origin' 0 0

# PHP sessions can expire while visitors still hold Wallos's 30-day cookie.
curl -fsSL --max-time 15 --max-redirs 4 \
    --cookie-jar "$scratch/remember-cookies" \
    --cookie 'wallos_login=demo%7Cabc123ABC%7C2' \
    "${origin_url%/}/" > "$scratch/remember-page"
grep -Fq 'class="demo-banner"' "$scratch/remember-page" || {
    echo 'Demo dashboard did not recover an expired session' >&2
    exit 1
}

if [[ -n "$edge_url" ]]; then probe_demo "$edge_url" 'Edge' 0 1; fi
if [[ -n "$public_url" ]]; then probe_demo "$public_url" 'Public HTTPS' 1 1; fi
echo 'Anonymous Demo and session recovery checks passed.'

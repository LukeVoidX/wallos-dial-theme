#!/usr/bin/env bash
set -euo pipefail

image=${1:?pass a local image tag}
cd "$(dirname "$0")/../.."
version=$(cat VERSION)
grep -Fq "const DIAL_VERSION = '$version';" theme/includes/dial_version.php
worker=$(docker run --rm --entrypoint php "$image" /var/www/html/service-worker.php)
grep -Fq "const STATIC_CACHE = 'static-cache-v5.8.3-dial-$version';" <<<"$worker"
grep -Fq 'return cache.match(request, { ignoreSearch: true })' <<<"$worker"
for asset in styles/dial.css scripts/dial-interaction.js scripts/dial-categories.js scripts/dial-category-refresh.js scripts/dial-language.js scripts/stats-dial.js scripts/subscriptions.js; do
  test "$(grep -Fc "'$asset'" <<<"$worker")" -eq 1
done
grep -F -A3 "url.pathname.endsWith('/endpoints/dial/icon.php')" <<<"$worker" |
  grep -Fq 'event.respondWith(fetch(request));'
legacy=$(docker run --rm --entrypoint cat "$image" /var/www/html/service-worker.js)
grep -Fq "importScripts('service-worker.php');" <<<"$legacy"
echo 'Dial worker cache and private-icon bypass verified.'

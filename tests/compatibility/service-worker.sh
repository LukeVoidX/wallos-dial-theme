#!/usr/bin/env bash
set -euo pipefail

image=${1:?pass a local image tag}
cd "$(dirname "$0")/../.."
version=$(cat VERSION)
grep -Fq "const DIAL_VERSION = '$version';" theme/includes/dial_version.php
worker=$(docker run --rm --entrypoint php "$image" /var/www/html/service-worker.php)
grep -Fq "const STATIC_CACHE = 'static-cache-v5.8.3-dial-$version';" <<<"$worker"
grep -Fq 'return cache.match(request, { ignoreSearch: true })' <<<"$worker"
for asset in styles/dial.css scripts/dial-interaction.js scripts/dial-categories.js scripts/dial-category-refresh.js scripts/dial-language.js scripts/dial-icon-chooser.js scripts/stats-dial.js scripts/subscriptions.js images/dial-catalog/apple-one.svg images/dial-catalog/chatgpt.svg images/dial-catalog/claude.svg images/dial-catalog/jianying.svg images/dial-catalog/netcup.svg images/dial-catalog/nmcloud.svg images/dial-catalog/proxy-seller.svg images/dial-catalog/racknerd.svg images/dial-catalog/vmiss.svg; do
  test "$(grep -Fc "'$asset'" <<<"$worker")" -eq 1
done
grep -F -A5 "url.pathname.endsWith('/endpoints/dial/icon.php')" <<<"$worker" |
  grep -Fq 'event.respondWith(fetch(request));'
grep -Fq 'const validCaches = [STATIC_CACHE];' <<<"$worker"
if grep -Fq 'PREFETCH_PAGES' <<<"$worker"; then
  echo 'The worker still prefetches account pages' >&2
  exit 1
fi
if grep -Fq 'caches.open(PAGES_CACHE)' <<<"$worker"; then
  echo 'The worker still caches account pages' >&2
  exit 1
fi
grep -Fq "url.pathname.endsWith('/endpoints/dial/icons.php')" <<<"$worker"
grep -Fq "url.pathname.endsWith('/endpoints/subscription/get.php')" <<<"$worker"
grep -Fq "url.pathname.endsWith('/endpoints/subscriptions/get.php')" <<<"$worker"
legacy=$(docker run --rm --entrypoint cat "$image" /var/www/html/service-worker.js)
grep -Fq "importScripts('service-worker.php');" <<<"$legacy"
echo 'Dial worker cache and private-icon bypass verified.'

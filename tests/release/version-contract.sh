#!/usr/bin/env bash
# Published-source contract. The Demo Compose default follows the verified
# 1.0.0 image only after the separate public Demo switch has passed.
set -euo pipefail
cd "$(dirname "$0")/../.."

test "$(cat VERSION)" = 1.0.0
test "$(cat WALLOS_VERSION)" = 5.8.3
grep -Fq 'org.opencontainers.image.version="1.0.0"' Dockerfile
grep -Fq "apk add --no-cache 'rsvg-convert=2.62.3-r0'" Dockerfile
grep -Fq "DIAL_VERSION = '1.0.0'" theme/includes/dial_version.php
grep -Fq 'ghcr.io/lukevoidx/wallos-dial:1.0.0' compose.yaml
grep -Fq 'wallos-dial:1.0.0' compose.build.yaml
grep -Fq 'WALLOS_DEMO_IMAGE:-ghcr.io/lukevoidx/wallos-dial:1.0.0' demo/compose.yaml
grep -Fq '# Wallos Dial 1.0.0' docs/RELEASE-NOTES-1.0.0.md

if test "$#" -gt 0; then
  actual=$(docker image inspect "$1" --format '{{index .Config.Labels "org.opencontainers.image.version"}}')
  test "$actual" = 1.0.0
  docker run --rm --entrypoint sh "$1" -ec \
    "apk list --installed 'rsvg-convert' | grep -Eq '^rsvg-convert-2[.]62[.]3-r0 '"
fi
echo 'Stable source version and public Demo default are aligned at 1.0.0.'

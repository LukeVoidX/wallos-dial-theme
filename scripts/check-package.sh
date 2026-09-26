#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

required=(VERSION Dockerfile compose.example.yaml LICENSE.md README.md theme/index.php theme/subscriptions.php theme/calendar.php theme/stats.php theme/includes/header.php theme/includes/dial_logo_map.php theme/styles/dial.css theme/scripts/stats-dial.js theme/scripts/dial-interaction.js)
for file in "${required[@]}"; do
  [[ -f "$file" ]] || { echo "Missing: $file" >&2; exit 1; }
done

version=$(tr -d '\n' < VERSION)
grep -Fq "org.opencontainers.image.version=\"$version\"" Dockerfile || { echo 'Dockerfile version differs from VERSION' >&2; exit 1; }
grep -Fq "wallos-dial:$version" compose.example.yaml || { echo 'Compose tag differs from VERSION' >&2; exit 1; }

if command -v node >/dev/null; then
  while IFS= read -r -d '' file; do node --check "$file"; done < <(find theme -name '*.js' -print0)
else
  echo 'Node.js missing: JavaScript syntax check skipped' >&2
fi
if command -v php >/dev/null; then
  while IFS= read -r -d '' file; do php -l "$file" >/dev/null; done < <(find theme -name '*.php' -print0)
else
  echo 'PHP missing: use the Docker image for PHP syntax verification' >&2
fi

if find . -type f \( -name '*.db' -o -name '*.sqlite' -o -name '.env' -o -name '*.key' -o -name '*.pem' \) -print | grep -q .; then
  echo 'Local database, environment, or key file found in package' >&2
  exit 1
fi
if grep -RIEq 'jarvishub|lukevoidx|dolphtek|withluke|mostai|mostrahub|erenship|nmcloud|proxy-seller' theme config; then
  echo 'Account-specific mapping found in public package' >&2
  exit 1
fi

echo 'Package checks passed.'

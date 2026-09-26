#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

required=(VERSION WALLOS_VERSION Dockerfile compose.yaml compose.build.yaml compat/upstream-files.sha256 LICENSE.md README.md theme/index.php theme/subscriptions.php theme/calendar.php theme/stats.php theme/includes/header.php theme/includes/dial_logo_map.php theme/endpoints/user/set_dial_language.php theme/styles/dial.css theme/scripts/stats-dial.js theme/scripts/dial-interaction.js theme/scripts/dial-language.js theme/images/dial-icon/favicon.svg theme/images/dial-icon/favicon-16.png theme/images/dial-icon/favicon-32.png)
for file in "${required[@]}"; do
  [[ -f "$file" ]] || { echo "Missing: $file" >&2; exit 1; }
done

version=$(tr -d '\n' < VERSION)
grep -Fq "org.opencontainers.image.version=\"$version\"" Dockerfile || { echo 'Dockerfile version differs from VERSION' >&2; exit 1; }
grep -Fq "wallos-dial:$version" compose.build.yaml || { echo 'Compose tag differs from VERSION' >&2; exit 1; }

upstream_version=$(tr -d '\n' < WALLOS_VERSION)
grep -Fq "FROM bellamy/wallos:$upstream_version@sha256:" Dockerfile || { echo 'Base image differs from WALLOS_VERSION' >&2; exit 1; }
grep -Fq "ghcr.io/lukevoidx/wallos-dial:$version" compose.yaml || { echo 'Public Compose tag differs from VERSION' >&2; exit 1; }

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

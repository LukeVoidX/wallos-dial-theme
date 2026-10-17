#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

required=(VERSION WALLOS_VERSION Dockerfile compose.yaml compose.build.yaml compat/upstream-files.sha256 LICENSE.md README.md docs/CATEGORIES.md theme/index.php theme/subscriptions.php theme/categories.php theme/settings.php theme/calendar.php theme/stats.php theme/includes/header.php theme/includes/dial_version.php theme/includes/dial_logo_map.php theme/includes/dial_categories.php theme/includes/dial_category_i18n.php theme/includes/dial_mail_theme.php theme/endpoints/categories/category.php theme/endpoints/categories/dial_manage.php theme/endpoints/user/set_dial_language.php theme/endpoints/cronjobs/sendnotifications.php theme/endpoints/cronjobs/sendcancellationnotifications.php theme/endpoints/cronjobs/sendverificationemails.php theme/endpoints/cronjobs/sendresetpasswordemails.php theme/endpoints/notifications/testemailnotifications.php theme/styles/dial.css theme/scripts/stats-dial.js theme/scripts/dial-interaction.js theme/scripts/dial-categories.js theme/scripts/dial-category-refresh.js theme/scripts/dial-language.js theme/images/dial-icon/favicon.svg theme/images/dial-icon/favicon-16.png theme/images/dial-icon/favicon-32.png tests/category-core.php tests/category-http.sh tests/category-browser.cjs tests/mail-theme.php tests/mail-mime.php tests/mail-smtp.php)
for file in "${required[@]}"; do
  [[ -f "$file" ]] || { echo "Missing: $file" >&2; exit 1; }
done
[[ -f docs/CATEGORIES.zh-CN.md && -x tests/category-demo-edge.sh ]] || {
  echo 'Missing Chinese category guide or executable Demo edge test' >&2; exit 1;
}

grep -Eq '^[[:space:]]*path / .* /categories\.php ' demo/Caddyfile.site.example || {
  echo 'Demo edge does not allow the read-only category page' >&2; exit 1;
}
if grep -Eq '^[[:space:]]*path .*endpoints/categories/(category|dial_manage)\.php' demo/Caddyfile.site.example; then
  echo 'Demo edge must not allow category writes' >&2; exit 1
fi

version=$(tr -d '\n' < VERSION)
grep -Fq "org.opencontainers.image.version=\"$version\"" Dockerfile || { echo 'Dockerfile version differs from VERSION' >&2; exit 1; }
grep -Fq "const DIAL_VERSION = '$version';" theme/includes/dial_version.php || { echo 'Dial PHP version differs from VERSION' >&2; exit 1; }
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
  php tests/mail-theme.php
else
  echo 'PHP missing: use the Docker image for PHP syntax verification' >&2
fi

# data/ is explicitly Git/Docker-ignored and may hold a running local demo.
# Scan the source package, not that disposable runtime volume.
if find . \( -path './.git' -o -path './data' \) -prune -o -type f \( -name '*.db' -o -name '*.sqlite' -o -name '.env' -o -name '*.key' -o -name '*.pem' \) -print | grep -q .; then
  echo 'Local database, environment, or key file found in package' >&2
  exit 1
fi
public_icons=(apple-one chatgpt claude jianying netcup nmcloud proxy-seller racknerd vmiss)
for file in docs/ICONS.md docs/zh-CN/ICONS.md theme/migrations/dial_001_icon_library.php theme/includes/dial_icon_catalog.php theme/includes/dial_icon_repository.php theme/includes/dial_icon_resolver.php theme/includes/dial_icon_normalize.php theme/includes/dial_icon_transaction.php theme/includes/dial_logo_limits.php theme/includes/subscription_details_popup.php theme/endpoints/dial/icons.php theme/endpoints/dial/icon.php theme/endpoints/dial/subscription_icon.php theme/endpoints/subscription/add.php theme/endpoints/subscription/clone.php theme/endpoints/subscription/get.php theme/scripts/dial-icon-chooser.js theme/scripts/subscription-details.js compat/nginx-dial-icon.conf tests/icon-catalog.php tests/icon-schema.php tests/icon-resolver.php tests/icon-normalize.php tests/icon-atomic.php tests/logo-limits.php tests/icon-http.py tests/icon-detail-http.py tests/icon-cache-browser.cjs tests/icon-detail-browser.cjs tests/icon-chooser-browser.cjs; do
  [[ -f "$file" ]] || { echo "Missing icon-system file: $file" >&2; exit 1; }
done
[[ -x theme/bin/dial-render-svg.sh && -x tests/run-icon-http.sh ]] || {
  echo 'Icon SVG renderer or HTTP test runner is not executable' >&2; exit 1;
}
for icon in "${public_icons[@]}"; do
  [[ -f "theme/images/dial-catalog/$icon.svg" ]] || { echo "Missing catalog icon: $icon" >&2; exit 1; }
done
[[ $(find theme/images/dial-catalog -maxdepth 1 -type f -name '*.svg' | wc -l | tr -d ' ') == 9 ]] || {
  echo 'Public catalog contains an unreviewed SVG' >&2; exit 1;
}
if find theme/images/dial-logos -type f ! -name .gitkeep | grep -q . ||
   [[ -e config/logo-map.local.php ]] ||
   grep -RIEq 'dolphtek|erenship|jarvishub|mostrahub|withluke' theme config ||
   grep -RIEq 'lukevoidx|mostai' theme/images/dial-catalog/*.svg config; then
  echo 'Private logo or account-specific mapping found in public package' >&2
  exit 1
fi

echo 'Package checks passed.'

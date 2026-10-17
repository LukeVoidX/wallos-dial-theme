#!/bin/sh

set -euo pipefail

echo "Startup script is running..." > /var/log/startup.log

# Default the PUID and PGID environment variables to 82, otherwise
# set to the user defined ones.
PUID=${PUID:-82}
PGID=${PGID:-82}

# Change the www-data user id and group id to be the user-specified ones
groupmod -o -g "$PGID" www-data
usermod -o -u "$PUID" www-data
# Preserve upstream ownership of the writable app tree while respecting the
# two optional read-only personal SVG mounts documented in Compose. Recursive
# chown would fail on either bind mount and abort the entire first run.
find /var/www/html \
  \( -path /var/www/html/includes/dial_logo_map.local.php -o \
     -path /var/www/html/images/dial-logos \) -prune -o \
  -exec chown www-data:www-data {} +
chown -R www-data:www-data /tmp
chmod -R 770 /tmp

# PIDs we’ll track
PHP_FPM_PID=
NGINX_PID=
CROND_PID=
shutdown_in_progress=0

shutdown_once() {
  exit_signal=$?
  kill_signal=$(kill -l "$exit_signal" 2>/dev/null || echo "$exit_signal")

  [ "$shutdown_in_progress" -eq 1 ] && return 0
  shutdown_in_progress=1

  echo "Got signal: $kill_signal - Shutting down gracefully... "
  # nginx wants QUIT for graceful
  nginx -s quit || true
  # php-fpm graceful quit as well
  [ -n "${PHP_FPM_PID}" ] && kill -QUIT "${PHP_FPM_PID}" 2>/dev/null || true
  # cron can just get TERM
  [ -n "${CROND_PID}" ] && kill -TERM "${CROND_PID}" 2>/dev/null || true
  echo "Graceful shutdown complete."
}

# Handle all common stop signals
trap 'shutdown_once' SIGTERM SIGINT SIGQUIT

# Initialize and migrate before accepting HTTP traffic. Upstream launches Nginx
# first, which allows /registration.php and /health.php to race these writes.
# Create database if it does not exist
/usr/local/bin/php /var/www/html/endpoints/cronjobs/createdatabase.php

# Perform any database migrations
/usr/local/bin/php /var/www/html/endpoints/db/migrate.php

# Change permissions on the database directory
chmod -R 755 /var/www/html/db/
chown -R www-data:www-data /var/www/html/db/

mkdir -p /var/www/html/images/uploads/logos/avatars

# Change permissions on the logos directory
chmod -R 755 /var/www/html/images/uploads/logos
chown -R www-data:www-data /var/www/html/images/uploads/logos

# Start both PHP-FPM and Nginx only after the complete schema is available.
echo "Launching php-fpm"
php-fpm -F &
PHP_FPM_PID=$!

# Nginx must not accept a request while the FastCGI listener is still coming
# up, otherwise the first registration request can receive a transient 502.
attempt=0
until nc -z -w 1 127.0.0.1 9000 >/dev/null 2>&1; do
  if ! kill -0 "$PHP_FPM_PID" 2>/dev/null; then
    echo "php-fpm exited before its listener became ready" >&2
    wait "$PHP_FPM_PID" || true
    exit 1
  fi
  attempt=$((attempt + 1))
  if [ "$attempt" -ge 100 ]; then
    echo "php-fpm listener did not become ready" >&2
    exit 1
  fi
  sleep 0.05
done

echo "Launching crond"
crond -f &
CROND_PID=$!

echo "Launching nginx"
nginx -g 'daemon off;' &
NGINX_PID=$!

touch ~/startup.txt

# Remove crontab for the user
crontab -d -u root

# Run updatenextpayment.php and wait for it to finish
/usr/local/bin/php /var/www/html/endpoints/cronjobs/updatenextpayment.php

# Run updateexchange.php
/usr/local/bin/php /var/www/html/endpoints/cronjobs/updateexchange.php

# Run checkforupdates.php
/usr/local/bin/php /var/www/html/endpoints/cronjobs/checkforupdates.php

# Essentially wait until all child processes exit
wait

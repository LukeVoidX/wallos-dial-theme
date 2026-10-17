#!/usr/bin/env bash
set -euo pipefail

image=${1:?pass a local image tag}
cd "$(dirname "$0")/../.."

case "$(cat VERSION)" in
  1.0.0-rc.1|1.0.0) ;;
  *) echo 'Unexpected Dial 1.0 candidate version' >&2; exit 1;;
esac
test "$(cat WALLOS_VERSION)" = '5.8.3'
file=theme/endpoints/cronjobs/sendnotifications.php
test "$(grep -Fc 'SELECT * FROM household WHERE id = :id AND user_id = :ownerId' "$file")" -eq 11
test "$(grep -Fc "bindValue(':ownerId', \$userId, SQLITE3_INTEGER)" "$file")" -eq 11
! grep -Fq 'SELECT * FROM household WHERE id = :userId' "$file"
docker run --rm --entrypoint sh "$image" -ec '
  grep -Fq "&& \$password_login_disabled" /var/www/html/login.php
  grep -Fq "function rejectForeignId" /var/www/html/endpoints/subscription/add.php
  for table in currencies categories household payment_methods; do
    grep -Eq "FROM $table WHERE .*user_id" /var/www/html/endpoints/subscription/add.php
  done
'
echo 'Patched base and notification source verified.'

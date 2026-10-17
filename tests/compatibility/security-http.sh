#!/usr/bin/env bash
set -euo pipefail
image=$1
cd "$(dirname "$0")/../.."
mkdir -p data
scratch=$(mktemp -d "$PWD/data/.dial-security.XXXXXX")
name="dial-security-$$"
trap 'docker container rm -f "$name" >/dev/null 2>&1 || true; rm -r "$scratch"' EXIT
mkdir -p "$scratch/db" "$scratch/logos"
start_image() {
  docker run -d --name "$name" -e "PUID=$(id -u)" -e "PGID=$(id -g)" \
    -p 127.0.0.1::80 \
    -v "$scratch/db:/var/www/html/db" \
    -v "$scratch/logos:/var/www/html/images/uploads/logos" "$@" "$image" >/dev/null
  base="http://$(docker port "$name" 80/tcp | head -1)"
  for attempt in $(seq 1 40); do
    if curl -fsS "$base/health.php" >/dev/null 2>&1 &&
       docker logs "$name" 2>&1 | grep -F 'Updated next payment dates' >/dev/null; then return; fi
    sleep 1
  done
  echo 'Isolated container did not become healthy' >&2
  docker logs "$name" >&2 || true
  docker exec "$name" sh -ec 'ls -l /var/www/html/db; sqlite3 /var/www/html/db/wallos.db "SELECT COUNT(*) FROM migrations"' >&2 || true
  exit 1
}
start_image
docker container rm -f "$name" >/dev/null
ids=$(docker run --rm --user "$(id -u):$(id -g)" --entrypoint php \
  -v "$scratch/db:/var/www/html/db" -v "$PWD/tests/compatibility:/tests:ro" \
  "$image" /tests/seed-security.php /var/www/html/db/wallos.db)
start_image
alpha=$(jq -r .alpha <<<"$ids")
alphaCurrency=$(jq -r .alphaCurrency <<<"$ids")
alphaCategory=$(jq -r .alphaCategory <<<"$ids")
alphaPayment=$(jq -r .alphaPayment <<<"$ids")
alphaPayer=$(jq -r .alphaPayer <<<"$ids")
betaCurrency=$(jq -r .betaCurrency <<<"$ids")
betaCategory=$(jq -r .betaCategory <<<"$ids")
betaPayment=$(jq -r .betaPayment <<<"$ids")
betaPayer=$(jq -r .betaPayer <<<"$ids")
curl -fsSL -c "$scratch/alpha.cookies" -b "$scratch/alpha.cookies" \
  --data-urlencode username=alpha --data-urlencode 'password=DialFixture-2026!' \
  "$base/login.php" > "$scratch/dashboard.html"
csrf=$(sed -n 's/.*window.csrfToken = "\([^"]*\)".*/\1/p' "$scratch/dashboard.html" | head -1)
test -n "$csrf"
count() {
  docker exec "$name" sqlite3 /var/www/html/db/wallos.db \
    "SELECT COUNT(*) FROM subscriptions WHERE user_id=$alpha"
}
post_subscription() {
  currency=$1 category=$2 payer=$3 payment=$4
  curl -fsS -b "$scratch/alpha.cookies" -c "$scratch/alpha.cookies" \
    -H "X-CSRF-Token: $csrf" \
    --data-urlencode "name=Fixture service $RANDOM" \
    --data-urlencode 'price=9.99' --data-urlencode "currency_id=$currency" \
    --data-urlencode 'frequency=1' --data-urlencode 'cycle=3' \
    --data-urlencode 'next_payment=2026-11-15' \
    --data-urlencode 'start_date=2026-10-17' \
    --data-urlencode "payment_method_id=$payment" \
    --data-urlencode "payer_user_id=$payer" \
    --data-urlencode "category_id=$category" \
    --data-urlencode 'notes=' --data-urlencode 'url=' --data-urlencode 'logo-url=' \
    --data-urlencode 'notify_days_before=-1' --data-urlencode 'cancellation_date=' \
    --data-urlencode 'replacement_subscription_id=0' \
    "$base/endpoints/subscription/add.php"
}
before=$(count)
test "$(post_subscription "$alphaCurrency" "$alphaCategory" "$alphaPayer" "$alphaPayment" | jq -r .status)" = Success
test "$(count)" -eq $((before + 1))
for foreign in currency category payer payment; do
  case "$foreign" in
    currency) response=$(post_subscription "$betaCurrency" "$alphaCategory" "$alphaPayer" "$alphaPayment");;
    category) response=$(post_subscription "$alphaCurrency" "$betaCategory" "$alphaPayer" "$alphaPayment");;
    payer) response=$(post_subscription "$alphaCurrency" "$alphaCategory" "$betaPayer" "$alphaPayment");;
    payment) response=$(post_subscription "$alphaCurrency" "$alphaCategory" "$alphaPayer" "$betaPayment");;
  esac
  test "$(jq -r .status <<<"$response")" = Error
  test "$(count)" -eq $((before + 1))
done
test "$(docker exec "$name" sqlite3 /var/www/html/db/wallos.db \
  "SELECT COUNT(*) FROM household WHERE id=$betaPayer AND user_id=$alpha")" -eq 0
docker container rm -f "$name" >/dev/null
start_image \
  -e OIDC_ENABLED=1 -e OIDC_DISABLE_PASSWORD_LOGIN=1 \
  -e OIDC_CLIENT_ID=fixture -e OIDC_AUTH_URL=https://id.example.invalid/auth \
  -e OIDC_TOKEN_URL=https://id.example.invalid/token \
  -e OIDC_USERINFO_URL=https://id.example.invalid/userinfo \
  -e OIDC_REDIRECT_URL=https://wallos.example.invalid/oidc.php
curl -sS -c "$scratch/sso.cookies" -b "$scratch/sso.cookies" \
  -D "$scratch/login.headers" -o "$scratch/login.html" \
  --data-urlencode username=alpha --data-urlencode 'password=DialFixture-2026!' \
  "$base/login.php"
! grep -Eq '^Location: (\.|/)$' "$scratch/login.headers"
curl -sS -b "$scratch/sso.cookies" -D "$scratch/stats.headers" -o "$scratch/stats.html" "$base/stats.php"
grep -Eiq '^Location:.*login.php' "$scratch/stats.headers"
echo 'SSO and foreign-ID HTTP checks passed.'

#!/usr/bin/env bash

set -euo pipefail

log_step()
{
    printf '[check.sh] %s\n' "$1"
}

fail()
{
    printf '[check.sh] FAIL: %s\n' "$1" >&2
    exit 1
}

# ── Resolve release root from script location ────────────────────────
# VitoDeploy may run check.sh from an arbitrary working directory.
# Derive the release root so we can reliably read .env.
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
RELEASE_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
ENV_FILE="${RELEASE_DIR}/.env"

# Read a value from .env, stripping surrounding quotes, CR (CRLF endings),
# and surrounding whitespace. A stray \r in APP_URL/DEPLOY_TOKEN is what
# makes curl reject the URL with "Malformed input to a URL function".
read_env()
{
    local key="$1" default="${2-}"
    local value
    value=$(grep -oP "(?<=^${key}=).+" "$ENV_FILE" 2>/dev/null | head -n1 | tr -d '"\r' | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//')

    if [[ -z "$value" ]]; then
        printf '%s' "$default"
    else
        printf '%s' "$value"
    fi
}

log_step "release: ${RELEASE_DIR}"

# composer.json requires php ^8.3 — accept 8.3, 8.4, or 8.5.
log_step 'check PHP version'
PHP_VERSION="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
[[ "$PHP_VERSION" =~ ^8\.(3|4|5)$ ]] || fail "wrong PHP version (got ${PHP_VERSION}, expected 8.3/8.4/8.5)"

log_step 'check ext-mongodb'
php -m | grep -q "mongodb" || fail 'ext-mongodb missing'

# REDISCLI_AUTH keeps the password out of `ps` and silences redis-cli's
# "Using a password with '-a' or '-u' option…" warning. Empty/unset means
# the local Redis has no AUTH configured (which is fine).
REDIS_PASSWORD=$(read_env REDIS_PASSWORD)

log_step 'ping redis'
if [[ -n "$REDIS_PASSWORD" && "$REDIS_PASSWORD" != "null" ]]; then
    REDISCLI_AUTH="$REDIS_PASSWORD" redis-cli ping | grep -q "PONG" || fail 'redis ping did not return PONG'
else
    redis-cli ping | grep -q "PONG" || fail 'redis ping did not return PONG (no auth)'
fi

# Clear OPcache via HTTP (must happen after VitoDeploy switches the symlink
# so that nginx routes to the new release via index.php).
APP_URL=$(read_env APP_URL "http://localhost")
DEPLOY_TOKEN=$(read_env DEPLOY_TOKEN)

[[ "$APP_URL" =~ ^https?:// ]] || fail "APP_URL is not a valid http(s) URL: '${APP_URL}'"
[[ -n "$DEPLOY_TOKEN" ]] || fail "DEPLOY_TOKEN is empty in ${ENV_FILE} — the /deploy/clear-opcache route 403s without it"

log_step "clear opcache (${APP_URL}/deploy/clear-opcache)"
OPCACHE_RESPONSE=$(curl -sS --max-time 10 -w '\nHTTP_STATUS:%{http_code}' "${APP_URL}/deploy/clear-opcache?token=${DEPLOY_TOKEN}" 2>&1) || true
OPCACHE_BODY="${OPCACHE_RESPONSE%$'\n'HTTP_STATUS:*}"
OPCACHE_STATUS="${OPCACHE_RESPONSE##*HTTP_STATUS:}"
[[ "$OPCACHE_BODY" == "OPCACHE_CLEARED" ]] || fail "opcache clear http=${OPCACHE_STATUS} body=${OPCACHE_BODY}"

# Warm-up request: with a fresh opcache + cold app caches, the first
# request pays the full bootstrap cost (PHP file compile, Laravel boot,
# discovery query rebuild, Redis cache primer). Fire one curl that we
# don't grade on — its purpose is to populate opcache and cache stores
# so the real health check below sees a warm path. A 30s budget is
# deliberately generous; the actual health check is strict.
log_step "warm up (${APP_URL}/nl)"
curl -sS -o /dev/null --max-time 30 "${APP_URL}/nl" >/dev/null 2>&1 || true

# Strict health check with retry. With a warm path the response should
# be sub-second; we still allow up to three attempts to ride out a
# transient FPM worker recycle or a one-off slow query that races with
# the warm-up.
HEALTH_BODY_FILE="/tmp/check-home.$$"
trap 'rm -f "$HEALTH_BODY_FILE"' EXIT

attempt=0
max_attempts=3
sleep_between=2

while :; do
    attempt=$((attempt + 1))
    log_step "health check (${APP_URL}/nl) [attempt ${attempt}/${max_attempts}]"

    HEALTH_RESPONSE=$(curl -sS -o "$HEALTH_BODY_FILE" -w '%{http_code} %{time_total}s' --max-time 15 "${APP_URL}/nl" 2>&1 || echo "curl_error")
    HEALTH_STATUS="${HEALTH_RESPONSE%% *}"

    if [[ "$HEALTH_STATUS" == "200" ]]; then
        log_step "OK: ${HEALTH_RESPONSE}"
        break
    fi

    HEALTH_HEAD=$(head -c 500 "$HEALTH_BODY_FILE" 2>/dev/null || echo '<no body>')

    if [[ "$attempt" -ge "$max_attempts" ]]; then
        fail "home page http=${HEALTH_STATUS} body_head=${HEALTH_HEAD}"
    fi

    log_step "transient ${HEALTH_STATUS}; retrying in ${sleep_between}s — body_head=${HEALTH_HEAD:0:160}"
    sleep "$sleep_between"
    sleep_between=$((sleep_between * 2))
done

log_step 'all checks passed'

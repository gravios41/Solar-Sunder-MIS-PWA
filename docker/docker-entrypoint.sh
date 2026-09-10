#!/bin/sh
set -e

# ── Apache port (Render provides $PORT) ───────────────────────────────────
PORT="${PORT:-10000}"
sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/__PORT__/${PORT}/" /etc/apache2/sites-available/000-default.conf

# ── Materialize the environment for PHP ──────────────────────────────────
# Under php:apache (mod_php) getenv() does not reliably expose container
# environment variables to scripts. Write the ones we need to a KEY=VALUE
# file OUTSIDE the web root; config/config.php loads it. This makes runtime
# secrets work regardless of how the host injects them.
ENV_FILE=/var/www/env.runtime
: > "$ENV_FILE"
for key in \
    APP_ENV RENDER \
    SUPABASE_URL SUPABASE_ANON_KEY SUPABASE_SERVICE_KEY \
    SMTP_HOST SMTP_PORT SMTP_SECURE SMTP_USER SMTP_PASS MAIL_FROM MAIL_FROM_NAME \
    RESEND_API_KEY
do
    val=$(printenv "$key" 2>/dev/null || true)
    if [ -n "$val" ]; then
        printf '%s=%s\n' "$key" "$val" >> "$ENV_FILE"
    fi
done
# Readable by the Apache/PHP user. The file is outside DocumentRoot
# (/var/www/html) so it is never served over HTTP.
chown root:www-data "$ENV_FILE" 2>/dev/null && chmod 640 "$ENV_FILE" 2>/dev/null || chmod 644 "$ENV_FILE" 2>/dev/null || true

exec "$@"

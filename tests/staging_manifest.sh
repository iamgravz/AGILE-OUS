#!/usr/bin/env bash
set -euo pipefail
command -v docker >/dev/null || { echo 'FAIL: Docker Compose is required for staging manifest validation'; exit 1; }
command -v jq >/dev/null || { echo 'FAIL: jq is required'; exit 1; }
command -v openssl >/dev/null || { echo 'FAIL: OpenSSL is required'; exit 1; }

scratch="$(mktemp -d)"
trap 'rm -rf "$scratch"' EXIT
chmod 0700 "$scratch"
export AGILE_STAGING_SECRET_DIR="$scratch"
export AGILE_CLAMAV_DB_DIR="$scratch"
export STAGING_URL="https://staging.example.invalid"
export STAGING_BIND_IP="127.0.0.1"
export STAGING_HTTPS_PORT="8443"
for file in mysql_app_password mysql_root_password mfa_key_b64 db_backup_key_b64 file_backup_key_b64; do
    printf '%s' 'synthetic-test-only-not-an-actual-credential' > "$scratch/$file"
done
# The test certificate/key are disposable runner-only fixtures, not part of the repo.
openssl req -x509 -newkey rsa:2048 -sha256 -days 1 -nodes \
  -keyout "$scratch/tls.key" -out "$scratch/tls.crt" \
  -subj '/CN=staging.example.invalid' >/dev/null 2>&1
chmod 0600 "$scratch"/*

docker compose -f deploy/staging/compose.yaml config --quiet
docker compose -f deploy/staging/compose.yaml config --format json > "$scratch/manifest.json"
jq -e '
  .name == "agile-ous-staging"
  and (.services.database.ports == null)
  and (.services.app.ports == null)
  and (.services.web.ports | length == 1)
  and (.services.web.ports[0].host_ip == "127.0.0.1")
  and (.services.web.ports[0].target == 443)
  and (.services.app.environment.APP_ENV == "staging")
  and (.services.app.environment.SESSION_SECURE == "true")
  and (.services.app.environment.DB_PASSWORD_FILE == "/run/secrets/mysql_app_password")
  and (.services.app.environment.MFA_KEY_B64_FILE == "/run/secrets/mfa_key_b64")
  and (.services.app.environment.MAIL_TRANSPORT == "disabled")
  and (.services.app.environment.AI_EXTERNAL_PROCESSING_APPROVED == "false")
  and (.services.app.environment.ACADEMIC_ROLE_TRANSITIONS_ENABLED == "false")
  and (.services.app.environment.AGILE_ALLOW_ISOLATED_RESTORE == "false")
  and ((.services.migrate.profiles // []) | index("admin") != null)
  and ((.services.scanner.profiles // []) | index("tools") != null)
  and (.networks.private.internal == true)
  and (.volumes.private_data != null)
  and (.volumes.encrypted_backups != null)
  and (.secrets.tls_key != null)
' "$scratch/manifest.json" >/dev/null

# Nginx must serve only public/ and deny execution of arbitrary PHP scripts.
test -f deploy/staging/nginx.container.conf
grep -Fq 'root /srv/agile/public;' deploy/staging/nginx.container.conf
grep -Fq 'internal;' deploy/staging/nginx.container.conf
grep -Fq 'location ~*' deploy/staging/nginx.container.conf
grep -Fq 'ssl_certificate_key /run/secrets/tls_key;' deploy/staging/nginx.container.conf

# Test the actual Nginx configuration against an ephemeral certificate.
docker run --rm \
  --mount "type=bind,src=$PWD/deploy/staging/nginx.container.conf,dst=/etc/nginx/conf.d/default.conf,readonly" \
  --mount "type=bind,src=$scratch/tls.crt,dst=/run/secrets/tls_cert,readonly" \
  --mount "type=bind,src=$scratch/tls.key,dst=/run/secrets/tls_key,readonly" \
  nginx:stable-alpine nginx -t >/dev/null
echo 'PASS: protected staging Compose topology, secret mounts, and actual Nginx syntax validated.'

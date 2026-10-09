#!/bin/sh
# Short-lived root startup: convert root-owned Docker Compose secrets into
# app-readable files in a container-only tmpfs, then permanently drop to
# www-data. No secret value is printed, exported, or persisted in the image.
set -eu
umask 077

if [ "$(id -u)" -ne 0 ]; then
    echo 'Startup secret handoff requires the restricted initial root process.' >&2
    exit 1
fi

dir=/tmp/agile-runtime-secrets
mkdir -p "$dir"
chmod 0700 "$dir"
for key in DB_PASSWORD MFA_KEY_B64 AGILE_BACKUP_KEY_B64 AGILE_FILE_BACKUP_KEY_B64; do
    source="$(printenv "${key}_FILE" || true)"
    case "$source" in
      /run/secrets/*) ;;
      *) echo 'Required protected Docker secret not mounted.' >&2; exit 1 ;;
    esac
    if [ ! -f "$source" ] || [ -L "$source" ] || [ ! -r "$source" ] || [ ! -s "$source" ]; then
        echo 'Required protected Docker secret is missing or unreadable.' >&2
        exit 1
    fi
    # The source is never chmod'd or modified: host mode 0600 remains intact.
    destination="$dir/$key"
    cp -- "$source" "$destination"
    # Root has CHOWN but intentionally not FOWNER/DAC_OVERRIDE capabilities.
    # Set permissions while still owner, then transfer file ownership once.
    chmod 0400 "$destination"
    chown www-data:www-data "$destination"
    export "${key}_FILE=$destination"
done

chmod 0700 "$dir"
chown www-data:www-data "$dir"

# Standard PHP-FPM runs a tightly capability-restricted root master only
# to open container stderr and manage the pool; individual HTTP/grade/Welfare
# requests execute in the www-data pool. Running the FPM *master* as
# www-data prevents the official /proc/self/fd/2 error log from being opened.
if [ "${1:-}" = 'php-fpm' ]; then
    exec "$@"
fi

# Maintenance, malware-scan and migration PHP code runs as www-data.
exec gosu www-data "$@"

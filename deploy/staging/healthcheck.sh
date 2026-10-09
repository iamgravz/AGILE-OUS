#!/bin/sh
# Run health checks as the *same unprivileged account* as PHP request workers.
# Container health commands do not inherit env changes made by PID 1,
# so map only secret-file paths to the tmpfs copies before dropping UID.
set -eu
export DB_PASSWORD_FILE=/tmp/agile-runtime-secrets/DB_PASSWORD
export MFA_KEY_B64_FILE=/tmp/agile-runtime-secrets/MFA_KEY_B64
export AGILE_BACKUP_KEY_B64_FILE=/tmp/agile-runtime-secrets/AGILE_BACKUP_KEY_B64
export AGILE_FILE_BACKUP_KEY_B64_FILE=/tmp/agile-runtime-secrets/AGILE_FILE_BACKUP_KEY_B64
exec gosu www-data php /srv/agile/scripts/staging_container_health.php

#!/bin/sh
set -eu
port="${PORT:-8080}"
case "$port" in
  ''|*[!0-9]*) echo "Invalid PORT setting" >&2; exit 1 ;;
esac
if [ "$port" -lt 1 ] || [ "$port" -gt 65535 ]; then
  echo "PORT outside allowed range" >&2
  exit 1
fi
printf 'Listen %s\n' "$port" > /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:80>/<VirtualHost *:$port>/" /etc/apache2/sites-available/000-default.conf
exec apache2-foreground

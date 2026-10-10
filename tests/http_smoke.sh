#!/usr/bin/env bash
set -euo pipefail
base="${1:-http://127.0.0.1:8080}"
check() {
  local path="$1" expected="$2" result
  result=$(curl --silent --output /dev/null --write-out '%{http_code}' "$base$path")
  if [[ "$result" != "$expected" ]]; then
    echo "HTTP smoke failure: $path expected $expected got $result" >&2
    exit 1
  fi
}
check '/' 200
check '/apply.php' 503
check '/membership.php' 401
check '/recruitment.php' 401
check '/positions.php' 401
check '/notification_drafts.php' 401
check '/reports.php' 401
check '/welfare.php' 401
check '/welfare_case.php?id=1' 401
echo "Anonymous HTTP access smoke checks passed"

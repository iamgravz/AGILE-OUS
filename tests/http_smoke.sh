#!/usr/bin/env bash
set -euo pipefail
BASE="${BASE_URL:-http://127.0.0.1:8090}"
COOKIE="$(mktemp)"
BODY="$(mktemp)"
trap 'rm -f "$COOKIE" "$BODY"' EXIT

expect_status() {
  local expected="$1" actual="$2" name="$3"
  if [[ "$actual" != "$expected" ]]; then
    echo "FAIL $name: expected $expected got $actual"; exit 1
  fi
  echo "PASS $name ($actual)"
}
extract_csrf() {
  grep -oE 'name="_csrf" value="[a-f0-9]+"' "$BODY" | head -n 1 | sed -E 's/.*value="([^"]+)".*/\1/'
}
# Wait for PHP server startup; do not hide persistent errors.
for attempt in 1 2 3 4 5 6 7 8 9 10; do
  if curl -fsS "$BASE/" -o "$BODY"; then break; fi
  sleep 1
done
grep -q 'Welcome to AGILE OUS' "$BODY"
echo 'PASS public landing page'
code="$(curl -sS -o "$BODY" -w '%{http_code}' -b "$COOKIE" "$BASE/dashboard")"
expect_status 303 "$code" 'Anonymous dashboard denied'
code="$(curl -sS -o "$BODY" -w '%{http_code}' -b "$COOKIE" -c "$COOKIE" "$BASE/apply")"
expect_status 200 "$code" 'Public application form'
TOKEN="$(extract_csrf)"
test -n "$TOKEN"
code="$(curl -sS -o "$BODY" -w '%{http_code}' -b "$COOKIE" -c "$COOKIE" \
  --data-urlencode "_csrf=$TOKEN" \
  --data-urlencode "full_name=Synthetic HTTP Applicant" \
  --data-urlencode "email=app-http@example.invalid" \
  --data-urlencode "student_number=TEST-HTTP-2026" \
  --data-urlencode "desired_role=Committee Member" \
  --data-urlencode "motivation=This is synthetic test data for end-to-end registration." \
  --data-urlencode "privacy_consent=yes" \
  --data-urlencode "answers[preferred_committee]=Membership and Student Welfare" \
  --data-urlencode "answers[relevant_skills]=Synthetic experience in student outreach" "$BASE/apply")"
expect_status 200 "$code" 'Application POST persisted'
grep -q 'Application Submitted' "$BODY"
grep -q 'AG-[A-F0-9]' "$BODY"
echo 'PASS application returned reference'
code="$(curl -sS -o "$BODY" -w '%{http_code}' -b "$COOKIE" -c "$COOKIE" "$BASE/login")"
expect_status 200 "$code" 'Staff login page'
TOKEN="$(extract_csrf)"
code="$(curl -sS -o "$BODY" -w '%{http_code}' -b "$COOKIE" -c "$COOKIE" \
  --data-urlencode "_csrf=$TOKEN" \
  --data-urlencode "email=staff-smoke@example.invalid" \
  --data-urlencode "password=${HTTP_TEST_PASSWORD}" "$BASE/login")"
expect_status 303 "$code" 'Staff login with synthetic credentials'
code="$(curl -sS -o "$BODY" -w '%{http_code}' -b "$COOKIE" -c "$COOKIE" "$BASE/dashboard")"
expect_status 200 "$code" 'Authorized dashboard'
grep -q 'Synthetic HTTP Applicant' "$BODY"
echo 'PASS stored application visible to authorized staff'
TOKEN="$(extract_csrf)"
code="$(curl -sS -o "$BODY" -w '%{http_code}' -b "$COOKIE" -c "$COOKIE" \
  --data-urlencode "_csrf=$TOKEN" "$BASE/logout")"
expect_status 303 "$code" 'Staff logout'
code="$(curl -sS -o "$BODY" -w '%{http_code}' -b "$COOKIE" "$BASE/dashboard")"
expect_status 303 "$code" 'Dashboard denied after logout'
echo 'HTTP end-to-end smoke passed (synthetic test records).'

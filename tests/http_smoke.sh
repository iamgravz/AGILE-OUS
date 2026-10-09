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
expect_status 303 "$code" 'Password-only staff session blocked pending MFA'
code="$(curl -sS -o "$BODY" -w '%{http_code}' -b "$COOKIE" -c "$COOKIE" "$BASE/staff/academic/casework")"
expect_status 303 "$code" 'Sensitive academic route blocked before MFA'
code="$(curl -sS -o "$BODY" -w '%{http_code}' -b "$COOKIE" -c "$COOKIE" "$BASE/mfa/setup")"
expect_status 200 "$code" 'MFA enrollment screen'
TOKEN="$(extract_csrf)"
test -n "$TOKEN"
code="$(curl -sS -o "$BODY" -w '%{http_code}' -b "$COOKIE" -c "$COOKIE" \
  --data-urlencode "_csrf=$TOKEN" \
  --data-urlencode "password=${HTTP_TEST_PASSWORD}" "$BASE/mfa/enroll")"
expect_status 200 "$code" 'Password-confirmed MFA enrollment secret'
SECRET="$(grep -oE 'id="mfa-secret">[A-Z2-7]+' "$BODY" | head -n 1 | cut -d'>' -f2)"
test -n "$SECRET"
TOKEN="$(extract_csrf)"
CODE="$(php -r 'require getcwd()."/app/bootstrap.php"; $name="Agile".chr(92)."Mfa"; echo $name::totp($argv[1],intdiv(time(),30));' "$SECRET")"
code="$(curl -sS -o "$BODY" -w '%{http_code}' -b "$COOKIE" -c "$COOKIE" \
  --data-urlencode "_csrf=$TOKEN" \
  --data-urlencode "code=$CODE" "$BASE/mfa/confirm")"
expect_status 200 "$code" 'MFA confirmation and one-time recovery code display'
grep -q 'Staff MFA Enabled' "$BODY"
RECOVERY="$(grep -oE '<li><code>[A-F0-9]{24}</code></li>' "$BODY" | head -n 1 | cut -d'>' -f3 | cut -d'<' -f1)"
test -n "$RECOVERY"
code="$(curl -sS -o "$BODY" -w '%{http_code}' -b "$COOKIE" -c "$COOKIE" "$BASE/dashboard")"
expect_status 200 "$code" 'Authorized dashboard'
grep -q 'Synthetic HTTP Applicant' "$BODY"
echo 'PASS stored application visible to authorized staff'

code="$(curl -sS -o "$BODY" -w '%{http_code}' -b "$COOKIE" -c "$COOKIE" "$BASE/staff/academic/casework")"
expect_status 200 "$code" 'MSW Head academic casework dashboard'
grep -q 'Semester Summary' "$BODY"
echo 'PASS academic casework summary rendered'
code="$(curl -sS -o "$BODY" -w '%{http_code}' -b "$COOKIE" -c "$COOKIE" "$BASE/staff/academic/appeals")"
expect_status 200 "$code" 'MSW Head academic correction inbox'
grep -q 'Academic Corrections' "$BODY"
echo 'PASS academic correction inbox rendered'
code="$(curl -sS -o "$BODY" -w '%{http_code}' -b "$COOKIE" -c "$COOKIE" "$BASE/member/academic")"
expect_status 200 "$code" 'Authenticated linked-member academic view'
grep -q 'No academic verification cycles are linked' "$BODY"
echo 'PASS staff account with no member association cannot see other students'
# The member page has no form for an unlinked account: obtain a fresh logout
# CSRF token from the authenticated staff dashboard before signing out.
code="$(curl -sS -o "$BODY" -w '%{http_code}' -b "$COOKIE" -c "$COOKIE" "$BASE/dashboard")"
expect_status 200 "$code" 'Refresh staff dashboard before secure logout'
TOKEN="$(extract_csrf)"
code="$(curl -sS -o "$BODY" -w '%{http_code}' -b "$COOKIE" -c "$COOKIE" \
  --data-urlencode "_csrf=$TOKEN" "$BASE/logout")"
expect_status 303 "$code" 'Staff logout'
code="$(curl -sS -o "$BODY" -w '%{http_code}' -b "$COOKIE" "$BASE/dashboard")"
expect_status 303 "$code" 'Dashboard denied after logout'
# Second password login must never silently inherit MFA from the earlier session.
code="$(curl -sS -o "$BODY" -w '%{http_code}' -b "$COOKIE" -c "$COOKIE" "$BASE/login")"
expect_status 200 "$code" 'Second password login form'
TOKEN="$(extract_csrf)"
code="$(curl -sS -o "$BODY" -w '%{http_code}' -b "$COOKIE" -c "$COOKIE" \
  --data-urlencode "_csrf=$TOKEN" \
  --data-urlencode "email=staff-smoke@example.invalid" \
  --data-urlencode "password=${HTTP_TEST_PASSWORD}" "$BASE/login")"
expect_status 303 "$code" 'Second password login accepted'
code="$(curl -sS -o "$BODY" -w '%{http_code}' -b "$COOKIE" -c "$COOKIE" "$BASE/dashboard")"
expect_status 303 "$code" 'New login still requires MFA'
code="$(curl -sS -o "$BODY" -w '%{http_code}' -b "$COOKIE" -c "$COOKIE" "$BASE/mfa/challenge")"
expect_status 200 "$code" 'Second factor challenge form'
TOKEN="$(extract_csrf)"
code="$(curl -sS -o "$BODY" -w '%{http_code}' -b "$COOKIE" -c "$COOKIE" \
  --data-urlencode "_csrf=$TOKEN" --data-urlencode "code=$RECOVERY" "$BASE/mfa/verify")"
expect_status 303 "$code" 'One-time recovery code authorizes second login'
code="$(curl -sS -o "$BODY" -w '%{http_code}' -b "$COOKIE" -c "$COOKIE" "$BASE/dashboard")"
expect_status 200 "$code" 'Staff dashboard restored only after MFA'
echo 'HTTP end-to-end smoke passed (synthetic test records).'

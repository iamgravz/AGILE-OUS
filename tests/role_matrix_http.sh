#!/usr/bin/env bash
set -euo pipefail
BASE="${BASE_URL:-http://127.0.0.1:8090}"
CREDENTIALS="${UAT_CREDENTIALS_FILE:?Missing CI-only UAT fixture path}"
PASSWORD="${HTTP_TEST_PASSWORD:?Missing CI-only test password}"
BODY="$(mktemp)"
COOKIE="$(mktemp)"
trap 'rm -f "$BODY" "$COOKIE"' EXIT

assert_http(){
    local expected="$1" name="$2" endpoint="$3"
    local status
    status="$(curl -sS -o "$BODY" -w '%{http_code}' -b "$COOKIE" -c "$COOKIE" "$BASE$endpoint")"
    if [[ "$status" != "$expected" ]]; then
        echo "FAIL: $name expected $expected received $status"
        exit 1
    fi
    echo "PASS: $name ($status)"
}
csrf(){
    grep -oE 'name="_csrf" value="[a-f0-9]+"' "$BODY" | head -n 1 | sed -E 's/.*value="([^"]+)".*/\1/'
}

# Cross-site submissions MUST fail even if no session cookie/token exists.
: > "$COOKIE"
status="$(curl -sS -o "$BODY" -w '%{http_code}' -X POST "$BASE/apply")"
[[ "$status" == "419" ]] || { echo "FAIL: anonymous tokenless application POST was not rejected"; exit 1; }
echo 'PASS: tokenless first-request application POST is rejected (419)'
status="$(curl -sS -o "$BODY" -w '%{http_code}' -X POST "$BASE/welfare")"
[[ "$status" == "419" ]] || { echo "FAIL: tokenless welfare POST was not rejected"; exit 1; }
echo 'PASS: tokenless welfare POST is rejected (419)'
assert_http 200 'Anonymous can view public application form' '/apply'
status="$(curl -sS -o "$BODY" -w '%{http_code}' -b "$COOKIE" -c "$COOKIE" -X POST "$BASE/apply")"
[[ "$status" == "419" ]] || { echo "FAIL: session with no submitted token was accepted"; exit 1; }
echo 'PASS: missing token rejected even with existing session'
status="$(curl -sS -o "$BODY" -w '%{http_code}' -b "$COOKIE" -c "$COOKIE" --data-urlencode '_csrf=' "$BASE/apply")"
[[ "$status" == "419" ]] || { echo "FAIL: blank CSRF token was accepted"; exit 1; }
echo 'PASS: blank CSRF token rejected'
assert_http 303 'Anonymous cannot view confidential welfare dashboard' '/staff/welfare'
assert_http 303 'Anonymous cannot view academic review dashboard' '/staff/academic'
assert_http 303 'Anonymous cannot request secure document download' '/staff/attachment?id=999999'
assert_http 200 'Public privacy information remains available' '/privacy'

app_id="$(awk -F'|' '$1=="unassigned_application" { print $2 }' "$CREDENTIALS")"
[[ "$app_id" =~ ^[0-9]+$ ]] || { echo 'FAIL: synthetic application ID missing'; exit 1; }

while IFS='|' read -r role email recovery; do
    [[ "$role" == "unassigned_application" ]] && continue
    : > "$COOKIE"
    assert_http 200 "$role password login page" '/login'
    token="$(csrf)"
    [[ -n "$token" ]] || { echo 'FAIL: CSRF token missing'; exit 1; }
    status="$(curl -sS -o "$BODY" -w '%{http_code}' -b "$COOKIE" -c "$COOKIE" \
        --data-urlencode "_csrf=$token" --data-urlencode "email=$email" \
        --data-urlencode "password=$PASSWORD" "$BASE/login")"
    [[ "$status" == "303" ]] || { echo "FAIL: synthetic login for $role"; exit 1; }

    if [[ "$role" != "member" ]]; then
        assert_http 303 "$role password-only cannot view dashboard" '/dashboard'
        assert_http 200 "$role MFA challenge screen" '/mfa/challenge'
        token="$(csrf)"
        status="$(curl -sS -o "$BODY" -w '%{http_code}' -b "$COOKIE" -c "$COOKIE" \
            --data-urlencode "_csrf=$token" --data-urlencode "code=$recovery" "$BASE/mfa/verify")"
        [[ "$status" == "303" ]] || { echo "FAIL: second factor for $role"; exit 1; }
        echo "PASS: $role independently completed MFA"
    fi

    case "$role" in
      msw_head)
        assert_http 200 'MSW Head can view confidential welfare dashboard' '/staff/welfare'
        assert_http 200 'MSW Head can view restricted academic dashboard' '/staff/academic'
        assert_http 200 'MSW Head can view specific application' "/application?id=$app_id"
        assert_http 200 'MSW Head can review HR requests' '/staff/hr'
        assert_http 200 'MSW Head can review email delivery' '/staff/email/reconcile'
        assert_http 200 'MSW Head can review academic appeals' '/staff/academic/appeals'
        ;;
      msw_member)
        assert_http 200 'Assigned-scoped MSW Member welfare dashboard loads' '/staff/welfare'
        assert_http 403 'MSW Member cannot read academic review dashboard' '/staff/academic'
        assert_http 403 'MSW Member cannot read unassigned applicant' "/application?id=$app_id"
        assert_http 403 'MSW Member cannot approve HR requests' '/staff/hr'
        assert_http 403 'MSW Member cannot reconcile outgoing Gmail' '/staff/email/reconcile'
        assert_http 200 'MSW Member may draft an email for approval' '/staff/email'
        ;;
      president)
        assert_http 200 'President can view aggregate dashboard only' '/dashboard'
        grep -q 'Read-only Overview' "$BODY"
        assert_http 403 'President cannot open confidential welfare records' '/staff/welfare'
        assert_http 403 'President cannot view private academic grades' '/staff/academic'
        assert_http 403 'President cannot view applicant personal record' "/application?id=$app_id"
        assert_http 403 'President cannot download an unknown private file' '/staff/attachment?id=999999'
        assert_http 403 'President cannot control HR requests' '/staff/hr'
        ;;
      admin)
        assert_http 200 'Technical Admin sees read-only overview' '/dashboard'
        assert_http 200 'Technical Admin can access independent identity review' '/staff/identity/review'
        assert_http 403 'Technical Admin cannot view Welfare cases' '/staff/welfare'
        assert_http 403 'Technical Admin cannot view academic grade reviews' '/staff/academic'
        assert_http 403 'Technical Admin cannot access applicant personal record' "/application?id=$app_id"
        assert_http 403 'Technical Admin cannot access MSW email drafts' '/staff/email'
        ;;
      member)
        assert_http 200 'General member sees own-only academic summary' '/member/academic'
        assert_http 403 'General member cannot access staff dashboard' '/dashboard'
        assert_http 403 'General member cannot access welfare case dashboard' '/staff/welfare'
        assert_http 403 'General member cannot access grade review dashboard' '/staff/academic'
        assert_http 403 'General member cannot approve members' '/staff/members'
        assert_http 403 'General member cannot review HR requests' '/staff/hr'
        ;;
      *) echo "FAIL: unexpected test role"; exit 1;;
    esac
done < "$CREDENTIALS"

echo 'PASS: synthetic end-to-end role-permission and CSRF acceptance matrix.'

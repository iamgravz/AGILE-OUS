#!/usr/bin/env bash
# Phase 10: cross-role HTTP acceptance tests using exclusively synthetic fixtures.
set -euo pipefail
BASE="${BASE_URL:-http://127.0.0.1:8090}"
FIXTURE="${PHASE10_FIXTURE:?Required synthetic fixture JSON}"
: "${HTTP_TEST_PASSWORD:?Synthetic password required}"
[[ "${CI:-}" == "true" && "${RUN_E2E_TESTS:-}" == "yes" ]] || {
  echo 'FAIL: this acceptance suite is permitted in CI with synthetic data only' >&2
  exit 1
}
command -v jq >/dev/null
[[ -f "$FIXTURE" ]] || { echo 'FAIL: fixture JSON missing' >&2; exit 1; }
COOKIE="$(mktemp)"
BODY="$(mktemp)"
trap 'rm -f "$COOKIE" "$BODY"' EXIT
pass() { printf 'PASS: %s\n' "$1"; }
fail() { printf 'FAIL: %s\n' "$1" >&2; exit 1; }
fixture() { jq -er "$1" "$FIXTURE"; }
visit() {
  local path="$1" expected="$2" label="$3" actual
  actual="$(curl --silent --show-error --output "$BODY" --write-out '%{http_code}' \
      --cookie "$COOKIE" --cookie-jar "$COOKIE" "$BASE$path")"
  [[ "$actual" == "$expected" ]] || fail "$label (expected $expected got $actual)"
  pass "$label [$expected]"
}
present() { grep -Fq -- "$1" "$BODY" || fail "$2 (expected text absent)"; pass "$2"; }
absent() { if grep -Fq -- "$1" "$BODY"; then fail "$2 (confidential text disclosed)"; fi; pass "$2"; }
token() {
  grep -oE 'name="_csrf" value="[a-f0-9]+"' "$BODY" |
     head -n 1 | sed -E 's/.*value="([^"]+)".*/\1/'
}
post() {
  local endpoint="$1" expected="$2" label="$3"
  shift 3
  local result params=()
  for field in "$@"; do params+=(--data-urlencode "$field"); done
  result="$(curl --silent --show-error --output "$BODY" --write-out '%{http_code}' \
       --cookie "$COOKIE" --cookie-jar "$COOKIE" "${params[@]}" "$BASE$endpoint")"
  [[ "$result" == "$expected" ]] || fail "$label (expected $expected got $result)"
  pass "$label [$expected]"
}
login() {
  local person="$1" email secret otp csrf
  : > "$COOKIE"
  email="$(fixture ".users.$person.email")"
  visit '/login' 200 "$person login screen"
  csrf="$(token)"; [[ -n "$csrf" ]] || fail 'Missing synthetic login CSRF'
  post '/login' 303 "$person password accepted" "_csrf=$csrf" "email=$email" "password=$HTTP_TEST_PASSWORD"
  if [[ "$person" != 'member' ]]; then
    visit '/mfa/setup' 200 "$person authenticator setup"
    csrf="$(token)"
    post '/mfa/enroll' 200 "$person MFA enrollment" "_csrf=$csrf" "password=$HTTP_TEST_PASSWORD"
    secret="$(grep -oE 'id="mfa-secret">[A-Z2-7]+' "$BODY" | head -n 1 | cut -d'>' -f2)"
    [[ -n "$secret" ]] || fail "$person missing authenticator secret"
    csrf="$(token)"
    otp="$(php -r 'require getcwd()."/app/bootstrap.php"; $name="Agile".chr(92)."Mfa"; echo $name::totp($argv[1],intdiv(time(),30));' "$secret")"
    post '/mfa/confirm' 200 "$person MFA confirmation" "_csrf=$csrf" "code=$otp"
    present 'Staff MFA Enabled' "$person second factor authorized"
  fi
}
# Public data must never include unpublished editorial copy or Welfare narratives.
: > "$COOKIE"
visit '/updates' 200 'Public CMS is readable'
absent 'Phase10 Editorial Draft Review' 'Editorial review is not public'
visit '/vacancies' 200 'Public vacancies page responds'
visit '/welfare/track' 200 'Public welfare status form'
csrf="$(token)"
post '/welfare/track' 200 'Public private-token status succeeds' "_csrf=$csrf" \
  "reference=$(fixture '.welfare.reference')" "token=$(fixture '.welfare.token')"
present 'Current status' 'Only welfare status is displayed'
absent 'PHASE10_CONFIDENTIAL_WELFARE_A' 'Public welfare status cannot reveal sensitive narrative'

login president
visit '/dashboard' 200 'President may view aggregate summary'
absent 'Phase10 Private Applicant ASSIGNED' 'President cannot see applicant identity'
absent 'PHASE10_CONFIDENTIAL_WELFARE_A' 'President overview does not reveal welfare narrative'
visit "/application?id=$(fixture '.applications.a')" 403 'President cannot open individual application'
visit '/staff/welfare' 403 'President cannot open welfare cases'
visit '/staff/academic/casework' 403 'President cannot access grades or academic appeals'
visit '/staff/email' 403 'President cannot access personal email queue'
visit '/staff/identity/review' 403 'President cannot approve identity links'
visit '/staff/hr' 403 'President cannot change HR staffing decisions'

login admin
visit '/dashboard' 200 'Technical Admin receives aggregate overview'
absent 'Phase10 Private Applicant ASSIGNED' 'Admin cannot see applicant name'
visit '/staff/welfare' 403 'Technical Admin denied welfare case access'
visit '/staff/academic/casework' 403 'Technical Admin denied confidential academic records'
visit "/application?id=$(fixture '.applications.a')" 403 'Technical Admin denied applicant case access'
visit '/staff/identity/review' 200 'Technical Admin can review designated identity requests'

login assigned
visit '/dashboard' 200 'Assigned MSW Member has application dashboard'
present 'Phase10 Private Applicant ASSIGNED' 'Assigned reviewer sees own application'
absent 'Phase10 Private Applicant OTHER' 'Assigned reviewer cannot list unrelated application'
visit "/application?id=$(fixture '.applications.a')" 200 'Assigned reviewer can open assigned applicant'
visit "/application?id=$(fixture '.applications.b')" 403 'Assigned reviewer cannot open unrelated applicant'
visit '/staff/welfare' 200 'Assigned reviewer has scoped welfare list'
present 'Phase10 Private Welfare CASE-A' 'Assigned reviewer can see own welfare case'
absent 'Phase10 Private Welfare CASE-B' 'Assigned reviewer cannot list other welfare cases'
visit "/staff/welfare/case?id=$(fixture '.welfare.a')" 200 'Assigned welfare case opens'
present 'PHASE10_CONFIDENTIAL_WELFARE_A' 'Assigned welfare case narrative is visible'
visit "/staff/welfare/case?id=$(fixture '.welfare.b')" 403 'Unassigned confidential welfare case rejected'
visit '/staff/academic/casework' 403 'MSW Member cannot see confidential academic records'
visit '/staff/email' 200 'MSW Member can draft official email'
post '/staff/email/draft' 419 'Missing CSRF blocks email mutation' \
  'recipient=synthetic@example.invalid' 'subject=No Token' 'message=Forbidden mutation test'

login unassigned
visit "/application?id=$(fixture '.applications.a')" 403 'Other MSW Member cannot open first assigned applicant'
visit "/application?id=$(fixture '.applications.b')" 200 'Other MSW Member can open own applicant'
visit "/staff/welfare/case?id=$(fixture '.welfare.a')" 403 'Other MSW Member cannot access first confidential welfare case'
visit "/staff/welfare/case?id=$(fixture '.welfare.b')" 200 'Other MSW Member can open own welfare case'

login committee
visit '/staff/hr' 200 'Committee Head can request authorized HR staffing'
visit '/staff/content' 200 'Committee Head can draft organization content'
visit '/staff/welfare' 403 'Committee Head cannot access Welfare case records'
visit "/staff/recruitment?application_id=$(fixture '.applications.a')" 403 'Committee Head cannot inspect confidential recruitment review'
visit '/staff/academic/casework' 403 'Committee Head denied restricted academic dashboard'
visit '/staff/members' 403 'Committee Head denied entire membership directory'

login editor
visit '/staff/content' 200 'Source Code Editor can review own editorial draft'
present 'Phase10 Editorial Draft Review' 'Editor sees unpublished editorial review'
visit '/staff/welfare' 403 'Source Code Editor denied Welfare'
visit '/staff/email' 403 'Source Code Editor denied MSW mail'
visit '/staff/academic/casework' 403 'Source Code Editor denied restricted academic dashboard'
csrf="$(token)"
# The current page with a 403 lacks a token; refresh authenticated content screen.
visit '/staff/content' 200 'Refresh editor CSRF form'
csrf="$(token)"
post '/staff/content/status' 422 'Editorial draft cannot be self-published' \
  "_csrf=$csrf" "id=$(fixture '.editor_post')" 'status=published'
visit '/updates' 200 'Public updates still respond'
absent 'Phase10 Editorial Draft Review' 'Unauthorized editorial publishing had no effect'

login member
visit '/member/card' 200 'General Member can view own digital card'
present 'Phase10 Synthetic General Member' 'Member card is account-scoped'
visit '/member/academic' 200 'Member can view only own semester verification status'
visit '/dashboard' 403 'Ordinary Member denied staff dashboard'
visit '/staff/welfare' 403 'Ordinary Member denied Welfare cases'
visit '/staff/members' 403 'Ordinary Member denied other member records'
visit "/member/verify?code=$(fixture '.member.verification_code')" 404 'Public card verification defaults to opt-out'
visit '/member/card' 200 'Member privacy preference form'
csrf="$(token)"
post '/member/verification-visibility' 303 'Member explicitly opts into public verification' \
  "_csrf=$csrf" 'enabled=yes'
visit "/member/verify?code=$(fixture '.member.verification_code')" 200 'Opted-in digital card validates'
present 'Phase10 Synthetic General Member' 'Only selected public member fields disclosed'
absent 'phase10-member-' 'Member email remains private in public verification'
visit '/member/card' 200 'Member may revoke visibility'
csrf="$(token)"
post '/member/verification-visibility' 303 'Member revokes digital card public visibility' "_csrf=$csrf"
visit "/member/verify?code=$(fixture '.member.verification_code')" 404 'Card privacy revocation takes effect'
echo 'PASS: Phase 10 synthetic multi-role HTTP staging acceptance suite passed.'

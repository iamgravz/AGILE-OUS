# Test cases

TC-01 to TC-35 from `docs/PROJECT_CONTEXT.md` §13, and which test covers each business rule in §7.

## How to run them

**Automated:** run `composer test`. It builds its own databases (`agile_ams_test` and `agile_ams_test_http_test`) and a temporary files folder, so your data and `storage/` are not touched, and it sends no email. It takes about 3 minutes.

**Manual, on your real site:**

- Start the site with `php -S localhost:8000 -t public`.
- Log in with these demo accounts. The password for all of them is `Password123!`.

| Role | Email |
|---|---|
| General Member | `member@agile.test` |
| Committee Head (Academic) | `head@agile.test` |
| Executive Committee | `execomm@agile.test` |
| MSW Member | `mswmember@agile.test` |
| MSW Committee Head | `mswhead@agile.test` |
| President | `president@agile.test` |

- With `MAIL_DRIVER=log`, emails do not leave the computer. Run `php jobs/send_email_queue.php`, then open the newest file in `storage/mail`.
- To see a phone layout, press F12, then Ctrl+Shift+M, and choose a width of 360. For a laptop, use 1366.

**How to read the results:** "Passed" means `composer test` passed that case on November 1, 2026. A blank result is yours to fill in during the demo. Parts marked **Manual** have not been recorded as passed here.

## Test cases

| ID | Module | Scenario | Steps | Expected result | Actual result | Pass/fail |
|---|---|---|---|---|---|---|
| TC-01 | Portal | Home renders on mobile and desktop | **Automated:** `HttpFlowsTest::testTc01HomeRenders` (loads, with the viewport tag). **Manual, for the look:** open `/` at 360 and at 1366. | No sideways scrolling, and the text is readable at both widths. | Automated: Passed. Manual: | |
| TC-02 | Tracking | Track with a valid code and email | **Automated:** `HttpFlowsTest::testTc02AndTc03TrackingAndItsLimit`. **Demo:** on `/track`, enter `DEMAPPSUBMT2` and `andrea.santos@agile.test`. | Status and timeline show. | Passed | Pass |
| TC-03 | Tracking | Wrong email, then too many tries | **Automated:** `HttpFlowsTest::testTc02AndTc03TrackingAndItsLimit`. **Demo:** on `/track`, enter the same code with `x@example.test`, six times. | The same message as for an unknown code, then "Too many tries". | Passed | Pass |
| TC-04 | Applications | Submit an application | **Automated:** `HttpWholeFlowsTest::testTc04AndTc31ApplyWithoutAnAccountThenApproval`. **Demo:** in a private window, go through `/apply` with a new email. The code is in `storage/mail` after running the queue job. | Status Submitted, a tracking code on screen, and an email queued. | Passed | Pass |
| TC-05 | Uploads | `.exe` or 10 MB file rejected | **Automated:** `HttpFlowsTest::testTc05WrongTypesAndLargeFilesAreRefused`. **Demo:** as a member, try both on Ask for Help. | A clear message for each, and nothing saved. | Passed | Pass |
| TC-06 | Applications | MSW Member cannot approve | **Automated:** `HttpFlowsTest::testTc06AnMswMemberCannotApproveByDirectPost`, `HttpAccessTest::testEveryProtectedRouteFollowsSectionSix`. **Demo:** as `mswmember@agile.test`, open an application under review. | No Approve button. A direct POST gets 403 and an `access.denied` audit row. | Passed | Pass |
| TC-07 | Applications | MSW Head approves | **Automated:** `AccountsAndDecisionsTest::testApprovalMakesAMemberWithNoAccountThenTheyRegister`, `CredentialApprovalTest::testNewCredentialsWaitAndTheQrSaysNotYetIssued`. **Demo:** as MSW Head, verify both documents, then approve with remarks. | A member with a member number, and an ID and certificate waiting for approval. | Passed | Pass |
| TC-08 | Roles | Role expires | **Automated:** `EligibilityTest::testFailedSubjectFlagsThenConfirmIneligibleExpiresTheRole`, `MemberRoleTest::testChangeRoleToGeneralMemberRevokesIssuesAndAddsHistory`. | Active General Member, the old ID revoked, a new one waiting, and a notice sent. | Passed | Pass |
| TC-09 | Recruitment | HR request notifies the MSW Head | **Automated:** `RecruitmentTest::testSubmittingNotifiesTheMswHead`. **Demo:** as `head@agile.test`, file an HR request. Check the MSW Head's bell. | The MSW Head is notified. | Passed | Pass |
| TC-10 | Recruitment | Committee Head cannot create a vacancy | **Automated:** `RecruitmentTest::testACommitteeHeadCannotCreateAVacancy`, `HttpAccessTest::testEveryProtectedRouteFollowsSectionSix`. **Demo:** as `head@agile.test`, open `/recruitment/vacancies/create`. | 403. | Passed | Pass |
| TC-11 | Recruitment | Interview invite and 24-hour reminder | **Automated:** `AppointmentFlowTest::testReminderSendsOnceAndReschedulingArmsItAgain`. **Manual, for the invite:** as MSW Member, schedule an interview on an application. Run the queue job. | An invite in `storage/mail`. The reminder goes once, 24 hours before. | Automated: Passed. Manual: | |
| TC-12 | Recruitment | Appointment saved only after approval | **Automated:** `AppointmentFlowTest::testPreparedChangesNothingAndApprovalChangesEverything`. | Preparing changes nothing. Approval gives the position and updates the slots. | Passed | Pass |
| TC-13 | Welfare | A case in all 7 categories | **Automated:** `WelfareRulesTest::testEachCategoryIsSavedAndTheTextIsEncrypted`. | Saved in each category, with the text encrypted. | Passed | Pass |
| TC-14 | Welfare | Harassment case alerts handlers | **Automated:** `WelfareRulesTest::testOnlyHarassmentOrUrgentAlertsTheHandlers`. **Demo:** as a member, submit a Harassment/Bullying concern. Check the bell for MSW Head and President. | An urgent alert to both. | Passed | Pass |
| TC-15 | Welfare | Other roles denied and logged | **Automated:** `HttpFlowsTest::testTc15OtherRolesOpeningACaseAreDeniedAndLogged`, `BusinessRulesTest::testBr06EveryCaseViewIsLoggedAndOthersAreRefused`. **Demo:** as `mswmember@agile.test`, open `/welfare/1`. | 403, and a `welfare.access_denied` audit row. | Passed | Pass |
| TC-16 | Welfare | President adds a note but cannot close | **Automated:** `WelfareCaseTest::testThePresidentHandlesButCannotAssignCloseOrDispose`. **Demo:** as President, open a case. | Can add a note. No assign or close controls, and a direct POST gets 403. | Passed | Pass |
| TC-17 | Welfare | Closed case cannot reopen | **Automated:** `WelfareCaseTest::testCloseOnlyFromResolvedThenNothingChanges`, `WelfareRulesTest::testAClosedCaseCannotChange`. | Every change to a closed case is refused. | Passed | Pass |
| TC-18 | Welfare | Closure, disposition, student notified | **Automated:** `BusinessRulesTest::testTc18ClosureThenDispositionIsRecordedOnce`. **Manual, for a real file:** as a member, submit a concern with a PDF. As MSW Head, resolve, close, then choose Delete file. | The disposition saves once and only after closure, the file can no longer be opened, and the student is notified. | Automated: Passed. Manual: | |
| TC-19 | Login | 5 wrong passwords lock for 15 minutes | **Automated:** `HttpFlowsTest::testTc19FiveWrongPasswordsLockTheAccount`. **Demo:** enter a wrong password 5 times. As MSW Head, unlock the account afterward. | Locked for 15 minutes, with the same generic message, even with the right password. | Passed | Pass |
| TC-20 | Dashboards | Execomm dashboard is read-only | **Automated:** `HttpFlowsTest::testTc20ExecommDashboardIsReadOnly`. **Demo:** log in as `execomm@agile.test`. | "View only" shows, with no action controls, and a direct POST gets 403. | Passed | Pass |
| TC-21 | Security | Missing CSRF gets 419 | **Automated:** `HttpAccessTest::testEveryPostRouteAnswers419WithoutAValidToken`, `HttpFlowsTest::testTc21AMissingTokenGets419`. | 419 on every POST route. | Passed | Pass |
| TC-22 | Security | SQL and HTML injection in search | **Automated:** `HttpFlowsTest::testTc22InjectionInSearchIsHarmless`. **Demo:** as MSW Head, search for `<script>alert(1)</script>`. | Nothing runs, the text is shown as typed, and no data changes. | Passed | Pass |
| TC-23 | Content | Not public until approved | **Automated:** `HttpFlowsTest::testTc23AnUnapprovedPostIsNotPublic`, `BusinessRulesTest::testBr12ContentIsPublicOnlyAfterTheMswHeadApproves`. **Demo:** as `head@agile.test`, draft and submit a post, then look at `/announcements`. | Not listed until the MSW Head publishes it. | Passed | Pass |
| TC-24 | Email | Failed email retried up to 3 times | **Automated:** `BusinessRulesTest::testTc24AFailedEmailIsTriedThreeTimes`. | Queued after tries 1 and 2, Failed after try 3, and no fourth try. | Passed | Pass |
| TC-25 | Eligibility | New term lists only covered officers | **Automated:** `EligibilityTest::testScreeningCoversOnlyOfficers`. **Demo:** as MSW Head, go to Eligibility screening and start one. | Officers only. General Members are left out. | Passed | Pass |
| TC-26 | Eligibility | Failed subject flags the officer | **Automated:** `EligibilityTest::testFailedSubjectFlagsThenConfirmIneligibleExpiresTheRole`. | Flagged and notified. | Passed | Pass |
| TC-27 | Eligibility | Confirm ineligible | **Automated:** `EligibilityTest::testFailedSubjectFlagsThenConfirmIneligibleExpiresTheRole`. | The role expires, and they stay an Active member. | Passed | Pass |
| TC-28 | AI | Suggestion not saved until confirmed | **Automated:** `AiExtractionTest::testASuggestionLeavesTheScreeningUnchanged`, `AiExtractionTest::testSavingUsesTheFormNotTheSuggestion`. | The screening is unchanged until the reviewer saves. | Passed | Pass |
| TC-29 | Welfare | Guest submits after an email code | **Automated:** `HttpWholeFlowsTest::testTc29AndTc30AGuestSubmitsAndTracksAConcern`. **Demo:** in a private window, open `/ask-for-help`. | A case code shows, and WELFARE_RECEIVED is emailed. | Passed | Pass |
| TC-30 | Welfare | Guest tracks the case | **Automated:** `HttpWholeFlowsTest::testTc29AndTc30AGuestSubmitsAndTracksAConcern`, `BusinessRulesTest::testBr17AGuestTracksACaseOnlyWithTheMatchingEmail`. **Demo:** on `/track`, enter that code and email. | Status and student-visible updates only. | Passed | Pass |
| TC-31 | Applications | Apply without an account | **Automated:** `HttpWholeFlowsTest::testTc04AndTc31ApplyWithoutAnAccountThenApproval`, `BusinessRulesTest::testBr18EditLinksAreSingleUse`. **Manual, for the revision link:** as MSW Head, return an application. Open the emailed link, then open it again. | Member number with no account, and the email explains `/register`. The link works once. | Automated: Passed. Manual: | |
| TC-32 | Registration | `/register` | **Automated:** `AccountsAndDecisionsTest::testApprovalMakesAMemberWithNoAccountThenTheyRegister`, `AccountsAndDecisionsTest::testRegistrationOnlyWorksForAnApprovedApplicant`, `AccountsAndDecisionsTest::testRegisteringTwiceIsImpossible`, `AccountsAndDecisionsTest::testTheSixthTryIsBlockedPerStudentNumberFromAnyAddress`, `BusinessRulesTest::testBr18ARightCodeOnlyWorksForAnApprovedMemberWithNoAccount`. **Demo:** on `/register`, use `2024-00114-OU-0`. Then try a random number. | A code goes to the application's email and the account waits. A random number gets the same message with no code. | Passed | Pass |
| TC-33 | Credentials | Hidden until approved | **Automated:** `CredentialApprovalTest::testNewCredentialsWaitAndTheQrSaysNotYetIssued`, `CredentialApprovalTest::testApprovingRegeneratesTheFileAndEmailsTheMemberOnce`, `CredentialApprovalTest::testReturnNeedsRemarksAndRegenerateMakesANewPendingFile`, `CredentialApprovalTest::testARoleChangeRevokesAtOnceAndTheNewOneWaits`, `CredentialApprovalTest::testOnlyTheMswHeadActs`. **Manual, for the QR:** approve one, then open the QR link from the PDF. | The QR says "Not yet issued", then "Valid". Downloads appear only after approval. | Automated: Passed. Manual: | |
| TC-34 | Applications | Who decides | **Automated:** `AccountsAndDecisionsTest::testCommitteeDeciderFollowsTheTable`, `AccountsAndDecisionsTest::testOnlyTheCommitteesHeadDecidesItsPosition`, `AccountsAndDecisionsTest::testMswHeadDecidesWhenTheCommitteeHasNoHead`, `AccountsAndDecisionsTest::testAcceptingIsNotTheAppointment`. | Each application is decided only by the person in BR-20, and others get 403 and an audit row. | Passed | Pass |
| TC-35 | Accounts | Pending accounts | **Automated:** `AccountsAndDecisionsTest::testRegisteredAccountWaitsForApprovalAndARejectionNeedsAReason`, `BusinessRulesTest::testBr21APendingAccountCannotLogIn`. **Demo:** log in with a newly registered account, then approve it as MSW Head. | The waiting message shows until approved. A rejection needs remarks. | Passed | Pass |

## Before the demo: what only a person can check

Tests cannot judge these, so go through them once on your real site:

- [ ] **Look and layout:** home, application wizard, a dashboard and a welfare case at 360 and at 1366.
- [ ] **Keyboard:** press Tab through the login page and a form. The focus ring should always show.
- [ ] **Real email:** set `MAIL_DRIVER=smtp`, send one email to yourself, and check the banner, button and footer in Gmail.
- [ ] **PDFs:** open an approved ID and certificate, and scan the QR code with a phone.

## Business rules

| Rule | What it says | Tests |
|---|---|---|
| BR-02 | One active application per vacancy per person | `AccountsAndDecisionsTest::testBrTwoSeesAcrossAccountsAndApplicants` |
| BR-03 | Approval needs all required documents verified | `BusinessRulesTest::testBr03ApprovalWaitsForEveryRequiredDocument` |
| BR-04 | Only the MSW Head creates vacancies, from an approved request, and they close on their own | `RecruitmentTest::testACommitteeHeadCannotCreateAVacancy`, `RecruitmentTest::testAVacancyNeedsAnApprovedRequest`, `RecruitmentTest::testJobClosesPastVacanciesOnceAndNotifiesTheHead`, `RecruitmentTest::testJobMarksFullVacancyFilledAndLeavesOthersAlone` |
| BR-05 | One evaluation per evaluator, and only the MSW Head approves appointments | `AppointmentFlowTest::testASecondEvaluationUpdatesAndTwoEvaluatorsCount`, `BusinessRulesTest::testBr05OnlyTheMswHeadApprovesAppointments` |
| BR-06 | Welfare only for MSW Head and President, and every view logged | `BusinessRulesTest::testBr06EveryCaseViewIsLoggedAndOthersAreRefused`, `WelfareCaseTest::testEveryOtherRoleIsRefusedInsideTheService` |
| BR-07 | Only the MSW Head closes, and the 24-hour rule | `WelfareCaseTest::testThePresidentHandlesButCannotAssignCloseOrDispose`, `WelfareCaseTest::testOverdueRule`, `WelfareCaseTest::testRemindersSendOnce` |
| BR-08 | Screening covers officers only | `EligibilityTest::testScreeningCoversOnlyOfficers` |
| BR-09 | Eligible means no failed subject and enrolled | `EligibilityTest::testBrNineResult` |
| BR-10 | Role expiry keeps Active membership, and the MSW Head cannot review their own | `EligibilityTest::testFailedSubjectFlagsThenConfirmIneligibleExpiresTheRole`, `EligibilityTest::testTheMswHeadCannotReviewTheirOwnScreening`, `RemindersAndGuardsTest::testTheLastMswHeadCannotLoseTheRole` |
| BR-11 | AI is not saved without confirmation | `AiExtractionTest::testASuggestionLeavesTheScreeningUnchanged`, `AiExtractionTest::testSavingUsesTheFormNotTheSuggestion` |
| BR-12 | Content is public only after approval | `BusinessRulesTest::testBr12ContentIsPublicOnlyAfterTheMswHeadApproves` |
| BR-13 | Active is the only membership status | `BusinessRulesTest::testBr13ActiveIsTheOnlyMembershipStatus` |
| BR-14 | Decisions need remarks | `BusinessRulesTest::testBr14ApplicationDecisionsNeedRemarks`, `RecruitmentTest::testDecisionsNeedRemarks`, `EligibilityTest::testRemarksAreRequired`, `CredentialApprovalTest::testReturnNeedsRemarksAndRegenerateMakesANewPendingFile` |
| BR-15 | Closed cases stay closed | `WelfareCaseTest::testCloseOnlyFromResolvedThenNothingChanges`, `WelfareRulesTest::testAClosedCaseCannotChange`, `BusinessRulesTest::testTc18ClosureThenDispositionIsRecordedOnce` |
| BR-17 | Guest welfare: wrong or expired codes refused, tracking needs the matching email | `WelfareRulesTest::testCodeIsHashedExpiresAndLocksAfterFiveWrongTries`, `BusinessRulesTest::testBr17AGuestTracksACaseOnlyWithTheMatchingEmail`, `HttpWholeFlowsTest::testTc29AndTc30AGuestSubmitsAndTracksAConcern` |
| BR-18 | No accounts before approval, codes expire, links expire and work once | `BusinessRulesTest::testBr18ApprovalCreatesNoAccount`, `BusinessRulesTest::testBr18ARightCodeOnlyWorksForAnApprovedMemberWithNoAccount`, `BusinessRulesTest::testBr18ARegistrationCodeExpires`, `BusinessRulesTest::testBr18EditLinksExpire`, `BusinessRulesTest::testBr18EditLinksAreSingleUse`, `BusinessRulesTest::testBr18ANewLinkCancelsTheOlderOne` |
| BR-19 | Credentials hidden until approved | `CredentialApprovalTest::testNewCredentialsWaitAndTheQrSaysNotYetIssued`, `CredentialApprovalTest::testFilesFollowTheStatus`, `CredentialApprovalTest::testOnlyTheMswHeadActs` |
| BR-20 | Who decides | `AccountsAndDecisionsTest::testCommitteeDeciderFollowsTheTable`, `AccountsAndDecisionsTest::testOnlyTheCommitteesHeadDecidesItsPosition`, `AccountsAndDecisionsTest::testMswHeadDecidesWhenTheCommitteeHasNoHead` |
| BR-21 | Pending accounts cannot log in | `BusinessRulesTest::testBr21APendingAccountCannotLogIn`, `AccountsAndDecisionsTest::testRegisteredAccountWaitsForApprovalAndARejectionNeedsAReason` |

**§8 status lifecycles:** `TransitionMapsTest::testOnlySectionEightMovesAreAllowed` checks every pair of statuses for all ten maps.

**§6 permissions:** `HttpAccessTest::testEveryProtectedRouteFollowsSectionSix` checks every protected route for every demo role over real HTTP, against the table in `docs/PROJECT_CONTEXT.md`.

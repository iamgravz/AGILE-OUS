<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\ForbiddenException;
use DomainException;
use App\Models\ApplicantRepository;
use App\Models\MemberRepository;
use App\Models\MembershipApplicationRepository;
use App\Models\RoleRepository;
use App\Models\UserRepository;
use App\Models\VacancyRepository;
use App\Services\AccountApprovalService;
use App\Services\AuthService;
use App\Services\MemberRegistrationService;
use App\Services\CredentialService;
use App\Services\EmailQueueService;
use App\Services\Mail\MailerInterface;
use App\Services\TrackingService;
use App\Services\WelfareService;
use App\Services\MembershipService;
use App\Services\RbacService;
use PHPUnit\Framework\TestCase;

/** BR-18, BR-20, BR-21. Needs the seeded database. Everything rolls back. */
final class AccountsAndDecisionsTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SESSION['user_id']);
        RbacService::clearCache();
    }

    public function testCommitteeDeciderFollowsTheTable(): void
    {
        $service = new MembershipService();
        $mswCommittee = config('role_mapping.msw_committee');
        $row = static fn (?int $head, ?string $committee, ?int $owner, string $roleType = 'committee_member'): array => ['committee_head_id' => $head, 'committee_name' => $committee, 'owner_user_id' => $owner, 'role_type' => $roleType];

        $this->assertSame(5, $service->committeeDecider($row(5, 'Events and External Relations', 9)));
        $this->assertSame(5, $service->committeeDecider($row(5, 'Events and External Relations', null)));
        $this->assertNull($service->committeeDecider($row(null, null, 9)), 'General or executive');
        $this->assertNull($service->committeeDecider($row(5, $mswCommittee, 9)), 'MSW position');
        $this->assertNull($service->committeeDecider($row(null, 'Events and External Relations', 9)), 'No head');
        $this->assertNull($service->committeeDecider($row(5, 'Events and External Relations', 5)), 'Applying to their own committee');
        $this->assertSame(5, $service->committeeDecider($row(5, 'Events and External Relations', 9, 'deputy')), 'Deputy: the committee head (R6)');
        $this->assertNull($service->committeeDecider($row(5, 'Events and External Relations', 9, 'committee_head')), 'Committee Head position: the MSW Committee Head (R6)');
        $this->assertNull($service->committeeDecider($row(null, 'The Source Code', 9, 'source_code')), 'The Source Code: the MSW Committee Head (R6)');
    }

    public function testOnlyTheCommitteesHeadDecidesItsPosition(): void
    {
        $this->inRollback(function (): void {
            $id = $this->application($this->vacancyIn('Academics and Training'), $this->userId('nomember@agile.test'), null);
            $otherHead = $this->otherCommitteeHead();

            foreach ([$otherHead, $this->userId('mswhead@agile.test'), $this->userId('mswmember@agile.test')] as $userId) {
                $this->signInId($userId);

                try {
                    (new MembershipService())->approve($id, 'No');
                    $this->fail('Only the committee head may decide.');
                } catch (ForbiddenException $exception) {
                    $this->assertSame('application.decide', $exception->permission);
                }
            }

            $this->signInId($this->userId('head@agile.test'));
            $service = new MembershipService();
            $this->assertTrue($service->canDecide((new MembershipApplicationRepository())->findDetailed($id)));
            $service->approve($id, 'Good fit');
            $this->assertSame('approved', (new MembershipApplicationRepository())->find($id)['status']);
        });
    }

    public function testTheDeciderCanWaitlistAndDecideLater(): void
    {
        $this->inRollback(function (): void {
            $id = $this->application($this->vacancyIn('Academics and Training'), $this->userId('nomember@agile.test'), null);
            $repository = new MembershipApplicationRepository();

            // Someone who is not the decider cannot waitlist.
            $this->signInId($this->userId('mswhead@agile.test'));

            try {
                (new MembershipService())->waitlist($id, 'No slot');
                $this->fail('Only the decider may waitlist.');
            } catch (ForbiddenException) {
                $this->addToAssertionCount(1);
            }

            $this->signInId($this->userId('head@agile.test'));
            $service = new MembershipService();

            try {
                $service->waitlist($id, '   ');
                $this->fail('Remarks are required.');
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }

            $service->waitlist($id, 'Qualified, but all slots are filled for now.');
            $this->assertSame('waitlisted', $repository->find($id)['status']);
            $this->assertSame(1, (int) Database::connection()->query(
                "SELECT COUNT(*) FROM notifications n JOIN email_templates t ON t.template_id = n.template_id WHERE t.code = 'APP_WAITLISTED' AND n.channel = 'email'"
            )->fetchColumn());

            // Waitlisted is not final: the same decider approves it when a slot opens.
            $this->assertTrue($service->canDecide($repository->findDetailed($id)));
            $service->approve($id, 'A slot opened.');
            $this->assertSame('approved', $repository->find($id)['status']);
            $this->assertSame(['waitlisted', 'approved'], array_slice(array_column($repository->historyWithActors($id), 'new_status'), -2));
        });
    }

    public function testAWaitlistedApplicationCannotBeWaitlistedAgainOrReturned(): void
    {
        $this->assertTrue(\App\Services\ApplicationStatus::canTransition('under_review', 'waitlisted'));
        $this->assertTrue(\App\Services\ApplicationStatus::canTransition('waitlisted', 'rejected'));
        $this->assertFalse(\App\Services\ApplicationStatus::canTransition('waitlisted', 'waitlisted'));
        $this->assertFalse(\App\Services\ApplicationStatus::canTransition('waitlisted', 'for_revision'));
        $this->assertFalse(\App\Services\ApplicationStatus::canTransition('submitted', 'waitlisted'));
    }

    public function testDeciderNamesWhoMustDecide(): void
    {
        $this->inRollback(function (): void {
            $service = new MembershipService();
            $repository = new MembershipApplicationRepository();
            $vacancyId = $this->vacancyIn('Academics and Training');
            $committee = $repository->findDetailed($this->application($vacancyId, $this->userId('nomember@agile.test'), null));
            $this->assertSame([$this->userId('head@agile.test')], $service->decider($committee)['user_ids']);
            $this->assertStringEndsWith('Academics and Training Committee Head', $service->decider($committee)['label']);

            $own = $repository->findDetailed($this->application($vacancyId, $this->userId('head@agile.test'), null));
            $this->assertSame([$this->userId('mswhead@agile.test')], $service->decider($own)['user_ids'], 'A head applying to their own committee.');
            $this->assertStringEndsWith('MSW Committee Head', $service->decider($own)['label']);

            $applicantId = (new ApplicantRepository())->insert(['email' => 'general.one@example.test', 'first_name' => 'G', 'last_name' => 'O', 'student_no' => '2025-99970-OU-0']);
            $general = $repository->findDetailed($this->application(null, null, $applicantId));
            $this->assertSame([$this->userId('mswhead@agile.test')], $service->decider($general)['user_ids']);

            foreach (['head@agile.test', 'mswmember@agile.test'] as $email) {
                $this->signIn($email);
                $this->assertFalse($service->canDecide($general), $email);
            }

            Database::connection()->prepare("UPDATE users SET status = 'disabled' WHERE email = 'head@agile.test'")->execute();
            $disabledHead = $repository->findDetailed((int) $committee['application_id']);
            $this->assertSame([$this->userId('mswhead@agile.test')], $service->decider($disabledHead)['user_ids'], 'A disabled head cannot decide.');
        });
    }

    public function testAcceptingIsNotTheAppointment(): void
    {
        $this->inRollback(function (): void {
            $vacancyId = $this->vacancyIn('Academics and Training');
            $userId = $this->userId('nomember@agile.test');
            $id = $this->application($vacancyId, $userId, null);
            $this->signIn('head@agile.test');
            (new MembershipService())->approve($id, 'Good fit');

            $this->assertSame('approved', (new MembershipApplicationRepository())->find($id)['status']);
            $this->assertSame(0, (int) (new VacancyRepository())->find($vacancyId)['slots_filled'], 'The MSW Head still approves the appointment.');
            $this->assertSame('general_member', (new RoleRepository())->find((int) (new UserRepository())->find($userId)['role_id'])['slug']);
        });
    }

    public function testApproveSelectedAndTheDigest(): void
    {
        $this->inRollback(function (): void {
            Database::connection()->exec("UPDATE users SET status = 'disabled' WHERE status = 'pending'");
            $this->assertSame(0, (new AccountApprovalService())->sendDigest());
            $first = $this->registeredApplicant('sel.one@example.test', '2025-99971-OU-0');
            $second = $this->registeredApplicant('sel.two@example.test', '2025-99972-OU-0');
            $approvals = new AccountApprovalService();

            $this->assertSame(1, $approvals->sendDigest());
            $this->assertSame(0, $approvals->sendDigest(), 'Once a day.');
            $this->assertSame(2, $approvals->approveSelected([$first, $second, 999999]));
            $this->assertSame('active', (new UserRepository())->find($second)['status']);
        });
    }

    public function testMswHeadDecidesWhenTheCommitteeHasNoHead(): void
    {
        $this->inRollback(function (): void {
            $vacancyId = $this->vacancyIn('Academics and Training');
            Database::connection()->exec("UPDATE committees SET head_user_id = NULL WHERE name = 'Academics and Training'");
            $id = $this->application($vacancyId, $this->userId('nomember@agile.test'), null);

            $this->signIn('head@agile.test');
            $this->assertFalse((new MembershipService())->canDecide((new MembershipApplicationRepository())->findDetailed($id)));

            $this->signIn('mswhead@agile.test');
            (new MembershipService())->reject($id, 'Not this time');
            $this->assertSame('rejected', (new MembershipApplicationRepository())->find($id)['status']);
        });
    }

    public function testDeciderIsEmailedOnceTheApplicationIsReady(): void
    {
        $this->inRollback(function (): void {
            $id = $this->application($this->vacancyIn('Academics and Training'), $this->userId('nomember@agile.test'), null, 'pending');
            $head = $this->userId('head@agile.test');
            $documents = Database::connection()->query("SELECT document_id FROM application_documents WHERE application_id = {$id} ORDER BY document_id")->fetchAll(PDO::FETCH_COLUMN);

            $this->signIn('mswmember@agile.test');
            (new MembershipService())->verifyDocument($id, (int) $documents[0], 'verified', '');
            $this->assertSame(0, $this->countMail($head, 'application is waiting'));

            (new MembershipService())->verifyDocument($id, (int) $documents[1], 'verified', '');
            $this->assertSame(1, $this->countMail($head, 'application is waiting'));
        });
    }

    public function testApprovalMakesAMemberWithNoAccountThenTheyRegister(): void
    {
        $this->inRollback(function (): void {
            $email = 'new.applicant@example.test';
            $applicants = new ApplicantRepository();
            $applicantId = $applicants->insert(['email' => $email, 'first_name' => 'New', 'last_name' => 'Applicant', 'student_no' => '2025-99991-OU-0', 'contact_no' => '09171112222', 'email_verified_at' => date('Y-m-d H:i:s')]);
            $id = $this->application(null, null, $applicantId);

            $this->signIn('mswhead@agile.test');
            (new MembershipService())->approve($id, 'Welcome');

            $users = new UserRepository();
            $members = new MemberRepository();
            $this->assertNull($users->findByEmail($email), 'Approval creates no account.');
            $member = $members->findByApplicant($applicantId);
            $this->assertNull($member['user_id']);
            $this->assertNotEmpty($member['member_no']);
            $detail = $members->findDetailed((int) $member['member_id']);
            $this->assertSame('New', $detail['first_name']);
            $this->assertSame('General Member', $detail['role_name']);
            $this->assertSame('2025-99991-OU-0', $detail['student_no']);
            $mail = Database::connection()->prepare("SELECT payload_json FROM notifications WHERE recipient_email = ? AND title LIKE '%application was approved%'");
            $mail->execute([$email]);
            $this->assertStringContainsString('/register', (string) $mail->fetchColumn());

            $registration = new MemberRegistrationService();
            $this->assertTrue($registration->requestCode('2025-99991-OU-0', '198.51.100.' . random_int(1, 250)));
            $this->assertNull($registration->confirmCode('2025-99991-OU-0', 'nope'));
            unset($_SESSION['user_id']);
            (new AuthService())->registerMember((int) $member['member_id'], 'Password123!');

            $user = $users->findByEmail($email);
            $userId = (int) $user['user_id'];
            $this->assertSame('pending', $user['status']);
            $this->assertSame('general_member', (new RoleRepository())->find((int) $user['role_id'])['slug']);
            $this->assertSame($userId, (int) $applicants->find($applicantId)['user_id']);
            $this->assertSame($userId, (int) $members->find((int) $member['member_id'])['user_id']);
            $this->assertNotNull($members->find((int) $member['member_id'])['registered_at']);
            $this->assertNull((new MembershipApplicationRepository())->find($id)['user_id'], 'Exactly one of applicant_id and user_id is set.');

            $this->signIn('mswhead@agile.test');
            $pending = array_values(array_filter((new AccountApprovalService())->pending(), static fn (array $row): bool => (int) $row['user_id'] === $userId));
            $this->assertSame($id, (int) $pending[0]['approved_application_id']);
            $this->assertSame($member['member_no'], $pending[0]['member_no']);

            (new AccountApprovalService())->approve($userId);
            $this->assertSame('active', $users->find($userId)['status']);
            $this->assertSame(1, $this->countMail($userId, 'account was approved'));
        });
    }

    public function testRegistrationOnlyWorksForAnApprovedApplicant(): void
    {
        $this->inRollback(function (): void {
            $registration = new MemberRegistrationService();
            $members = new MemberRepository();

            $this->assertNull($members->findUnregisteredByStudentNo('2099-99999-OU-0'), 'Unknown number');
            $applicantId = (new ApplicantRepository())->insert(['email' => 'waiting@example.test', 'first_name' => 'W', 'last_name' => 'A', 'student_no' => '2025-99980-OU-0', 'email_verified_at' => date('Y-m-d H:i:s')]);
            $this->application(null, null, $applicantId, 'pending');
            $this->assertNull($members->findUnregisteredByStudentNo('2025-99980-OU-0'), 'Not approved yet');
            $this->assertNull($registration->confirmCode('2025-99980-OU-0', '123456'));
        });
    }

    public function testRegisteringTwiceIsImpossible(): void
    {
        $this->inRollback(function (): void {
            $userId = $this->registeredApplicant('twice@example.test', '2025-99981-OU-0');
            $memberId = (int) (new MemberRepository())->findByUser($userId)['member_id'];
            $this->assertNull((new MemberRepository())->findUnregisteredByStudentNo('2025-99981-OU-0'));

            $this->expectException(DomainException::class);
            (new AuthService())->registerMember($memberId, 'Password123!');
        });
    }

    public function testTheSixthTryIsBlockedPerStudentNumberFromAnyAddress(): void
    {
        $this->inRollback(function (): void {
            $registration = new MemberRegistrationService();
            $number = '2099-' . random_int(10000, 99999) . '-OU-0';

            for ($i = 1; $i <= 5; $i++) {
                $this->assertTrue($registration->requestCode($number, "198.51.100.{$i}"), "Try {$i}");
            }

            $this->assertFalse($registration->requestCode($number, '198.51.100.6'));
        });
    }

    public function testRegistrationRemindersComeAtSevenAndFourteenDaysOnly(): void
    {
        $this->inRollback(function (): void {
            $applicantId = (new ApplicantRepository())->insert(['email' => 'reminder.me@example.test', 'first_name' => 'R', 'last_name' => 'M', 'student_no' => '2025-99982-OU-0', 'contact_no' => '09171112226', 'email_verified_at' => date('Y-m-d H:i:s')]);
            $id = $this->application(null, null, $applicantId);
            $this->signIn('mswhead@agile.test');
            (new MembershipService())->approve($id, 'Welcome');
            $members = new MemberRepository();
            $memberId = (int) $members->findByApplicant($applicantId)['member_id'];
            $records = new App\Services\MemberRecordsService();
            $mine = fn (): array => array_values(array_filter($members->dueForRegistrationReminder(date('Y-m-d')), static fn (array $row): bool => (int) $row['member_id'] === $memberId));
            $mails = function (): int {
                $count = Database::connection()->prepare("SELECT COUNT(*) FROM notifications WHERE recipient_email = 'reminder.me@example.test' AND title LIKE 'Create your AGILE account%'");
                $count->execute();

                return (int) $count->fetchColumn();
            };

            $this->assertSame([], $mine(), 'Not before 7 days.');

            $members->update($memberId, ['joined_at' => date('Y-m-d', strtotime('-7 days'))]);
            $records->sendRegistrationReminders(static function (string $line): void {
            });
            $this->assertSame(1, $mails());
            $records->sendRegistrationReminders(static function (string $line): void {
            });
            $this->assertSame(1, $mails(), 'A second run sends nothing.');

            $members->update($memberId, ['joined_at' => date('Y-m-d', strtotime('-14 days'))]);
            $records->sendRegistrationReminders(static function (string $line): void {
            });
            $records->sendRegistrationReminders(static function (string $line): void {
            });
            $this->assertSame(2, $mails());

            $records->resendRegistration($memberId);
            $this->assertSame(3, $mails());
            $this->assertContains($memberId, array_map(static fn (array $row): int => (int) $row['member_id'], $members->search(['registration' => 'unregistered'], 1)['data']));

            $this->signIn('mswmember@agile.test');
            $this->expectException(ForbiddenException::class);
            $records->resendRegistration($memberId);
        });
    }

    public function testOfficerAccountsSetAPasswordFromTheEmailedLink(): void
    {
        $this->inRollback(function (): void {
            $auth = new AuthService();
            $email = 'new.officer.' . bin2hex(random_bytes(3)) . '@example.test';
            $userId = $auth->createAccount($email, 'New', 'Officer', 'msw_member');
            $user = (new UserRepository())->find($userId);
            $this->assertSame('active', $user['status']);
            $this->assertSame('', $user['password_hash']);
            $this->assertSame(AuthService::LOGIN_FAILED, $auth->attempt($email, 'Password123!', '203.0.113.20'));

            $statement = Database::connection()->prepare("SELECT payload_json FROM notifications WHERE user_id = ? AND title LIKE '%password%' ORDER BY notification_id DESC LIMIT 1");
            $statement->execute([$userId]);
            parse_str((string) parse_url(json_decode((string) $statement->fetchColumn(), true)['link'], PHP_URL_QUERY), $query);

            $this->assertTrue($auth->setPassword($query['token'], 'Password123!'));
            $this->assertFalse($auth->setPassword($query['token'], 'Password123!'), 'The link works once.');
            $this->assertTrue(password_verify('Password123!', (new UserRepository())->find($userId)['password_hash']));

            $this->expectException(DomainException::class);
            $auth->createAccount($email, 'Again', 'Officer', 'msw_member');
        });
    }

    public function testRegisteredAccountWaitsForApprovalAndARejectionNeedsAReason(): void
    {
        $this->inRollback(function (): void {
            $auth = new AuthService();
            $email = 'registered.' . bin2hex(random_bytes(3)) . '@example.test';
            $userId = $this->registeredApplicant($email, '2025-99992-OU-0');

            $this->assertSame(AuthService::LOGIN_PENDING, $auth->attempt($email, 'Password123!', '203.0.113.9'));
            $this->assertSame(AuthService::LOGIN_FAILED, $auth->attempt($email, 'Wrong123!', '203.0.113.10'));

            $this->signIn('mswhead@agile.test');
            $approvals = new AccountApprovalService();

            try {
                $approvals->reject($userId, '   ');
                $this->fail('A rejection needs a reason.');
            } catch (DomainException) {
                $this->assertSame('pending', (new UserRepository())->find($userId)['status']);
            }

            $memberId = (int) (new MemberRepository())->findByUser($userId)['member_id'];
            $approvals->reject($userId, 'Not a BSIT-OU student');
            $rejected = (new UserRepository())->find($userId);
            $this->assertSame('disabled', $rejected['status']);
            $this->assertSame('Not a BSIT-OU student', $rejected['rejection_remarks']);
            $this->assertSame(AuthService::LOGIN_FAILED, $auth->attempt($email, 'Password123!', '203.0.113.11'));
            $sent = Database::connection()->prepare("SELECT COUNT(*) FROM notifications WHERE recipient_email = ? AND title LIKE '%update on your AGILE account%'");
            $sent->execute([$email]);
            $this->assertSame(1, (int) $sent->fetchColumn(), 'The email goes to their real address.');
            $this->assertNull((new MemberRepository())->find($memberId)['user_id']);
            $this->assertNotNull((new MemberRepository())->findUnregisteredByStudentNo('2025-99992-OU-0'), 'The student number may register again.');

            $auth->registerMember($memberId, 'Password123!');
            $again = (int) (new UserRepository())->findByEmail($email)['user_id'];
            $this->assertNotSame($userId, $again);
            $approvals->approve($again);
            $approved = (new UserRepository())->find($again);
            $this->assertSame($this->userId('mswhead@agile.test'), (int) $approved['approved_by']);
            $this->assertNotNull($approved['approved_at']);
            $waiting = Database::connection()->prepare("SELECT COUNT(*) FROM digital_credentials WHERE member_id = ? AND revoked_at IS NULL AND status = 'pending_approval'");
            $waiting->execute([$memberId]);
            $this->assertSame(2, (int) $waiting->fetchColumn(), 'Revoked on rejection, so generated again for review.');

            $this->expectException(DomainException::class);
            $approvals->approve($userId);
        });
    }

    public function testOnlyTheMswHeadApprovesAccounts(): void
    {
        foreach (['head@agile.test', 'mswmember@agile.test', 'president@agile.test', 'member@agile.test'] as $email) {
            $this->signIn($email);

            try {
                (new AccountApprovalService())->pending();
                $this->fail("{$email} should be refused.");
            } catch (ForbiddenException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testTheQueueErasesEveryLiveTokenAfterSending(): void
    {
        $this->inRollback(function (): void {
            $notifications = new App\Services\NotificationService();
            $userId = $this->userId('member@agile.test');
            $ids = [
                ...$notifications->notify($userId, 'ACCOUNT_SETUP', ['link' => '/account/setup?token=abc'], null, ['email']),
                $notifications->notifyEmail('x@example.test', 'APPLICATION_LINK', ['name' => 'X', 'message' => 'm', 'action' => 'a', 'link' => '/apply/continue?token=abc']),
                $notifications->notifyEmail('x@example.test', 'APP_FOR_REVISION', ['name' => 'X', 'tracking_code' => 'C', 'remarks' => 'r', 'link' => '/apply/continue?token=abc']),
            ];
            $kept = $notifications->notifyEmail('x@example.test', 'APP_REJECTED', ['name' => 'X', 'tracking_code' => 'C', 'remarks' => 'keep me']);

            (new EmailQueueService())->run(new class () implements MailerInterface {
                public function send(string $to, string $subject, string $html): void
                {
                }
            }, static function (string $line): void {
            });

            $read = Database::connection()->prepare('SELECT status, payload_json FROM notifications WHERE notification_id = ?');

            foreach ($ids as $id) {
                $read->execute([$id]);
                $row = $read->fetch();
                $this->assertSame('sent', $row['status']);
                $this->assertSame('{}', str_replace(' ', '', $row['payload_json']));
            }

            $read->execute([$kept]);
            $this->assertStringContainsString('keep me', $read->fetch()['payload_json']);
        });
    }

    public function testAskingForALinkCountsTowardTheTrackLimit(): void
    {
        $this->inRollback(function (): void {
            $tracking = new TrackingService();
            $ip = '198.51.100.' . random_int(1, 200);

            for ($i = 0; $i < 5; $i++) {
                $this->assertFalse($tracking->isBlocked($ip));
                $tracking->emailEditLink('WRONGCODE' . $i, 'x@example.test', $ip);
            }

            $this->assertTrue($tracking->isBlocked($ip));
        });
    }

    public function testBrTwoSeesAcrossAccountsAndApplicants(): void
    {
        $this->inRollback(function (): void {
            $user = (new UserRepository())->findByEmail('nomember@agile.test');
            $applicantId = (new ApplicantRepository())->insert(['email' => $user['email'], 'first_name' => 'A', 'last_name' => 'B', 'student_no' => '2025-99993-OU-0', 'email_verified_at' => date('Y-m-d H:i:s')]);
            $this->application(null, null, $applicantId);
            $draft = (new MembershipApplicationRepository())->insert(['user_id' => (int) $user['user_id'], 'tracking_code' => MembershipService::generateTrackingCode(), 'status' => 'draft', 'wizard_step' => 3]);
            $this->signIn('nomember@agile.test');

            try {
                (new MembershipService())->chooseType($draft, null);
                $this->fail('The same email already has an active application.');
            } catch (DomainException $exception) {
                $this->assertStringContainsString('already have an active', $exception->getMessage());
            }

            $repository = new MembershipApplicationRepository();
            $this->assertNotNull($repository->findActiveGeneral('other@example.test', '2025-99993-OU-0', 0), 'Same student number');
            $this->assertNull($repository->findActiveGeneral('other@example.test', '2025-11111-OU-0', 0));
        });
    }

    public function testTheDatabaseRefusesAnApplicationWithNoOwnerOrTwoOwners(): void
    {
        $this->inRollback(function (): void {
            $applicantId = (new ApplicantRepository())->insert(['email' => 'two@example.test', 'first_name' => 'A', 'last_name' => 'B']);

            foreach ([[null, null], [$this->userId('member@agile.test'), $applicantId]] as [$userId, $applicant]) {
                try {
                    (new MembershipApplicationRepository())->insert(['user_id' => $userId, 'applicant_id' => $applicant, 'tracking_code' => MembershipService::generateTrackingCode(), 'status' => 'draft', 'wizard_step' => 1]);
                    $this->fail('The check should refuse this row.');
                } catch (PDOException $exception) {
                    $this->assertStringContainsString('chk_membership_applications_owner', $exception->getMessage());
                }
            }
        });
    }

    public function testRejectingAnAccountRevokesItsCredentials(): void
    {
        $this->inRollback(function (): void {
            $userId = $this->registeredApplicant('revoke.me@example.test', '2025-99994-OU-0');
            $memberId = (int) (new MemberRepository())->findByUser($userId)['member_id'];
            $count = Database::connection()->prepare('SELECT COUNT(*) FROM digital_credentials WHERE member_id = ? AND revoked_at IS NULL');
            $count->execute([$memberId]);
            $this->assertGreaterThan(0, (int) $count->fetchColumn());

            $this->signIn('mswhead@agile.test');
            (new AccountApprovalService())->reject($userId, 'Details did not match');
            $count->execute([$memberId]);
            $this->assertSame(0, (int) $count->fetchColumn());
            $this->assertNotNull((new MemberRepository())->find($memberId), 'BR-13: the member record stays.');
        });
    }

    public function testGuestCasesJoinTheAccountWhenTheyRegister(): void
    {
        $this->inRollback(function (): void {
            $email = 'joins.later@example.test';
            $case = (new WelfareService())->submitAsGuest(['name' => 'J', 'email' => $email, 'student_no' => ''], ['category_id' => '1', 'subject' => 'S', 'description' => 'D', 'urgent' => false], '', []);
            $cases = new App\Models\WelfareCaseRepository();
            $this->assertNull($cases->find((int) $case['case_id'])['user_id']);

            $userId = $this->registeredApplicant($email, '2025-99995-OU-0');
            $this->assertSame($userId, (int) $cases->find((int) $case['case_id'])['user_id']);
        });
    }

    /** An approved applicant who has registered, and whose account is waiting. */
    private function registeredApplicant(string $email, string $studentNo): int
    {
        $applicantId = (new ApplicantRepository())->insert(['email' => $email, 'first_name' => 'R', 'last_name' => 'M', 'student_no' => $studentNo, 'contact_no' => '09171112224', 'email_verified_at' => date('Y-m-d H:i:s')]);
        $id = $this->application(null, null, $applicantId);
        $this->signIn('mswhead@agile.test');
        (new MembershipService())->approve($id, 'Welcome');
        (new AuthService())->registerMember((int) (new MemberRepository())->findByApplicant($applicantId)['member_id'], 'Password123!');

        return (int) (new UserRepository())->findByEmail($email)['user_id'];
    }

    private function application(?int $vacancyId, ?int $userId, ?int $applicantId, string $documents = 'verified'): int
    {
        $id = (new MembershipApplicationRepository())->insert([
            'user_id' => $userId,
            'applicant_id' => $applicantId,
            'vacancy_id' => $vacancyId,
            'tracking_code' => MembershipService::generateTrackingCode(),
            'status' => 'under_review',
            'submitted_at' => date('Y-m-d H:i:s'),
            'wizard_step' => 6,
        ]);

        foreach (['school_id', 'cor'] as $type) {
            $this->insert('application_documents', [
                'application_id' => $id,
                'doc_type' => $type,
                'original_name' => "{$type}.pdf",
                'stored_path' => 'applications/demo/' . md5("demo-{$type}") . '.pdf',
                'mime_type' => 'application/pdf',
                'size_bytes' => 1,
                'verification_status' => $documents,
            ]);
        }

        return $id;
    }

    private function vacancyIn(string $committee): int
    {
        $statement = Database::connection()->prepare('SELECT p.position_id, p.committee_id FROM positions p JOIN committees c ON c.committee_id = p.committee_id WHERE c.name = ? AND p.role_type = \'committee_member\' LIMIT 1');
        $statement->execute([$committee]);
        $position = $statement->fetch();
        $requestId = $this->insert('hr_requests', [
            'committee_id' => $position['committee_id'],
            'position_id' => $position['position_id'],
            'requested_by' => $this->userId('head@agile.test'),
            'slots' => 1,
            'justification' => 'j',
            'qualifications' => 'q',
            'status' => 'approved',
        ]);

        return (new VacancyRepository())->insert([
            'request_id' => $requestId,
            'position_id' => $position['position_id'],
            'title' => 'Test position',
            'description' => 'd',
            'slots' => 1,
            'status' => 'open',
            'opens_at' => date('Y-m-d H:i:s', time() - 3600),
            'closes_at' => date('Y-m-d H:i:s', time() + 86400),
        ]);
    }

    private function otherCommitteeHead(): int
    {
        $userId = (new UserRepository())->insert([
            'role_id' => (new RoleRepository())->findBySlug('committee_head')['role_id'],
            'email' => 'other.head.' . bin2hex(random_bytes(3)) . '@example.test',
            'password_hash' => 'x',
            'first_name' => 'Other',
            'last_name' => 'Head',
            'contact_no' => '09170000000',
            'status' => 'active',
        ]);
        Database::connection()->prepare("UPDATE committees SET head_user_id = ? WHERE name <> 'Academics and Training' AND name <> ? LIMIT 1")->execute([$userId, config('role_mapping.msw_committee')]);

        return $userId;
    }

    private function countMail(int $userId, string $titlePart): int
    {
        $statement = Database::connection()->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND channel = 'email' AND title LIKE ?");
        $statement->execute([$userId, "%{$titlePart}%"]);

        return (int) $statement->fetchColumn();
    }

    /** @param array<string, mixed> $row */
    private function insert(string $table, array $row): int
    {
        $columns = implode(', ', array_keys($row));
        $marks = implode(', ', array_fill(0, count($row), '?'));
        $db = Database::connection();
        $db->prepare("INSERT INTO {$table} ({$columns}) VALUES ({$marks})")->execute(array_values($row));

        return (int) $db->lastInsertId();
    }

    private function signIn(string $email): void
    {
        $this->signInId($this->userId($email));
    }

    private function signInId(int $userId): void
    {
        $_SESSION['user_id'] = $userId;
        RbacService::clearCache();
    }

    private function userId(string $email): int
    {
        return (int) (new UserRepository())->findByEmail($email)['user_id'];
    }

    private function inRollback(callable $test): void
    {
        $sentinel = new RuntimeException('Roll back the test.');

        try {
            Database::transaction(static function () use ($test, $sentinel): void {
                $test();

                throw $sentinel;
            });
        } catch (RuntimeException $exception) {
            if ($exception !== $sentinel) {
                throw $exception;
            }
        }
    }
}

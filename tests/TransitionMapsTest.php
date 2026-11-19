<?php

declare(strict_types=1);

use App\Services\ApplicationStatus;
use App\Services\AppointmentService;
use App\Services\CmsService;
use App\Services\CredentialService;
use App\Services\DocumentStatus;
use App\Services\EligibilityService;
use App\Services\NotificationStatus;
use App\Services\RecruitmentService;
use App\Services\WelfareService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Every lifecycle in section 8, written out here by hand. Each map is checked for every pair of statuses, so both the
 * allowed and the forbidden moves are covered, including a status moving to itself. Membership has one status, and
 * a role expiring or a credential being revoked are not maps: see BusinessRulesTest and CredentialApprovalTest.
 */
final class TransitionMapsTest extends TestCase
{
    /** @return array<string, array{callable(string, string): bool, array<string, list<string>>}> */
    public static function lifecycles(): array
    {
        return [
            'Membership application' => [ApplicationStatus::canTransition(...), [
                'draft' => ['submitted'],
                'submitted' => ['under_review', 'withdrawn'],
                'under_review' => ['approved', 'rejected', 'for_revision'],
                'for_revision' => ['submitted'],
                'approved' => [],
                'rejected' => [],
                'withdrawn' => [],
            ]],
            'Applicant document' => [DocumentStatus::canTransition(...), [
                'pending' => ['verified', 'needs_correction', 'rejected'],
                'needs_correction' => ['pending'],
                'verified' => [],
                'rejected' => [],
            ]],
            'HR request' => [RecruitmentService::canTransitionRequest(...), [
                'submitted' => ['approved', 'returned', 'cancelled'],
                'returned' => ['submitted'],
                'approved' => [],
                'cancelled' => [],
            ]],
            'Vacancy' => [RecruitmentService::canTransitionVacancy(...), [
                'draft' => ['open', 'cancelled'],
                'open' => ['closed', 'filled', 'cancelled'],
                'closed' => ['open', 'filled'],
                'filled' => [],
                'cancelled' => [],
            ]],
            'Welfare case' => [WelfareService::canTransition(...), [
                'submitted' => ['in_progress'],
                'in_progress' => ['referred', 'resolved'],
                'referred' => ['in_progress', 'resolved'],
                'resolved' => ['closed'],
                'closed' => [],
            ]],
            'Content post' => [CmsService::canTransition(...), [
                'draft' => ['for_approval'],
                'for_approval' => ['published', 'returned'],
                'returned' => ['for_approval'],
                'published' => ['archived'],
                'archived' => [],
            ]],
            'Eligibility screening' => [EligibilityService::canTransition(...), [
                'pending' => ['submitted', 'incomplete'],
                'submitted' => ['eligible', 'flagged'],
                'incomplete' => ['flagged'],
                'flagged' => ['confirmed_ineligible', 'cleared'],
                'eligible' => [],
                'confirmed_ineligible' => [],
                'cleared' => [],
            ]],
            'Notification' => [NotificationStatus::canTransition(...), [
                'queued' => ['sent', 'failed'],
                'failed' => ['queued'],
                'sent' => [],
            ]],
            'Appointment' => [AppointmentService::canTransition(...), [
                'prepared' => ['approved', 'returned'],
                'approved' => [],
                'returned' => [],
            ]],
            'Digital credential' => [CredentialService::canTransition(...), [
                'pending_approval' => ['approved', 'returned'],
                'returned' => ['pending_approval'],
                'approved' => [],
            ]],
        ];
    }

    /** @param array<string, list<string>> $map */
    #[DataProvider('lifecycles')]
    public function testOnlySectionEightMovesAreAllowed(callable $canMove, array $map): void
    {
        foreach ($map as $from => $allowed) {
            foreach (array_keys($map) as $to) {
                $this->assertSame(in_array($to, $allowed, true), $canMove($from, $to), "{$from} to {$to}");
            }
        }
    }

    /** @param array<string, list<string>> $map */
    #[DataProvider('lifecycles')]
    public function testUnknownStatusesCannotMove(callable $canMove, array $map): void
    {
        $first = array_key_first($map);

        $this->assertFalse($canMove('not_a_status', $first));
        $this->assertFalse($canMove($first, 'not_a_status'));
    }

    public function testApplicationAndDocumentMapsThrowOnAForbiddenMove(): void
    {
        $this->expectException(DomainException::class);
        ApplicationStatus::assertTransition('approved', 'draft');
    }

    public function testDocumentMapThrowsOnAForbiddenMove(): void
    {
        $this->expectException(DomainException::class);
        DocumentStatus::assertTransition('verified', 'pending');
    }

    public function testNotificationMapThrowsOnAForbiddenMove(): void
    {
        $this->expectException(DomainException::class);
        NotificationStatus::assertTransition('sent', 'queued');
    }
}

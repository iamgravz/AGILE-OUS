<?php

declare(strict_types=1);

use App\Services\ApplicationStatus;
use App\Services\DocumentStatus;
use App\Services\MembershipService;
use PHPUnit\Framework\TestCase;

final class ApplicationRulesTest extends TestCase
{
    public function testApplicationTransitionsMatchSectionEight(): void
    {
        $allowed = [
            ['draft', 'submitted'],
            ['submitted', 'under_review'],
            ['submitted', 'withdrawn'],
            ['under_review', 'approved'],
            ['under_review', 'rejected'],
            ['under_review', 'for_revision'],
            ['for_revision', 'submitted'],
        ];

        foreach (ApplicationStatus::ALL as $from) {
            foreach (ApplicationStatus::ALL as $to) {
                $this->assertSame(in_array([$from, $to], $allowed, true), ApplicationStatus::canTransition($from, $to), "{$from} to {$to}");
            }
        }
    }

    public function testInvalidApplicationTransitionThrows(): void
    {
        $this->expectException(DomainException::class);
        ApplicationStatus::assertTransition('approved', 'draft');
    }

    public function testDocumentTransitionsMatchSectionEight(): void
    {
        $allowed = [
            ['pending', 'verified'],
            ['pending', 'needs_correction'],
            ['pending', 'rejected'],
            ['needs_correction', 'pending'],
        ];

        foreach (DocumentStatus::ALL as $from) {
            foreach (DocumentStatus::ALL as $to) {
                $this->assertSame(in_array([$from, $to], $allowed, true), DocumentStatus::canTransition($from, $to), "{$from} to {$to}");
            }
        }
    }

    public function testTrackingCodeFormat(): void
    {
        for ($i = 0; $i < 500; $i++) {
            $this->assertMatchesRegularExpression('/^[A-HJKMNP-Z2-9]{12}$/', MembershipService::generateTrackingCode());
        }
    }
}

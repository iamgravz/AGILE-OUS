<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AcademicTermRepository;
use App\Models\ApplicationDocumentRepository;
use App\Models\AppointmentRepository;
use App\Models\ContentPostRepository;
use App\Models\DashboardRepository;
use App\Models\MemberRepository;
use App\Models\MembershipApplicationRepository;

/**
 * What each role's dashboard shows (FR-ADM-04, FR-ADM-06). Every number here is read-only. Cards and tasks are
 * plain label, value and link rows, so a view only has to list them.
 */
final class DashboardService
{
    private const ACKNOWLEDGE_HOURS = 24;

    public function __construct(private readonly DashboardRepository $data = new DashboardRepository())
    {
    }

    /** @return array<string, mixed> What every dashboard shows, plus the sections for this user's role. */
    public function forCurrentUser(array $user): array
    {
        $userId = (int) $user['user_id'];
        $member = (new MemberRepository())->findByUser($userId);

        $term = (new AcademicTermRepository())->current();

        $common = [
            'semester' => $term === null ? null : EligibilityService::termLabel($term),
            'eligibilityNotice' => $member === null ? null : (new EligibilityService())->noticeFor((int) $member['member_id']),
            'notifications' => (new NotificationService())->latest($userId, 5),
            'announcements' => (new ContentPostRepository())->publishedList('announcement', '', 1, 3)['data'] ?? [],
        ];

        return $common + match ($user['role_slug']) {
            'general_member' => $this->generalMember($userId),
            'committee_head' => $this->committeeHead($userId),
            'msw_member' => $this->mswMember(),
            'msw_head' => $this->mswHead(),
            'president' => $this->president(),
            'execomm' => $this->overview(),
            default => [],
        };
    }

    /**
     * The organization in numbers, for those with dashboard.view_all. Welfare is counts only.
     *
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        authorize('dashboard.view_all');

        $applications = $this->data->applicationsByStatus();
        $welfare = $this->data->welfareByStatus();

        return [
            'cards' => [
                ['label' => 'Members', 'value' => $this->data->memberCount()],
                ['label' => 'Active officers', 'value' => $this->data->activeOfficerCount()],
                ['label' => 'Applications being processed', 'value' => $this->sum($applications, ['submitted', 'under_review', 'for_revision'])],
                ['label' => 'Open vacancies', 'value' => $this->data->openVacancyCount()],
                ['label' => 'Slots filled', 'value' => $this->data->filledSlotCount()],
                ['label' => 'Welfare cases not closed', 'value' => array_sum($welfare) - ($welfare['closed'] ?? 0)],
            ],
            'eligibility' => $this->eligibilityProgress(),
            'charts' => [
                $this->chart('Applications per month', 'applications', $this->lastSixMonths()),
                $this->chart('Officers by committee', 'members', $this->data->membersByCommittee()),
                $this->chart('Welfare cases by status', 'cases', $this->labelled($welfare, WelfareService::STATUSES)),
                $this->chart('Welfare cases by category', 'cases', $this->data->welfareByCategory()),
            ],
        ];
    }

    /** @return array{label: string, url: string}|null What the applicant should do next on this application. */
    private function nextAction(array $application): ?array
    {
        $id = (int) $application['application_id'];

        if ($application['status'] === 'draft') {
            return ['label' => 'Finish your application', 'url' => route('apply.step', ['id' => $id, 'step' => (int) $application['wizard_step']])];
        }

        if ($application['status'] !== 'for_revision') {
            return null;
        }

        foreach ((new ApplicationDocumentRepository())->forApplication($id) as $document) {
            if ($document['verification_status'] === 'needs_correction') {
                return ['label' => 'Upload a corrected ' . MembershipService::documentLabel($document['doc_type']), 'url' => route('apply.step', ['id' => $id, 'step' => 5])];
            }
        }

        return ['label' => 'Revise and resubmit your application', 'url' => route('apply.step', ['id' => $id, 'step' => 1])];
    }

    /** @return array<string, mixed> */
    private function generalMember(int $userId): array
    {
        $profile = (new MemberRecordsService())->ownProfile();
        $welfare = $this->data->welfareStatusFor($userId);
        $tasks = [];

        foreach ((new MembershipApplicationRepository())->forUser($userId) as $application) {
            $action = $this->nextAction($application);

            if ($action !== null) {
                $tasks[] = $action;
            }
        }

        foreach ($this->data->pendingInterviewsFor($userId) as $interview) {
            $tasks[] = ['label' => 'Interview on ' . date('F j, Y, g:i A', strtotime($interview['scheduled_at'])), 'url' => route('my_applications')];
        }

        return [
            'cards' => [
                ['label' => 'My welfare cases', 'value' => count($welfare), 'url' => route('my_welfare_cases')],
                ['label' => 'Open positions', 'value' => $this->data->openVacancyCount(), 'url' => route('positions')],
            ],
            'tasks' => $tasks,
            'profile' => $profile,
            'welfareCases' => $welfare,
        ];
    }

    /** @return array<string, mixed> */
    private function committeeHead(int $userId): array
    {
        $requests = $this->data->hrRequestsByStatusFor($userId);
        $vacancies = $this->data->openVacanciesHeadedBy($userId);
        $toEvaluate = $this->data->applicationsAwaitingEvaluationBy($userId);
        $toDecide = $this->data->applicationsToDecide($userId, config('role_mapping.msw_committee'));

        return [
            'cards' => [
                ['label' => 'HR requests waiting', 'value' => $requests['submitted'] ?? 0, 'url' => route('hr_requests') . '?status=submitted'],
                ['label' => 'HR requests returned to me', 'value' => $requests['returned'] ?? 0, 'url' => route('hr_requests') . '?status=returned'],
                ['label' => 'My open vacancies', 'value' => count($vacancies), 'url' => route('committee_recruitment')],
                ['label' => 'Waiting for my evaluation', 'value' => count($toEvaluate), 'url' => route('evaluations')],
            ],
            'tasks' => [
                ...$this->oldestFirst([
                    $this->countTask($toDecide, 'application to decide', 'applications to decide', $this->data->oldestApplicationToDecide($userId, config('role_mapping.msw_committee')), route('applications.committee') . '?status=under_review'),
                    ...array_map(fn (array $row): array => $this->rowTask(
                        'Evaluate ' . $row['applicant_name'] . ' for ' . $row['vacancy_title'],
                        $row['submitted_at'],
                        route('applications.show', ['id' => (int) $row['application_id']]) . '?tab=evaluations',
                    ), array_slice($toEvaluate, 0, 5)),
                ]),
            ],
            'vacancies' => $vacancies,
        ];
    }

    /** @return array<string, mixed> */
    private function mswMember(): array
    {
        $applications = $this->data->applicationsByStatus();
        $ready = (new AppointmentRepository())->readyToPrepare();
        $interviews = $this->interviewsThisWeek();
        $documents = $this->data->documentsAwaitingVerification();

        return [
            'cards' => [
                ['label' => 'New applications', 'value' => $applications['submitted'] ?? 0, 'url' => route('applications') . '?status=submitted'],
                ['label' => 'Under review', 'value' => $applications['under_review'] ?? 0, 'url' => route('applications') . '?status=under_review'],
                ['label' => 'Documents to verify', 'value' => $documents, 'url' => route('applications')],
                ['label' => 'Interviews this week', 'value' => count($interviews), 'url' => route('interviews')],
                ['label' => 'Appointments to prepare', 'value' => count($ready), 'url' => route('appointments')],
            ],
            'tasks' => $this->oldestFirst([
                $this->countTask($documents, 'document waiting for verification', 'documents waiting for verification', $this->data->oldestDocumentAwaitingVerification(), route('applications')),
                ...array_map(fn (array $row): array => $this->rowTask(
                    'Prepare an appointment for ' . $row['applicant_name'],
                    $row['decided_at'],
                    route('appointments.prepare') . '?application=' . (int) $row['application_id'],
                ), array_slice($ready, 0, 5)),
            ]),
            'applicationStatuses' => $applications,
            'latestApplications' => array_slice((new MembershipApplicationRepository())->searchForStaff([], 'submitted_at', 'desc', 1)['data'], 0, 5),
            'interviews' => $interviews,
        ];
    }

    /** @return array<string, mixed> */
    private function mswHead(): array
    {
        $staff = $this->mswMember();
        $overview = $this->overview();
        $applications = $staff['applicationStatuses'];
        $approvals = [
            ['label' => 'Applications to decide', 'count' => $this->data->applicationsToDecide(null, config('role_mapping.msw_committee')), 'url' => route('applications') . '?status=under_review'],
            ['label' => 'IDs and certificates to approve', 'count' => (new CredentialService())->pendingCount(), 'url' => route('credentials.approvals')],
            ['label' => 'New accounts to approve', 'count' => $this->data->pendingAccountCount(), 'url' => route('accounts.approvals')],
            ['label' => 'HR requests to decide', 'count' => $this->data->hrRequestsSubmitted(), 'url' => route('hr_requests') . '?status=submitted'],
            ['label' => 'Appointments to approve', 'count' => $this->data->appointmentsPrepared(), 'url' => route('appointments') . '?status=prepared'],
            ['label' => 'Content to approve', 'count' => $this->data->contentForApproval(), 'url' => route('cms') . '?status=for_approval'],
        ];
        $msw = config('role_mapping.msw_committee');
        $welfareQueue = $this->welfareQueue();
        $tasks = [
            $this->countTask($approvals[0]['count'], 'application to decide', 'applications to decide', $this->data->oldestApplicationToDecide(null, $msw), $approvals[0]['url']),
            $this->countTask($approvals[1]['count'], 'ID or certificate to approve', 'IDs and certificates to approve', $this->data->oldestCredentialPending(), $approvals[1]['url']),
            $this->countTask($approvals[2]['count'], 'new account to approve', 'new accounts to approve', $this->data->oldestPendingAccount(), $approvals[2]['url']),
            $this->countTask($approvals[3]['count'], 'HR request to decide', 'HR requests to decide', $this->data->oldestHrRequestSubmitted(), $approvals[3]['url']),
            $this->countTask($approvals[4]['count'], 'appointment to approve', 'appointments to approve', $this->data->oldestAppointmentPrepared(), $approvals[4]['url']),
            $this->countTask($approvals[5]['count'], 'post to approve', 'posts to approve', $this->data->oldestContentForApproval(), $approvals[5]['url']),
            $this->countTask($welfareQueue['overdue'], 'welfare case not acknowledged after 24 hours', 'welfare cases not acknowledged after 24 hours', null, route('welfare.queue')),
        ];

        return [
            'cards' => [
                ...array_map(static fn (array $a): array => ['label' => $a['label'], 'value' => $a['count'], 'url' => $a['url']], $approvals),
                ['label' => 'Failed emails', 'value' => $this->data->failedEmails()],
            ],
            'tasks' => $this->oldestFirst([...$tasks, ...$staff['tasks']]),
            'latestApplications' => $staff['latestApplications'],
            'staffCards' => $staff['cards'],
            'applicationStatuses' => $applications,
            'interviews' => $staff['interviews'],
            'welfareQueue' => $welfareQueue,
            'eligibility' => $overview['eligibility'],
            'charts' => $overview['charts'],
        ];
    }

    /** @return array<string, mixed> */
    private function president(): array
    {
        $queue = $this->welfareQueue();

        return [
            'welfareQueue' => $queue,
            'tasks' => $this->oldestFirst([$this->countTask($queue['overdue'], 'welfare case not acknowledged after 24 hours', 'welfare cases not acknowledged after 24 hours', null, route('welfare.queue'))]),
        ] + $this->overview();
    }

    /** @return array{label: string, url: string, since: ?string}|null "3 documents waiting for verification, oldest 4 days". Null when none. */
    private function countTask(int $count, string $one, string $many, ?string $oldest, string $url): ?array
    {
        if ($count === 0) {
            return null;
        }

        $label = $count === 1 ? "1 {$one}" : "{$count} {$many}";

        return ['label' => $oldest === null ? $label : $label . ($count === 1 ? ', waiting ' : ', oldest ') . $this->age($oldest), 'url' => $url, 'since' => $oldest];
    }

    /** @return array{label: string, url: string, since: ?string} */
    private function rowTask(string $what, ?string $since, string $url): array
    {
        return ['label' => $since === null ? $what : $what . ', waiting ' . $this->age($since), 'url' => $url, 'since' => $since];
    }

    private function age(string $since): string
    {
        $days = (int) floor((time() - (int) strtotime($since)) / 86400);

        return $days < 1 ? 'less than a day' : ($days === 1 ? '1 day' : "{$days} days");
    }

    /**
     * Empty ones dropped, then the longest waiting first. Tasks with no date go last.
     *
     * @param list<array{label: string, url: string, since?: ?string}|null> $tasks
     * @return list<array{label: string, url: string, since?: ?string}>
     */
    private function oldestFirst(array $tasks): array
    {
        $tasks = array_values(array_filter($tasks));
        usort($tasks, static fn (array $a, array $b): int => ($a['since'] ?? '9999') <=> ($b['since'] ?? '9999'));

        return $tasks;
    }

    /** @return array{priorities: array<string, int>, overdue: int} Counts only. */
    private function welfareQueue(): array
    {
        authorize('welfare.view');

        return [
            'priorities' => $this->labelled($this->data->openWelfareByPriority(), array_reverse(WelfareService::PRIORITIES)),
            'overdue' => $this->data->overdueWelfareCount(date('Y-m-d H:i:s', time() - self::ACKNOWLEDGE_HOURS * 3600)),
        ];
    }

    /** @return array{term: string, counts: array<string, int>, total: int}|null */
    private function eligibilityProgress(): ?array
    {
        $term = (new AcademicTermRepository())->current();

        if ($term === null) {
            return null;
        }

        $counts = $this->data->eligibilityByResult((int) $term['term_id']);

        return ['term' => EligibilityService::termLabel($term), 'counts' => $counts, 'total' => array_sum($counts)];
    }

    /** @return list<array<string, mixed>> Monday to Sunday of the current week. */
    private function interviewsThisWeek(): array
    {
        $start = strtotime('monday this week');

        return $this->data->interviewsBetween(date('Y-m-d 00:00:00', $start), date('Y-m-d 23:59:59', $start + 6 * 86400));
    }

    /** @return array<string, int> "Oct 2026" => count, with empty months kept. */
    private function lastSixMonths(): array
    {
        $first = strtotime(date('Y-m-01') . ' -5 months');
        $counts = $this->data->applicationsPerMonth(date('Y-m-d 00:00:00', $first));
        $months = [];

        for ($i = 0; $i < 6; $i++) {
            $month = strtotime('+' . $i . ' months', $first);
            $months[date('M Y', $month)] = $counts[date('Y-m', $month)] ?? 0;
        }

        return $months;
    }

    /**
     * Every known status in order, zero included, with a readable name.
     *
     * @param array<string, int> $counts
     * @param list<string> $keys
     * @return array<string, int>
     */
    private function labelled(array $counts, array $keys): array
    {
        $labelled = [];

        foreach ($keys as $key) {
            $labelled[config("statuses.{$key}")[0] ?? $key] = $counts[$key] ?? 0;
        }

        return $labelled;
    }

    /** @param array<string, int> $counts @param list<string> $keys */
    private function sum(array $counts, array $keys): int
    {
        return array_sum(array_intersect_key($counts, array_flip($keys)));
    }

    /**
     * @param array<string, int> $values
     * @return array{title: string, unit: string, labels: list<string>, values: list<int>}
     */
    private function chart(string $title, string $unit, array $values): array
    {
        return ['title' => $title, 'unit' => $unit, 'labels' => array_map(strval(...), array_keys($values)), 'values' => array_values($values)];
    }
}

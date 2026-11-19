<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\View;
use App\Models\AcademicTermRepository;
use App\Models\CommitteeRepository;
use App\Models\ReportRepository;
use DateTimeImmutable;
use Dompdf\Dompdf;
use Dompdf\Options;
use DomainException;

/** Reports for the MSW Committee Head (FR-ADM-08). Welfare statistics are aggregated counts only. */
final class ReportService
{
    public const REPORTS = [
        'membership' => 'Membership',
        'recruitment' => 'Recruitment',
        'welfare' => 'Welfare statistics',
        'eligibility' => 'Eligibility',
    ];
    public const FORMATS = ['csv', 'pdf'];

    /** Excel reads a spreadsheet cell starting with one of these as a formula. */
    private const FORMULA_STARTS = ['=', '+', '-', '@', "\t", "\r"];

    public function __construct(private readonly ReportRepository $reports = new ReportRepository())
    {
    }

    /**
     * Only known filters with valid values survive, whatever was sent.
     *
     * @param array<string, string> $input
     * @return array<string, int|string>
     */
    public static function filters(string $report, array $input): array
    {
        $filters = [];

        if ($report !== 'eligibility') {
            foreach (['from', 'to'] as $key) {
                $date = DateTimeImmutable::createFromFormat('!Y-m-d', $input[$key] ?? '');

                if ($date !== false && $date->format('Y-m-d') === ($input[$key] ?? '')) {
                    $filters[$key] = $input[$key];
                }
            }
        }

        $id = static fn (string $key): ?int => ctype_digit($input[$key] ?? '') ? (int) $input[$key] : null;

        if (in_array($report, ['membership', 'recruitment'], true) && $id('committee') !== null) {
            $filters['committee_id'] = $id('committee');
        }

        if ($report === 'membership' && in_array($input['type'] ?? '', ['general', 'officer'], true)) {
            $filters['type'] = $input['type'];
        }

        if ($report === 'recruitment' && in_array($input['status'] ?? '', RecruitmentService::VACANCY_STATUSES, true)) {
            $filters['status'] = $input['status'];
        }

        if ($report === 'welfare' && $id('category') !== null) {
            $filters['category_id'] = $id('category');
        }

        if ($report === 'eligibility' && $id('term') !== null) {
            $filters['term_id'] = $id('term');
        }

        return $filters;
    }

    /**
     * @param array<string, int|string> $filters from filters()
     * @return array{report: string, title: string, subtitle: string, columns: list<string>, rows: list<list<string>>}
     */
    public function build(string $report, array $filters): array
    {
        authorize('report.export');

        return match ($report) {
            'membership' => $this->membership($filters),
            'recruitment' => $this->recruitment($filters),
            'welfare' => $this->welfare($filters),
            'eligibility' => $this->eligibility($filters),
            default => throw new DomainException('Please choose a report.'),
        };
    }

    /** @return array{filename: string, contents: string, mime: string} Every export is audit-logged. */
    public function export(string $report, array $filters, string $format): array
    {
        authorize('report.export');

        if (!in_array($format, self::FORMATS, true)) {
            throw new DomainException('Please choose CSV or PDF.');
        }

        $built = $this->build($report, $filters);
        $contents = $format === 'csv' ? self::toCsv($built) : $this->toPdf($built);

        AuditService::log('report.exported', 'report', null, ['report' => $report, 'format' => $format, 'filters' => $filters, 'rows' => count($built['rows'])]);

        return [
            'filename' => "agile-{$report}-report-" . date('Y-m-d') . ".{$format}",
            'contents' => $contents,
            'mime' => $format === 'csv' ? 'text/csv; charset=UTF-8' : 'application/pdf',
        ];
    }

    /**
     * UTF-8 with a BOM, so Excel shows names like Peñaflor correctly, and with formulas made harmless.
     *
     * @param array{columns: list<string>, rows: list<list<string>>} $report
     */
    public static function toCsv(array $report): string
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $report['columns'], ',', '"', '');

        foreach ($report['rows'] as $row) {
            fputcsv($stream, array_map(self::safeCell(...), $row), ',', '"', '');
        }

        rewind($stream);

        return "\xEF\xBB\xBF" . stream_get_contents($stream);
    }

    public static function safeCell(string $value): string
    {
        return $value !== '' && in_array($value[0], self::FORMULA_STARTS, true) ? "'" . $value : $value;
    }

    /** @return list<array<string, mixed>> */
    public function committees(): array
    {
        authorize('report.export');

        return (new CommitteeRepository())->options();
    }

    /** @return list<array<string, mixed>> */
    public function terms(): array
    {
        authorize('report.export');

        return (new AcademicTermRepository())->allNewestFirst();
    }

    /** @param array{title: string, subtitle: string, columns: list<string>, rows: list<list<string>>} $report */
    private function toPdf(array $report): string
    {
        $logo = Config::basePath('public/assets/img/logo.png');
        $options = new Options();
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setIsJavascriptEnabled(false);
        $options->setChroot(Config::basePath('storage/cache'));
        $options->setTempDir(Config::basePath('storage/cache'));
        $options->setFontCache(Config::basePath('storage/cache'));
        $options->setDefaultFont('DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->loadHtml(View::render('reports/pdf', $report + [
            'logo' => is_file($logo) ? 'data:image/png;base64,' . base64_encode((string) file_get_contents($logo)) : null,
            'generatedAt' => date('F j, Y, g:i A'),
        ]));
        $dompdf->render();

        return (string) $dompdf->output();
    }

    private function membership(array $filters): array
    {
        $rows = array_map(static fn (array $row): array => [
            (string) $row['member_no'],
            (string) $row['member_name'],
            (string) ($row['student_no'] ?? ''),
            ucfirst((string) $row['membership_type']),
            (string) ($row['position_title'] === null ? $row['role_name'] : $row['position_title']),
            (string) ($row['committee_name'] ?? ''),
            ucfirst((string) ($row['role_status'] ?? 'None')),
            ucfirst((string) $row['status']),
            (string) $row['joined_at'],
        ], $this->reports->membership($filters));

        return $this->shaped('membership', $filters, ['Member number', 'Name', 'Student number', 'Type', 'Role or position', 'Committee', 'Role status', 'Status', 'Joined'], $rows);
    }

    private function recruitment(array $filters): array
    {
        $rows = array_map(static fn (array $row): array => [
            (string) $row['title'],
            (string) ($row['committee_name'] ?? 'Executive'),
            ucfirst((string) $row['status']),
            (string) $row['slots'],
            (string) $row['slots_filled'],
            (string) $row['applications'],
            (string) $row['approved'],
            (string) $row['interviews'],
            (string) $row['appointments'],
            substr((string) $row['opens_at'], 0, 10),
            substr((string) $row['closes_at'], 0, 10),
        ], $this->reports->recruitment($filters));

        return $this->shaped('recruitment', $filters, ['Vacancy', 'Committee', 'Status', 'Slots', 'Filled', 'Applications', 'Approved', 'Interviews', 'Appointments', 'Opens', 'Closes'], $rows);
    }

    private function welfare(array $filters): array
    {
        $rows = array_map(static fn (array $row): array => [
            (string) $row['category'],
            ucwords(str_replace('_', ' ', (string) $row['status'])),
            ucfirst((string) $row['priority']),
            (string) $row['cases'],
        ], $this->reports->welfare($filters));

        return $this->shaped('welfare', $filters, ['Category', 'Status', 'Priority', 'Cases'], $rows);
    }

    private function eligibility(array $filters): array
    {
        $term = isset($filters['term_id']) ? (new AcademicTermRepository())->find((int) $filters['term_id']) : (new AcademicTermRepository())->current();

        if ($term === null) {
            throw new DomainException('Please choose a term.');
        }

        $yesNo = static fn (mixed $value): string => $value === null ? '' : ((int) $value === 1 ? 'Yes' : 'No');
        $rows = array_map(static fn (array $row): array => [
            (string) $row['member_no'],
            (string) $row['officer_name'],
            trim((string) $row['position_title'] . ($row['committee_name'] === null ? '' : ', ' . $row['committee_name']), ', '),
            ucwords(str_replace('_', ' ', (string) $row['result'])),
            $yesNo($row['has_failed_subject']),
            $yesNo($row['is_officially_enrolled']),
            substr((string) $row['verified_at'], 0, 10),
            substr((string) $row['decided_at'], 0, 10),
        ], $this->reports->eligibility((int) $term['term_id']));

        return $this->shaped('eligibility', ['term' => EligibilityService::termLabel($term)], ['Member number', 'Officer', 'Position', 'Result', 'Failed subject', 'Officially enrolled', 'Verified', 'Decided'], $rows);
    }

    /**
     * @param array<string, int|string> $filters
     * @param list<string> $columns
     * @param list<list<string>> $rows
     * @return array{report: string, title: string, subtitle: string, columns: list<string>, rows: list<list<string>>}
     */
    private function shaped(string $report, array $filters, array $columns, array $rows): array
    {
        $parts = [];

        if (isset($filters['term'])) {
            $parts[] = 'Term: ' . $filters['term'];
        }

        if (isset($filters['from'])) {
            $parts[] = 'From ' . $filters['from'];
        }

        if (isset($filters['to'])) {
            $parts[] = 'To ' . $filters['to'];
        }

        return [
            'report' => $report,
            'title' => self::REPORTS[$report] . ' report',
            'subtitle' => $parts === [] ? 'All dates' : implode(', ', $parts),
            'columns' => $columns,
            'rows' => $rows,
        ];
    }
}

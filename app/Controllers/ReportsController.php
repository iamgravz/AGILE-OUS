<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\FileStorageService;
use App\Services\RecruitmentService;
use App\Services\ReportService;
use App\Services\WelfareService;
use DomainException;

/** The reports page and exports. Rules and permission checks are in ReportService. */
final class ReportsController
{
    private const FILTER_KEYS = ['from', 'to', 'committee', 'type', 'status', 'category', 'term'];
    private const PREVIEW_ROWS = 50;

    public function __construct(private readonly ReportService $reports = new ReportService())
    {
    }

    public function index(Request $request): Response
    {
        $report = $request->queryText('report');
        $report = isset(ReportService::REPORTS[$report]) ? $report : 'membership';
        $input = [];

        foreach (self::FILTER_KEYS as $key) {
            $input[$key] = $request->queryText($key);
        }

        $built = null;
        $error = null;

        try {
            $built = $this->reports->build($report, ReportService::filters($report, $input));
        } catch (DomainException $exception) {
            $error = $exception->getMessage();
        }

        return Response::html(View::render('reports/index', [
            'report' => $report,
            'input' => $input,
            'built' => $built,
            'error' => $error,
            'previewRows' => self::PREVIEW_ROWS,
            'committees' => $this->reports->committees(),
            'terms' => $this->reports->terms(),
            'categories' => (new WelfareService())->categories(),
            'vacancyStatuses' => RecruitmentService::VACANCY_STATUSES,
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Reports']],
        ], 'layouts/dashboard'), Response::HTTP_OK, ['Cache-Control' => 'no-store']);
    }

    /** A POST, because each export is logged and a link should not be able to cause one. */
    public function export(Request $request): Response
    {
        $report = $request->text('report');
        $input = [];

        foreach (self::FILTER_KEYS as $key) {
            $input[$key] = $request->text($key);
        }

        try {
            if (!isset(ReportService::REPORTS[$report])) {
                throw new DomainException('Please choose a report.');
            }

            $file = $this->reports->export($report, ReportService::filters($report, $input), $request->text('format'));
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());

            return redirect(route('reports'));
        }

        return (new FileStorageService())->streamContents($file['contents'], $file['mime'], $file['filename']);
    }
}

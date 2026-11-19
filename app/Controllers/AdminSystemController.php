<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\AuditLogRepository;
use App\Services\AuditService;
use App\Services\FileStorageService;
use App\Services\ReportService;
use App\Services\SettingsService;

/** Settings and the read-only audit log viewer. There is no route that changes or removes an audit row. */
final class AdminSystemController
{
    private const EXPORT_LIMIT = 10000;

    public function settings(Request $request): Response
    {
        return $this->page('admin/settings', (new SettingsService())->current() + [
            'groups' => SettingsService::FIELDS,
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Settings']],
        ]);
    }

    public function saveSettings(Request $request): Response
    {
        $file = $request->files('landing_hero_image');
        $heroImage = is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE ? $file : null;
        $errors = (new SettingsService())->save($request->all(), $heroImage);

        if ($errors !== []) {
            flash('_errors', $errors);
            flash('_old_input', array_filter($request->all(), 'is_string'));
            flash('error', 'Nothing was saved. Please fix the fields below.');
        } else {
            flash('success', 'The settings were saved.');
        }

        return redirect(route('admin.settings'));
    }

    public function auditLogs(Request $request): Response
    {
        authorize('audit.view');

        $filters = $this->filters($request);
        $repository = new AuditLogRepository();

        return $this->page('admin/audit_logs', [
            'result' => $repository->search($filters, max(1, (int) $request->queryText('page'))),
            'filters' => $filters,
            'actions' => $repository->distinctValues('action'),
            'entities' => $repository->distinctValues('entity_type'),
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Audit log']],
        ]);
    }

    public function exportAuditLogs(Request $request): Response
    {
        authorize('audit.view');

        $filters = [];

        foreach (['user', 'action', 'entity', 'from', 'to'] as $key) {
            $filters[$key] = $request->text($key);
        }

        $filters = $this->validDates($filters);
        $rows = (new AuditLogRepository())->search($filters, 1, self::EXPORT_LIMIT)['data'];
        $csv = ReportService::toCsv([
            'columns' => ['When', 'User', 'Action', 'Entity', 'Entity ID', 'IP address', 'Details'],
            'rows' => array_map(static fn (array $row): array => [
                (string) $row['created_at'], (string) ($row['actor_name'] ?? 'System'), (string) $row['action'], (string) $row['entity_type'], (string) $row['entity_id'], (string) $row['ip_address'], (string) $row['details_json'],
            ], $rows),
        ]);
        AuditService::log('audit.exported', 'audit_log', null, ['filters' => array_filter($filters), 'rows' => count($rows)]);

        return (new FileStorageService())->streamContents($csv, 'text/csv; charset=UTF-8', 'audit-log-' . date('Y-m-d') . '.csv');
    }

    /** @return array<string, string> */
    private function filters(Request $request): array
    {
        $filters = [];

        foreach (['user', 'action', 'entity', 'from', 'to'] as $key) {
            $filters[$key] = $request->queryText($key);
        }

        return $this->validDates($filters);
    }

    /**
     * @param array<string, string> $filters
     * @return array<string, string>
     */
    private function validDates(array $filters): array
    {
        foreach (['from', 'to'] as $key) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters[$key]) !== 1) {
                $filters[$key] = '';
            }
        }

        return $filters;
    }

    /** @param array<string, mixed> $data */
    private function page(string $view, array $data): Response
    {
        return Response::html(View::render($view, $data, 'layouts/dashboard'));
    }
}

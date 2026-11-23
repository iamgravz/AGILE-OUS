<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\DocumentArchiveService;

/** Administration, Document archive: the semester archive of decided COR and grades (archive only, never deleted). */
final class DocumentArchiveController
{
    public function index(Request $request): Response
    {
        return Response::html(View::render('admin/document_archive', [
            'summary' => (new DocumentArchiveService())->summary(),
            'days' => (int) config('retention.archive_after_days'),
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Document archive']],
        ], 'layouts/dashboard'));
    }

    public function run(Request $request): Response
    {
        $result = (new DocumentArchiveService())->archive();
        flash('success', sprintf('%d file%s moved to the archive.', $result['moved'], $result['moved'] === 1 ? '' : 's') . ($result['skipped'] > 0 ? " {$result['skipped']} skipped (missing, or already in the archive); nothing was deleted." : ''));

        return redirect(route('admin.archive'));
    }
}

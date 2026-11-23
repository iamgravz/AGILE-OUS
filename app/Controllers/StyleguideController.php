<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\FileRejectedException;
use App\Services\FileStorageService;

/** Local-only component reference. The routes are not registered outside APP_ENV=local. */
final class StyleguideController
{
    public function public(Request $request): Response
    {
        return Response::html(View::render('styleguide/index', ['layout' => 'public'], 'layouts/public'));
    }

    public function dashboard(Request $request): Response
    {
        $data = [
            'layout' => 'dashboard',
            'breadcrumbs' => [
                ['label' => 'Home', 'url' => '/'],
                ['label' => 'Style Guide', 'url' => '/styleguide'],
                ['label' => 'Dashboard layout'],
            ],
            'currentUser' => ['name' => 'Example User', 'role' => 'Example Role'],
            'notifications' => $this->exampleNotifications(),
            'sidebarMenu' => config('menu.dashboard', []),
        ];

        return Response::html(View::render('styleguide/index', $data, 'layouts/dashboard'));
    }

    public function auth(Request $request): Response
    {
        return Response::html(View::render('styleguide/auth', [], 'layouts/auth'));
    }

    public function showUpload(Request $request): Response
    {
        return Response::html(View::render('styleguide/upload', ['maxMb' => (new FileStorageService())->maxUploadMb()], 'layouts/public'));
    }

    /** Runs the real upload checks, then removes the file. Nothing is kept. */
    public function upload(Request $request): Response
    {
        $files = new FileStorageService();
        $field = $request->files('checked_file') !== null ? 'checked_file' : 'server_file';
        $file = $request->files($field);

        try {
            $stored = $files->store(is_array($file) ? $file : [], 'upload-test');
            $files->delete($stored['stored_path']);
            flash('success', sprintf('Accepted %s (%s, %d bytes). Test file deleted.', $stored['original_name'], $stored['mime_type'], $stored['size_bytes']));
        } catch (FileRejectedException $exception) {
            flash('_errors', [$field => $exception->getMessage()]);
        }

        return redirect(route('styleguide.upload'));
    }

    /** @return list<array{title: string, time: string, link: string, unread: bool}> */
    private function exampleNotifications(): array
    {
        return [
            ['title' => 'Your application needs revision', 'time' => '10 minutes ago', 'link' => '#', 'unread' => true],
            ['title' => 'Interview scheduled for next Monday', 'time' => 'Yesterday', 'link' => '#', 'unread' => true],
            ['title' => 'Your document was verified', 'time' => 'Oct 12, 2026', 'link' => '#', 'unread' => false],
        ];
    }
}

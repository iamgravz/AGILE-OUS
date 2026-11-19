<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;

/** Stands in for sidebar pages until their steps replace it, so permissions can be tested on every URL. */
final class PlaceholderController
{
    public function show(Request $request): Response
    {
        $title = 'Administration';

        foreach (config('menu.dashboard', []) as $group) {
            foreach ($group['items'] as $item) {
                if ($item['url'] === $request->path()) {
                    $title = $item['label'];
                }
            }
        }

        return Response::html(View::render('dashboard/placeholder', [
            'title' => $title,
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => $title]],
        ], 'layouts/dashboard'));
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\LandingService;
use App\Services\PortalService;

final class PortalController
{
    private const LISTS = [
        'news' => ['title' => 'News', 'route' => 'news', 'intro' => 'News from the AGILE Execomm and committees.'],
        'event' => ['title' => 'Events', 'route' => 'events', 'intro' => 'Activities, seminars and assemblies.'],
        'publication' => ['title' => 'Publications', 'route' => 'publications', 'intro' => 'Newsletters and other AGILE publications.'],
        'announcement' => ['title' => 'Announcements', 'route' => 'announcements', 'intro' => 'Notices for AGILE members and applicants.'],
    ];

    public function __construct(private readonly PortalService $portal = new PortalService())
    {
    }

    public function home(Request $request): Response
    {
        return $this->page('portal/home', (new LandingService())->page());
    }

    public function about(Request $request): Response
    {
        return $this->page('portal/about', $this->portal->about(), 'layouts/docs');
    }

    public function news(Request $request): Response
    {
        return $this->listing($request, 'news');
    }

    public function events(Request $request): Response
    {
        return $this->listing($request, 'event');
    }

    public function publications(Request $request): Response
    {
        return $this->listing($request, 'publication');
    }

    public function announcements(Request $request): Response
    {
        return $this->listing($request, 'announcement');
    }

    /** Only published posts exist as far as the public is concerned (BR-12). */
    public function post(Request $request, string $slug): Response
    {
        $post = $this->portal->post($slug);

        return $post === null
            ? Response::error(Response::HTTP_NOT_FOUND)
            : $this->page('portal/post', ['post' => $post, 'list' => self::LISTS[$post['type']]], 'layouts/docs');
    }

    public function positions(Request $request): Response
    {
        return $this->page('portal/positions', ['positions' => $this->portal->openPositions()]);
    }

    public function position(Request $request, string $id): Response
    {
        $position = ctype_digit($id) ? $this->portal->openPosition((int) $id) : null;

        return $position === null
            ? Response::error(Response::HTTP_NOT_FOUND)
            : $this->page('portal/position', ['position' => $position], 'layouts/docs');
    }

    /** Logged-in users go straight to the form. Everyone else chooses how to continue. */
    public function askForHelp(Request $request): Response
    {
        if (current_user_id() !== null) {
            return redirect('/welfare/new');
        }

        // Logged out, the form is the page. Without guest submission, the page explains how to log in instead.
        return $this->portal->guestWelfareEnabled()
            ? redirect(route('welfare.guest'))
            : $this->page('portal/ask_for_help', ['guestEnabled' => false]);
    }

    public function privacy(Request $request): Response
    {
        return $this->page('portal/privacy', [], 'layouts/docs');
    }

    private function listing(Request $request, string $type): Response
    {
        $search = $request->queryText('q');
        $when = null;

        if ($type === 'event') {
            $when = $request->queryText('when') === 'past' ? 'past' : 'upcoming';
        }

        return $this->page('portal/list', [
            'type' => $type,
            'list' => self::LISTS[$type],
            'search' => $search,
            'when' => $when,
            'result' => $this->portal->posts($type, $search, max(1, (int) $request->queryText('page')), $when),
        ]);
    }

    /** @param array<string, mixed> $data */
    private function page(string $view, array $data = [], string $layout = 'layouts/public'): Response
    {
        return Response::html(View::render($view, $data, $layout));
    }
}

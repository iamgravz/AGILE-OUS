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
        'news' => ['title' => 'News', 'route' => 'news', 'eyebrow' => 'news', 'heading' => 'What is new at AGILE', 'intro' => 'Stories from the Executive Board and the committees: wins, activities and changes members should know about.'],
        'event' => ['title' => 'Events', 'route' => 'events', 'eyebrow' => 'events', 'heading' => 'Learn, build and show up together', 'intro' => 'Workshops, review sessions, assemblies and outreach. Add any of them to your calendar.'],
        'publication' => ['title' => 'Publications', 'route' => 'publications', 'eyebrow' => 'the source code', 'heading' => 'The Source Code', 'intro' => 'The official student publication of AGILE-OUS: news, features, opinion and comics by the publication staff.'],
        'announcement' => ['title' => 'Announcements', 'route' => 'announcements', 'eyebrow' => 'announcements', 'heading' => 'Notices for members and applicants', 'intro' => 'Deadlines, schedules and advisories from AGILE.'],
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
        return $this->page('portal/about', $this->portal->about());
    }

    public function committees(Request $request): Response
    {
        return $this->page('portal/committees', ['cards' => $this->portal->committeeCards(), 'facts' => $this->portal->facts()]);
    }

    public function committee(Request $request, string $id): Response
    {
        $committee = ctype_digit($id) ? $this->portal->committee((int) $id) : null;

        return $committee === null ? Response::error(Response::HTTP_NOT_FOUND) : $this->page('portal/committee', ['committee' => $committee]);
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

        if ($post === null) {
            return Response::error(Response::HTTP_NOT_FOUND);
        }

        $isEvent = $post['type'] === 'event';
        $related = $isEvent ? [] : array_slice(array_values(array_filter(
            (new \App\Models\ContentPostRepository())->publishedList($post['type'], '', 1, 4)['data'],
            static fn (array $other): bool => $other['slug'] !== $post['slug'],
        )), 0, 3);

        return $this->page('portal/post', [
            'post' => $post,
            'list' => self::LISTS[$post['type']],
            'author' => (new \App\Models\UserRepository())->find((int) $post['author_id']),
            'related' => $related,
            'registration' => $isEvent ? (new \App\Services\EventRegistrationService())->summary($post) : null,
        ], 'layouts/docs');
    }

    public function positions(Request $request): Response
    {
        $open = (new \App\Services\MembershipService())->openPositions();
        $role = $request->queryText('role');
        $roles = ['committee_head' => 'Committee Head', 'deputy' => 'Deputy Committee Head', 'committee_member' => 'Committee Member', 'source_code' => 'The Source Code'];

        return $this->page('portal/positions', [
            'open' => isset($roles[$role]) ? array_values(array_filter($open, static fn (array $p): bool => $p['role_type'] === $role)) : $open,
            'allOpen' => $open,
            'roles' => $roles,
            'role' => isset($roles[$role]) ? $role : '',
            'positions' => $this->portal->openPositions(),
        ]);
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

        $page = max(1, (int) $request->queryText('page'));

        return $this->page('portal/list', [
            // News carries the latest notices on its first page, so a deadline is never under a story.
            'notices' => $type === 'news' && $page === 1 && $search === '' ? array_slice($this->portal->posts('announcement', '', 1)['data'], 0, 3) : [],
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

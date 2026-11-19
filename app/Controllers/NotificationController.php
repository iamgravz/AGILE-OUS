<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\NotificationService;

final class NotificationController
{
    private const LATEST_LIMIT = 10;

    public function __construct(private readonly NotificationService $notifications = new NotificationService())
    {
    }

    public function index(Request $request): Response
    {
        $page = $request->query('page');
        $result = $this->notifications->page((int) current_user_id(), is_string($page) ? (int) $page : 1);
        $result['data'] = array_map($this->present(...), $result['data']);

        return Response::html(View::render('notifications/index', [
            'result' => $result,
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Notifications']],
        ], 'layouts/dashboard'));
    }

    public function latest(Request $request): Response
    {
        $userId = (int) current_user_id();

        return Response::json([
            'unread' => $this->notifications->unreadCount($userId),
            'items' => array_map($this->present(...), $this->notifications->latest($userId, self::LATEST_LIMIT)),
        ]);
    }

    public function markRead(Request $request, string $id): Response
    {
        $this->notifications->markRead((int) $id, (int) current_user_id());

        return $this->afterChange($request);
    }

    public function markAllRead(Request $request): Response
    {
        $this->notifications->markAllRead((int) current_user_id());

        return $this->afterChange($request);
    }

    private function afterChange(Request $request): Response
    {
        return $request->isAjax()
            ? Response::json(['unread' => $this->notifications->unreadCount((int) current_user_id())])
            : redirect(route('notifications'));
    }

    /**
     * @param array<string, mixed> $row
     * @return array{id: int, title: string, link: ?string, time: string, read: bool}
     */
    private function present(array $row): array
    {
        $link = (string) $row['link'];

        return [
            'id' => (int) $row['notification_id'],
            'title' => $row['title'],
            // Only links within the site or plain web links are ever placed in an href.
            'link' => preg_match('#^(/(?!/)|https?://)#i', $link) === 1 ? $link : null,
            'time' => date('M j, Y g:i A', strtotime($row['created_at'])),
            'read' => $row['read_at'] !== null,
        ];
    }
}

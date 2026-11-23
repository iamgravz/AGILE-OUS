<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\EventRegistrationService;
use DomainException;

/** A member registers for an event, or cancels. Rules are in EventRegistrationService. */
final class EventRegistrationsController
{
    public function __construct(private readonly EventRegistrationService $registrations = new EventRegistrationService())
    {
    }

    public function register(Request $request, string $id): Response
    {
        return $this->act($id, fn (int $postId) => $this->registrations->register($postId), 'You are registered. See you at the event.');
    }

    public function cancel(Request $request, string $id): Response
    {
        return $this->act($id, fn (int $postId) => $this->registrations->cancel($postId), 'Your registration was cancelled.');
    }

    private function act(string $id, callable $action, string $message): Response
    {
        if (!ctype_digit($id)) {
            return Response::error(Response::HTTP_NOT_FOUND);
        }

        try {
            $action((int) $id);
            flash('success', $message);
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
        }

        $statement = Database::connection()->prepare("SELECT slug FROM content_posts WHERE post_id = ? AND type = 'event' AND status = 'published'");
        $statement->execute([(int) $id]);
        $slug = $statement->fetchColumn();

        return redirect($slug === false ? route('events') : route('posts.show', ['slug' => $slug]));
    }
}

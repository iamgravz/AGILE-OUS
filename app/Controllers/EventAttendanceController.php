<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\EventRegistrationService;
use App\Services\FileStorageService;
use DomainException;

/** Event attendance for officers (event.attendance): registrants, check-in by member number or ID QR, export. */
final class EventAttendanceController
{
    public function __construct(private readonly EventRegistrationService $events = new EventRegistrationService())
    {
    }

    public function index(Request $request): Response
    {
        return Response::html(View::render('events/attendance/index', [
            'events' => $this->events->events(),
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Event attendance']],
        ], 'layouts/dashboard'));
    }

    public function show(Request $request, string $id): Response
    {
        $event = ctype_digit($id) ? $this->events->event((int) $id) : null;

        if ($event === null) {
            return Response::error(Response::HTTP_NOT_FOUND);
        }

        return Response::html(View::render('events/attendance/show', [
            'event' => $event,
            'registrants' => $this->events->registrants((int) $id),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Event attendance', 'url' => route('events.attendance')],
                ['label' => $event['title']],
            ],
        ], 'layouts/dashboard'));
    }

    public function checkIn(Request $request, string $id): Response
    {
        $input = $request->text('member');

        try {
            if ($input === '' || mb_strlen($input) > 255) {
                throw new DomainException('Enter a member number, or scan the QR on the AGILE ID.');
            }

            $name = $this->events->checkIn((int) $id, $input);
            flash('success', "{$name} is checked in.");
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
        }

        return redirect(route('events.attendance.show', ['id' => (int) $id]));
    }

    public function undo(Request $request, string $id, string $registrationId): Response
    {
        try {
            $this->events->undoCheckIn((int) $id, (int) $registrationId);
            flash('success', 'The check-in was undone.');
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
        }

        return redirect(route('events.attendance.show', ['id' => (int) $id]));
    }

    public function export(Request $request, string $id): Response
    {
        $event = ctype_digit($id) ? $this->events->event((int) $id) : null;

        if ($event === null) {
            return Response::error(Response::HTTP_NOT_FOUND);
        }

        return (new FileStorageService())->streamContents($this->events->csv((int) $id), 'text/csv', 'attendance-' . $event['slug'] . '.csv');
    }
}

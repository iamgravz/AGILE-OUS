<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Core\View;
use App\Services\CmsService;
use App\Services\FileRejectedException;
use DateTimeImmutable;
use DomainException;

final class CmsController
{
    public function __construct(private readonly CmsService $cms = new CmsService())
    {
    }

    public function index(Request $request): Response
    {
        $status = $this->queryText($request, 'status');
        $type = $this->queryText($request, 'type');
        $search = $this->queryText($request, 'q');
        $filters = array_filter([
            'status' => in_array($status, CmsService::STATUSES, true) ? $status : null,
            'type' => in_array($type, CmsService::TYPES, true) ? $type : null,
            'q' => $search !== '' ? $search : null,
        ], static fn (?string $value): bool => $value !== null);

        return $this->page('cms/index', [
            'result' => $this->cms->list($filters, max(1, (int) $this->queryText($request, 'page'))),
            'filters' => $filters,
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Content']],
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->editor(null);
    }

    public function edit(Request $request, string $id): Response
    {
        $post = $this->findOr404($id);

        if ($post === null) {
            return Response::error(Response::HTTP_NOT_FOUND);
        }

        if (!CmsService::isEditable($post['status'])) {
            flash('warning', 'This post cannot be edited in its current status.');

            return redirect(route('cms.show', ['id' => $post['post_id']]));
        }

        return $this->editor($post);
    }

    public function store(Request $request): Response
    {
        return $this->save($request, null, route('cms.create'));
    }

    public function update(Request $request, string $id): Response
    {
        $post = $this->findOr404($id);

        if ($post === null) {
            return Response::error(Response::HTTP_NOT_FOUND);
        }

        return $this->save($request, (int) $post['post_id'], route('cms.edit', ['id' => $post['post_id']]));
    }

    public function show(Request $request, string $id): Response
    {
        return $this->renderPost($id, false);
    }

    public function review(Request $request, string $id): Response
    {
        return $this->renderPost($id, true);
    }

    public function publish(Request $request, string $id): Response
    {
        return $this->decide($request, $id, 'publish', 'The post is now published.');
    }

    public function returnToAuthor(Request $request, string $id): Response
    {
        return $this->decide($request, $id, 'returnToAuthor', 'The post was returned to its author.');
    }

    public function archive(Request $request, string $id): Response
    {
        try {
            $this->cms->archive((int) $id);
            flash('success', 'The post was archived and is no longer public.');
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
        }

        return redirect(route('cms.review', ['id' => (int) $id]));
    }

    public function uploadImage(Request $request): Response
    {
        $file = $request->files('image');

        try {
            return Response::json(['url' => $this->cms->uploadBodyImage(is_array($file) ? $file : [])]);
        } catch (FileRejectedException $exception) {
            return Response::json(['error' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE);
        }
    }

    private function save(Request $request, ?int $id, string $backTo): Response
    {
        $submitting = $request->text('intent') === 'submit';
        $data = $this->postData($request);
        $errors = Validator::check($data, $this->rules($submitting, $data['type']));
        $data['event_start'] = $this->parseDate($data['event_start'], 'event_start', $errors);
        $data['event_end'] = $this->parseDate($data['event_end'], 'event_end', $errors);

        if ($data['type'] === 'event' && $data['event_start'] !== null && $data['event_end'] !== null && $data['event_end'] < $data['event_start']) {
            $errors['event_end'] = 'The end must be after the start.';
        }

        if ($submitting && trim(strip_tags($this->cms->clean($data['body']), '<img>')) === '') {
            $errors['body'] = 'The body is required before submitting.';
        }

        $cover = $this->uploadedCover($request);

        if ($errors !== []) {
            return $this->backWithErrors($backTo, $errors, $request);
        }

        try {
            $savedId = $this->cms->save($id, $data, $cover);

            if ($submitting) {
                $this->cms->submit($savedId);
                flash('success', 'Your post was submitted for approval.');

                return redirect(route('cms.show', ['id' => $savedId]));
            }
        } catch (FileRejectedException $exception) {
            return $this->backWithErrors($backTo, ['cover_image' => $exception->getMessage()], $request);
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());

            return redirect($id === null ? route('cms') : route('cms.show', ['id' => $id]));
        }

        flash('success', 'Your draft was saved.');

        return redirect(route('cms.edit', ['id' => $savedId]));
    }

    private function decide(Request $request, string $id, string $method, string $message): Response
    {
        $remarks = $request->text('remarks');
        $errors = Validator::check(['remarks' => $remarks], ['remarks' => ['label' => 'Remarks', 'rules' => ['required', 'max:2000']]]);

        if ($errors !== []) {
            flash('_errors', $errors);

            return redirect(route('cms.review', ['id' => (int) $id]));
        }

        try {
            $this->cms->$method((int) $id, $remarks);
            flash('success', $message);
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
        }

        return redirect(route('cms.review', ['id' => (int) $id]));
    }

    private function renderPost(string $id, bool $reviewing): Response
    {
        $post = $this->findOr404($id);

        if ($post === null) {
            return Response::error(Response::HTTP_NOT_FOUND);
        }

        return $this->page('cms/show', [
            'post' => $post,
            'reviewing' => $reviewing,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Content', 'url' => route('cms')],
                ['label' => $post['title'] !== '' ? $post['title'] : 'Untitled'],
            ],
        ]);
    }

    /** @param array<string, mixed>|null $post */
    private function editor(?array $post): Response
    {
        return $this->page('cms/edit', [
            'post' => $post,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Content', 'url' => route('cms')],
                ['label' => $post === null ? 'New post' : 'Edit post'],
            ],
        ]);
    }

    /** @return array<string, string> */
    private function postData(Request $request): array
    {
        return [
            'type' => $request->text('type'),
            'title' => $request->text('title'),
            'slug' => $request->text('slug'),
            'excerpt' => $request->text('excerpt'),
            'body' => $request->text('body'),
            'event_start' => $request->text('event_start'),
            'event_end' => $request->text('event_end'),
            'venue_or_link' => $request->text('venue_or_link'),
        ];
    }

    /** Drafts may be unfinished. Submitting needs everything the public page shows. */
    private function rules(bool $submitting, string $type): array
    {
        $needed = $submitting ? ['required'] : [];
        $neededForEvent = $submitting && $type === 'event' ? ['required'] : [];

        return [
            'type' => ['label' => 'Type', 'rules' => ['required', 'regex:/^(' . implode('|', CmsService::TYPES) . ')$/']],
            'title' => ['label' => 'Title', 'rules' => ['required', 'max:200']],
            'slug' => ['label' => 'Link name', 'rules' => ['max:200']],
            'excerpt' => ['label' => 'Excerpt', 'rules' => [...$needed, 'max:300']],
            'event_start' => ['label' => 'Event start', 'rules' => $neededForEvent],
            'venue_or_link' => ['label' => 'Venue or online link', 'rules' => [...$neededForEvent, 'max:255']],
        ];
    }

    /** @param array<string, string> $errors */
    private function parseDate(string $value, string $field, array &$errors): ?string
    {
        if ($value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value);

        if ($date === false) {
            $errors[$field] = 'Enter a valid date and time.';

            return null;
        }

        return $date->format('Y-m-d H:i:s');
    }

    /** @return array<string, mixed>|null */
    private function uploadedCover(Request $request): ?array
    {
        $file = $request->files('cover_image');

        return is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE ? $file : null;
    }

    /** @param array<string, string> $errors */
    private function backWithErrors(string $to, array $errors, Request $request): Response
    {
        $old = $this->postData($request);
        // The editor loads this as HTML, so it is cleaned even though nothing was saved.
        $old['body'] = $this->cms->clean($old['body']);

        flash('_errors', $errors);
        flash('_old_input', $old);

        return redirect($to);
    }

    /** @return array<string, mixed>|null */
    private function findOr404(string $id): ?array
    {
        return ctype_digit($id) ? $this->cms->find((int) $id) : null;
    }

    private function queryText(Request $request, string $key): string
    {
        $value = $request->query($key, '');

        return is_string($value) ? trim($value) : '';
    }

    /** @param array<string, mixed> $data */
    private function page(string $view, array $data): Response
    {
        return Response::html(View::render($view, $data, 'layouts/dashboard'));
    }
}

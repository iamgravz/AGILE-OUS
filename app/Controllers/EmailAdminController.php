<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\EmailDraftService;
use App\Services\EmailTemplateService;
use DomainException;

/** Email templates and announcement drafts. Rules and permission checks are in the services. */
final class EmailAdminController
{
    public function __construct(
        private readonly EmailTemplateService $templates = new EmailTemplateService(),
        private readonly EmailDraftService $drafts = new EmailDraftService(),
    ) {
    }

    public function templates(Request $request): Response
    {
        return $this->page('emails/admin/templates', [
            'templates' => $this->templates->list(),
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Email templates']],
        ]);
    }

    public function editTemplate(Request $request, string $id): Response
    {
        try {
            $template = $this->templates->find((int) $id);
        } catch (DomainException) {
            return Response::error(Response::HTTP_NOT_FOUND);
        }

        return $this->page('emails/admin/template_edit', [
            'template' => $template,
            'placeholders' => $this->templates->placeholders($template['code'], $template['subject'], $template['body_html']),
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Email templates', 'url' => route('email_templates')], ['label' => $template['code']]],
        ]);
    }

    public function updateTemplate(Request $request, string $id): Response
    {
        $to = route('email_templates.edit', ['id' => (int) $id]);
        $data = ['subject' => $request->text('subject'), 'body' => $request->text('body', false)];

        try {
            $this->templates->update((int) $id, $data['subject'], $data['body']);
            flash('success', 'The template was saved. The next email sent from it uses this wording.');
        } catch (DomainException $exception) {
            flash('_old_input', $data);
            flash('error', $exception->getMessage());
        }

        return redirect($to);
    }

    /** Opens in a frame on the edit page, so its email styles need their own, narrower rules. */
    public function previewTemplate(Request $request, string $id): Response
    {
        try {
            $html = $this->templates->preview((int) $id, $request->text('subject'), $request->text('body', false));
        } catch (DomainException $exception) {
            $html = '<!doctype html><title>Preview</title><p style="font-family:Arial,sans-serif;">' . e($exception->getMessage()) . '</p>';
        }

        return Response::html($html, Response::HTTP_OK, [
            'X-Frame-Options' => 'SAMEORIGIN',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; img-src 'self' data:; frame-ancestors 'self'",
        ]);
    }

    public function testTemplate(Request $request, string $id): Response
    {
        try {
            $this->templates->sendTest((int) $id);
            flash('success', 'A test email to you is queued. It goes out the next time the email queue runs.');
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
        }

        return redirect(route('email_templates.edit', ['id' => (int) $id]));
    }

    public function drafts(Request $request): Response
    {
        return $this->page('emails/admin/drafts', [
            'result' => $this->drafts->list(max(1, (int) $request->queryText('page'))),
            'service' => $this->drafts,
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Email drafts']],
        ]);
    }

    public function createDraft(Request $request): Response
    {
        authorize('email.compose');

        return $this->draftForm(null);
    }

    public function storeDraft(Request $request): Response
    {
        $data = $this->draftData($request);

        try {
            $id = $this->drafts->create($data['subject'], $data['body'], $data['target']);
        } catch (DomainException $exception) {
            flash('_old_input', $data);
            flash('error', $exception->getMessage());

            return redirect(route('emails.create'));
        }

        flash('success', 'The draft was saved. Check the recipient count, then send it.');

        return redirect(route('emails.show', ['id' => $id]));
    }

    public function showDraft(Request $request, string $id): Response
    {
        try {
            return $this->draftForm($this->drafts->find((int) $id));
        } catch (DomainException) {
            return Response::error(Response::HTTP_NOT_FOUND);
        }
    }

    public function updateDraft(Request $request, string $id): Response
    {
        $data = $this->draftData($request);

        try {
            $this->drafts->update((int) $id, $data['subject'], $data['body'], $data['target']);
            flash('success', 'The draft was saved.');
        } catch (DomainException $exception) {
            flash('_old_input', $data);
            flash('error', $exception->getMessage());
        }

        return redirect(route('emails.show', ['id' => (int) $id]));
    }

    public function sendDraft(Request $request, string $id): Response
    {
        try {
            $count = $this->drafts->send((int) $id);
            flash('success', $count === 1 ? '1 email is queued.' : "{$count} emails are queued.");
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
        }

        return redirect(route('emails.show', ['id' => (int) $id]));
    }

    /** @param array<string, mixed>|null $draft */
    private function draftForm(?array $draft): Response
    {
        return $this->page('emails/admin/draft_form', [
            'draft' => $draft,
            'targets' => $this->drafts->targets(),
            'recipientCount' => $draft === null ? null : $this->drafts->recipientCount($draft['target_group']),
            'breadcrumbs' => [['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Email drafts', 'url' => route('emails')], ['label' => $draft === null ? 'New email' : 'Draft ' . (int) $draft['draft_id']]],
        ]);
    }

    /** @return array{subject: string, body: string, target: string} */
    private function draftData(Request $request): array
    {
        return ['subject' => $request->text('subject'), 'body' => $request->text('body', false), 'target' => $request->text('target')];
    }

    /** @param array<string, mixed> $data */
    private function page(string $view, array $data): Response
    {
        return Response::html(View::render($view, $data, 'layouts/dashboard'));
    }
}

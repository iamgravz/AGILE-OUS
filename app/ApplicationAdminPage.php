<?php
declare(strict_types=1);
namespace Agile;

final class ApplicationAdminPage {
    public static function render(int $id, array $user): string {
        $application = ApplicationWorkflow::find($id);
        if (!$application) { return '<p>Application not found.</p>'; }
        $html = '<p><a href="/dashboard">← Back to staff dashboard</a></p>'
            . '<p><b>Reference:</b> ' . \escape($application['reference_code'])
            . ' &nbsp; <b>Status:</b> ' . \escape($application['status']) . '</p>'
            . '<dl><dt>Applicant</dt><dd>' . \escape($application['full_name']) . '</dd>'
            . '<dt>Email</dt><dd>' . \escape($application['email']) . '</dd>'
            . '<dt>Student number</dt><dd>' . \escape($application['student_number']) . '</dd>'
            . '<dt>Desired role</dt><dd>' . \escape($application['desired_role']) . '</dd>'
            . '<dt>Motivation</dt><dd>' . nl2br(\escape($application['motivation'])) . '</dd></dl>';

        $q = \db()->prepare('SELECT question_key, answer_text FROM application_answers WHERE application_id = ? ORDER BY id');
        $q->execute([$id]);
        $answers = $q->fetchAll();
        $html .= '<h2>Role-specific application answers</h2>';
        if (!$answers) { $html .= '<p>No additional answers were recorded.</p>'; }
        foreach ($answers as $answer) {
            $html .= '<p><b>' . \escape(ucwords(str_replace('_', ' ', $answer['question_key']))) . ':</b> '
                . nl2br(\escape($answer['answer_text'])) . '</p>';
        }
        if ($user['role'] === 'msw_head' && !in_array($application['status'], ['approved','rejected'], true)) {
            $reviewers = \db()->query("SELECT id,display_name FROM users WHERE role = 'msw_member' AND is_active = 1 ORDER BY display_name")->fetchAll();
            $html .= '<h2>Assign MSW reviewer</h2><form method="post" action="/application/assign">'
                . \formToken() . '<input type="hidden" name="id" value="' . $id . '">'
                . '<label>Reviewer<select name="reviewer_id"><option value="">Unassigned (MSW Head only)</option>';
            foreach ($reviewers as $reviewer) {
                $selected = (int)($application['assigned_to'] ?? 0) === (int)$reviewer['id'] ? ' selected' : '';
                $html .= '<option value="' . (int)$reviewer['id'] . '"' . $selected . '>'
                    . \escape($reviewer['display_name']) . '</option>';
            }
            $html .= '</select></label><button type="submit">Save reviewer assignment</button></form>';
        }

        $html .= '<h2>Verification prerequisites</h2><p>Interview completed: <b>'
            . ((int)$application['interview_completed'] ? 'Yes' : 'Not yet')
            . '</b> · Documents verified: <b>'
            . ((int)$application['documents_verified'] ? 'Yes' : 'Not yet') . '</b></p>';

        if ($user['role'] === 'msw_head' && !in_array($application['status'], ['approved','rejected'], true)) {
            $html .= '<form method="post" action="/application/verification">' . \formToken()
                . '<input type="hidden" name="id" value="' . $id . '">'
                . '<label><input style="display:inline;width:auto" type="checkbox" name="interview_completed" value="yes"'
                . ((int)$application['interview_completed'] ? ' checked' : '') . '> Interview completed and confirmed</label>'
                . '<label><input style="display:inline;width:auto" type="checkbox" name="documents_verified" value="yes"'
                . ((int)$application['documents_verified'] ? ' checked' : '') . '> Documents verified by authorized reviewer</label>'
                . '<label>Verification note<textarea name="note" required minlength="10" maxlength="1000"></textarea></label>'
                . '<button type="submit">Save verification</button></form>';
        }

        $available = ApplicationWorkflow::availableTargets($application['status'], $user['role']);
        if ($available) {
            $html .= '<h2>Update application status</h2>'
                . '<form method="post" action="/application/status">' . \formToken()
                . '<input type="hidden" name="id" value="' . $id . '">'
                . '<label>New status<select name="new_status">';
            foreach ($available as $target) {
                $html .= '<option value="' . \escape($target) . '">' . \escape(ucwords(str_replace('_', ' ', $target))) . '</option>';
            }
            $html .= '</select></label>'
                . '<label>Review note<textarea name="note" required minlength="10" maxlength="1000"></textarea></label>'
                . '<button type="submit">Save status change</button></form>';
        } else {
            $html .= '<p>No further status changes are permitted for your role or this application status.</p>';
        }

        $history = \db()->prepare('SELECT h.old_status,h.new_status,h.note,h.created_at,u.display_name
            FROM application_status_history h JOIN users u ON u.id=h.actor_user_id
            WHERE h.application_id = ? ORDER BY h.id DESC LIMIT 30');
        $history->execute([$id]);
        $html .= '<h2>Review history</h2>';
        foreach ($history->fetchAll() as $event) {
            $html .= '<p><b>' . \escape($event['created_at']) . ':</b> '
                . \escape($event['old_status']) . ' → ' . \escape($event['new_status'])
                . ' by ' . \escape($event['display_name'])
                . '<br>' . nl2br(\escape($event['note'])) . '</p>';
        }
        return $html;
    }
}

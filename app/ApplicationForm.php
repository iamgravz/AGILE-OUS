<?php
declare(strict_types=1);
namespace Agile;

final class ApplicationForm {
    public static function render(): string {
        $html = '<p>Apply for AGILE OUS membership using synthetic or authorized information only.</p>'
            . '<form method="post" action="/apply">' . \formToken()
            . '<label>Full name<input required maxlength="150" name="full_name" autocomplete="name"></label>'
            . '<label>Email<input required type="email" maxlength="190" name="email" autocomplete="email"></label>'
            . '<label>Student number<input required maxlength="32" name="student_number"></label>'
            . '<label>Application category<select id="desired-role" name="desired_role" required>';
        foreach (Membership::ROLES as $role) {
            $html .= '<option value="' . \escape($role) . '">' . \escape($role) . '</option>';
        }
        $html .= '</select></label>'
            . '<label>Why would you like to join?<textarea required minlength="10" maxlength="2000" name="motivation"></textarea></label>';
        foreach (Membership::ROLES as $role) {
            $html .= '<fieldset class="role-fields" data-role="' . \escape($role) . '" hidden>'
                . '<legend>' . \escape($role) . ' questions</legend>';
            foreach (ApplicationQuestions::forRole($role) as $q) {
                $key = \escape($q['key']);
                $label = \escape($q['label']);
                $required = !empty($q['required']) ? ' required' : '';
                $fieldName = 'answers[' . $key . ']';
                // Hidden fieldsets must not trigger client-side required checks.
                if ($q['type'] === 'textarea') {
                    $input = '<textarea maxlength="1200" name="' . $fieldName . '" rows="3"' . $required . '></textarea>';
                } elseif ($q['type'] === 'select') {
                    $input = '<select name="' . $fieldName . '"' . $required . '><option value="">Select a position</option>';
                    foreach ($q['options'] as $option) {
                        $input .= '<option value="' . \escape($option) . '">' . \escape($option) . '</option>';
                    }
                    $input .= '</select>';
                } else {
                    $type = $q['type'] === 'url' ? 'url' : 'text';
                    $input = '<input type="' . $type . '" maxlength="1200" name="' . $fieldName . '"' . $required . '>';
                }
                $html .= '<label>' . $label . $input . '</label>';
            }
            $html .= '</fieldset>';
        }
        $html .= '<label><input style="display:inline;width:auto" type="checkbox" name="privacy_consent" value="yes" required>'
            . ' I have read the <a href="/privacy" target="_blank" rel="noopener">privacy information</a> and agree to the collection of my application details for screening.</label>'
            . '<button type="submit">Submit Application</button></form>';
        $html .= <<<'HTML'
<script>
(function() {
 const role = document.getElementById('desired-role');
 const groups = Array.from(document.querySelectorAll('.role-fields'));
 function refresh() {
   for (const fieldset of groups) {
     const active = fieldset.dataset.role === role.value;
     fieldset.hidden = !active;
     for (const control of fieldset.querySelectorAll('input,textarea,select')) {
       control.disabled = !active;
       if (control.hasAttribute('data-was-required')) control.required = active;
       else if (control.required) {
         control.setAttribute('data-was-required', '');
         control.required = active;
       }
     }
   }
 }
 role.addEventListener('change', refresh);
 refresh();
})();
</script>
HTML;
        return $html;
    }
}

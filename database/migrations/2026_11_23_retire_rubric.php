<?php

declare(strict_types=1);

// Rubric scoring is replaced by the MSW interviewer's assessment (applicant_assessments). Its permission and its
// settings row go; the evaluations already saved stay in the evaluations table as history. Safe to run again.
return static function (PDO $db): void {
    $id = $db->query("SELECT permission_id FROM permissions WHERE code = 'evaluation.submit'")->fetchColumn();

    if ($id !== false) {
        $db->prepare('DELETE FROM role_permissions WHERE permission_id = ?')->execute([(int) $id]);
        $db->prepare('DELETE FROM user_permissions WHERE permission_id = ?')->execute([(int) $id]);
        $db->prepare('DELETE FROM permissions WHERE permission_id = ?')->execute([(int) $id]);
        echo "Retired the evaluation.submit permission.\n";
    }

    $db->exec("DELETE FROM settings WHERE setting_key = 'evaluation.rubric'");
};

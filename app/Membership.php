<?php
declare(strict_types=1);
namespace Agile;

final class Membership {
    public const ROLES = ['General Member', 'Committee Member', 'Deputy Committee Head', 'Committee Head', 'Executive Officer', 'The Source Code'];
    public static function validate(array $input): array {
        $errors = [];
        $name = trim((string)($input['full_name'] ?? ''));
        $email = strtolower(trim((string)($input['email'] ?? '')));
        $student = trim((string)($input['student_number'] ?? ''));
        $role = (string)($input['desired_role'] ?? '');
        $reason = trim((string)($input['motivation'] ?? ''));
        if (mb_strlen($name) < 3 || mb_strlen($name) > 150) $errors[] = 'Enter a full name (3–150 characters).';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) $errors[] = 'Enter a valid email.';
        if (!preg_match('/^[A-Za-z0-9-]{4,32}$/', $student)) $errors[] = 'Enter a valid student number.';
        if (!in_array($role, self::ROLES, true)) $errors[] = 'Choose a valid role.';
        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 2000) $errors[] = 'Motivation must be 10–2000 characters.';
        if (($input['privacy_consent'] ?? '') !== 'yes') $errors[] = 'Privacy consent is required.';
        if (in_array($role, self::ROLES, true)) {
            $errors = array_merge($errors, ApplicationQuestions::validate($role, $input['answers'] ?? []));
        }
        return $errors;
    }
    public static function submit(array $input): string {
        $errors = self::validate($input);
        if ($errors) throw new \InvalidArgumentException(implode(' ', $errors));
        $pdo = \db();
        $reference = 'AG-' . strtoupper(bin2hex(random_bytes(7)));
        $pdo->beginTransaction();
        try {
            $q = $pdo->prepare('INSERT INTO membership_applications (reference_code, full_name, email, student_number, desired_role, motivation, status, consent_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
            $q->execute([$reference,trim($input['full_name']),strtolower(trim($input['email'])),trim($input['student_number']),$input['desired_role'],trim($input['motivation']),'submitted']);
            $applicationId = (int)$pdo->lastInsertId();
            $answers = ApplicationQuestions::normalize($input['desired_role'], $input['answers'] ?? []);
            $qa = $pdo->prepare('INSERT INTO application_answers (application_id, question_key, answer_text) VALUES (?, ?, ?)');
            foreach ($answers as $key => $answer) { $qa->execute([$applicationId, $key, $answer]); }
            \audit(null,'application.submitted','membership_application',$applicationId);
            $pdo->commit();
        } catch (\Throwable $e) { $pdo->rollBack(); throw $e; }
        return $reference;
    }
}

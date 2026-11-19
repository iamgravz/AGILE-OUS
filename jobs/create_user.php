<?php

declare(strict_types=1);

// Creates an officer account and emails the link to set its password (BR-18). Their member record comes from an appointment.
// Usage: php jobs/create_user.php email first_name last_name role_slug [student_no]

require __DIR__ . '/_bootstrap.php';

use App\Services\AuthService;

[, $email, $firstName, $lastName, $role] = array_pad($argv, 5, '');
$studentNo = $argv[5] ?? null;

try {
    if ($email === '' || $firstName === '' || $lastName === '' || $role === '') {
        throw new DomainException('Usage: php jobs/create_user.php email first_name last_name role_slug [student_no]');
    }

    $userId = (new AuthService())->createAccount($email, $firstName, $lastName, $role, $studentNo);
    job_log("Created account {$userId}. The set-password email is queued.");
} catch (DomainException $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}

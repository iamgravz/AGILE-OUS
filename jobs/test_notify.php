<?php

declare(strict_types=1);

// Queues APP_SUBMITTED for the sample member: an in-app notification on the account, and the email to the
// address given here, because sample accounts use addresses that cannot receive mail.

use App\Models\UserRepository;
use App\Services\NotificationService;

require __DIR__ . '/_bootstrap.php';

if (config('app.env') === 'production') {
    exit("This test script does not run in production.\n");
}

$email = $argv[1] ?? '';

if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    exit("Usage: php jobs/test_notify.php you@example.com\n");
}

$user = (new UserRepository())->findBy(['email' => 'member@agile.test'])[0] ?? null;

if ($user === null) {
    exit("The sample member is missing. Run: php database/setup.php\n");
}

$payload = [
    'name' => $user['first_name'],
    'tracking_code' => 'TEST' . strtoupper(bin2hex(random_bytes(4))),
    'link' => '/track',
];

$service = new NotificationService();
$service->notify((int) $user['user_id'], 'APP_SUBMITTED', $payload, '/track', ['in_app']);
$id = $service->notifyEmail($email, 'APP_SUBMITTED', $payload);

echo "Queued email #{$id} to {$email}, with an in-app notification for {$user['email']}.\n";
echo "Next: php jobs/send_email_queue.php\n";

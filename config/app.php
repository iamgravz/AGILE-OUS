<?php

declare(strict_types=1);

return [
    'name' => env('APP_NAME', 'AGILE'),
    'env' => env('APP_ENV', 'production'),
    'debug' => env('APP_DEBUG', false),
    'url' => env('APP_URL', ''),
    // Read from .env, not the settings table, because error pages must render when the database is down.
    'contact_email' => env('ORG_CONTACT_EMAIL', 'contact@agile.test'),
    // The public footer's other contacts, also from .env for the same reason. Empty hides them.
    'msw_email' => (string) env('ORG_MSW_EMAIL', ''),
    'facebook_url' => (string) env('ORG_FACEBOOK_URL', ''),
    // Constitution, Article I Section 3 (domicile).
    'address' => (string) env('ORG_ADDRESS', 'PUP Open University System, Ninoy Aquino Library and Learning Resources Center (NALLRC), PUP Main Campus, Anonas St., Sta. Mesa, Manila'),
    'timezone' => env('APP_TIMEZONE', 'Asia/Manila'),
    // Adds database/seeds/sample_bulk.php (100 more made-up people) when seeding outside production.
    'sample_bulk' => env('SAMPLE_BULK', false),
    'session_idle_minutes' => (int) env('SESSION_IDLE_MINUTES', 30),
    'encryption_key' => env('ENCRYPTION_KEY', ''),
    'ai' => [
        // none or anthropic. Off unless someone turns it on, because turning it on sends documents to the provider.
        'provider' => env('AI_PROVIDER', 'none'),
        'api_key' => (string) env('AI_API_KEY', ''),
        'model' => env('AI_MODEL', 'claude-opus-5-5'),
        'base_url' => env('AI_BASE_URL', ''),
    ],
    'mail' => [
        'driver' => env('MAIL_DRIVER', 'log'),
        'from_address' => env('MAIL_FROM_ADDRESS', 'no-reply@agile.test'),
        'from_name' => env('MAIL_FROM_NAME', 'AGILE'),
        // Public URL of the banner, used only when public/assets/img/email-banner.jpg is missing. Emails show a text header when both are.
        'logo_url' => env('MAIL_LOGO_URL', ''),
        'smtp' => [
            'host' => env('SMTP_HOST', ''),
            'port' => (int) env('SMTP_PORT', 587),
            'user' => env('SMTP_USER', ''),
            'pass' => (string) env('SMTP_PASS', ''),
            'encryption' => env('SMTP_ENCRYPTION', 'tls'),
        ],
    ],
    'backup' => [
        // Found automatically in the usual XAMPP, Laragon and MySQL folders when empty.
        'mysqldump' => env('MYSQLDUMP_PATH', ''),
        // Empty means storage/backups.
        'directory' => env('BACKUP_DIR', ''),
    ],
    'database' => [
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => (int) env('DB_PORT', 3306),
        'name' => env('DB_NAME', 'agile_ams'),
        'user' => env('DB_USER', 'root'),
        'pass' => (string) env('DB_PASS', ''),
    ],
];

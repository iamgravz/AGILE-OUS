<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Session;
use App\Models\TokenRepository;
use App\Models\UserRepository;
use RuntimeException;

/** Everything here acts on the logged-in user, so one account can never change another. */
final class ProfileService
{
    private const PHOTO_SIZE = 400;
    private const PHOTO_MAX_MB = 5;
    private const PASSWORD_ATTEMPTS = 5;
    private const PASSWORD_WINDOW_MINUTES = 15;

    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
        private readonly TokenRepository $tokens = new TokenRepository(),
        private readonly FileStorageService $files = new FileStorageService(),
        private readonly ImageService $images = new ImageService(),
        private readonly RateLimiter $limiter = new RateLimiter(),
    ) {
    }

    public function updateContact(string $contactNo, ?string $yearLevel = null, ?string $section = null): void
    {
        $userId = $this->userId();
        $values = array_filter(['contact_no' => $contactNo, 'year_level' => $yearLevel, 'section' => $section], static fn (?string $value): bool => $value !== null);

        $this->users->update($userId, $values);
        AuditService::log('profile.updated', 'user', $userId, ['fields' => array_keys($values)]);
    }

    /**
     * @param array<string, mixed> $uploadedFile
     * @throws FileRejectedException
     */
    public function updatePhoto(array $uploadedFile): void
    {
        $userId = $this->userId();

        $this->files->validate($uploadedFile, ['jpg', 'jpeg', 'png'], self::PHOTO_MAX_MB);

        $jpeg = $this->images->squareJpeg($uploadedFile['tmp_name'], self::PHOTO_SIZE);
        $storedPath = $this->files->storeContents($jpeg, 'avatars', 'jpg');
        $previous = $this->users->find($userId)['photo_path'] ?? null;

        $this->users->update($userId, ['photo_path' => $storedPath]);
        AuditService::log('profile.photo_changed', 'user', $userId);

        if ($previous !== null) {
            $this->files->delete($previous);
        }
    }

    /** False when the current password is wrong, or after too many wrong attempts. */
    public function changePassword(string $currentPassword, string $newPassword): bool
    {
        $userId = $this->userId();
        $key = "password_change:{$userId}";

        if ($this->limiter->tooMany($key, self::PASSWORD_ATTEMPTS, self::PASSWORD_WINDOW_MINUTES)) {
            AuditService::log('password.change_failed', 'user', $userId, ['reason' => 'rate_limited']);

            return false;
        }

        $user = $this->users->find($userId);

        if (!password_verify($currentPassword, $user['password_hash'])) {
            $this->limiter->hit($key, self::PASSWORD_ATTEMPTS, self::PASSWORD_WINDOW_MINUTES);
            AuditService::log('password.change_failed', 'user', $userId, ['reason' => 'wrong_password']);

            return false;
        }

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);

        Database::transaction(function () use ($userId, $hash): void {
            $this->users->update($userId, ['password_hash' => $hash]);
            $this->tokens->revokeAll($userId, 'password_reset');
            AuditService::log('password.changed', 'user', $userId);
        });

        $this->limiter->clear($key);
        Session::regenerate();
        Csrf::rotate();
        // This login continues. Every other one ends at its next request.
        SessionGuard::remember($hash);

        return true;
    }

    private function userId(): int
    {
        return current_user_id() ?? throw new RuntimeException('ProfileService needs a logged-in user.');
    }
}

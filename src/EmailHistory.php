<?php

declare(strict_types=1);

namespace Chmajster\PlanWordPressSSO;

final class EmailHistory
{
    public const META_KEY = '_plan_sso_email_history';

    public static function boot(): void
    {
        add_action('profile_update', [self::class, 'captureChange'], 10, 3);
    }

    /**
     * WordPress hook callback. Parameters are intentionally not strictly typed.
     *
     * @param mixed $userId
     * @param mixed $oldUserData
     * @param mixed $userData
     */
    public static function captureChange($userId, $oldUserData, $userData): void
    {
        $userId = (int) $userId;
        if ($userId <= 0 || !is_object($oldUserData)) {
            return;
        }

        $oldEmail = Protocol::normalizeEmail((string) ($oldUserData->user_email ?? ''));
        $newEmail = '';

        if (is_array($userData) && array_key_exists('user_email', $userData)) {
            $newEmail = Protocol::normalizeEmail((string) $userData['user_email']);
        }

        if ($newEmail === '') {
            $currentUser = get_userdata($userId);
            if ($currentUser) {
                $newEmail = Protocol::normalizeEmail((string) $currentUser->user_email);
            }
        }

        if ($oldEmail === '' || $newEmail === '' || $oldEmail === $newEmail) {
            return;
        }

        $history = get_user_meta($userId, self::META_KEY, true);
        $history = is_array($history) ? $history : [];
        array_unshift($history, $oldEmail);

        update_user_meta(
            $userId,
            self::META_KEY,
            Protocol::normalizeEmailHistory($history, $newEmail, 5)
        );
    }

    /**
     * @return array<int, string>
     */
    public static function getForUser(int $userId, string $currentEmail): array
    {
        $history = get_user_meta($userId, self::META_KEY, true);

        return Protocol::normalizeEmailHistory(
            is_array($history) ? $history : [],
            $currentEmail,
            5
        );
    }
}

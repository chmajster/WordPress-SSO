<?php

declare(strict_types=1);

namespace Chmajster\PlanWordPressSSO;

use InvalidArgumentException;
use RuntimeException;

final class Protocol
{
    public const ALLOWED_ROLES = ['admin', 'leader', 'deputy', 'volunteer'];

    public static function normalizeEmail(string $email): string
    {
        $email = strtolower(trim($email));

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : '';
    }

    public static function isValidSecret(string $secret): bool
    {
        return preg_match('/^[a-fA-F0-9]{64,128}$/', $secret) === 1;
    }

    public static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    public static function generateNonce(int $bytes = 24): string
    {
        if ($bytes < 12 || $bytes > 96) {
            throw new InvalidArgumentException('Nieprawidłowa długość nonce.');
        }

        $nonce = self::base64UrlEncode(random_bytes($bytes));
        if (preg_match('/^[A-Za-z0-9_-]{16,128}$/', $nonce) !== 1) {
            throw new RuntimeException('Nie udało się wygenerować poprawnego nonce.');
        }

        return $nonce;
    }

    /**
     * @param array<int, mixed> $history
     * @return array<int, string>
     */
    public static function normalizeEmailHistory(array $history, string $currentEmail, int $limit = 5): array
    {
        $currentEmail = self::normalizeEmail($currentEmail);
        $result = [];

        foreach ($history as $value) {
            $email = self::normalizeEmail((string) $value);
            if ($email === '' || $email === $currentEmail || in_array($email, $result, true)) {
                continue;
            }

            $result[] = $email;
            if (count($result) >= $limit) {
                break;
            }
        }

        return $result;
    }

    /**
     * @param array<int, string> $previousEmails
     * @return array<string, mixed>
     */
    public static function buildPayload(
        string $ownerEmail,
        string $email,
        array $previousEmails,
        string $role,
        int $timestamp,
        string $nonce
    ): array {
        $ownerEmail = self::normalizeEmail($ownerEmail);
        $email = self::normalizeEmail($email);

        if ($ownerEmail === '' || $email === '') {
            throw new InvalidArgumentException('Payload wymaga poprawnych adresów e-mail.');
        }

        if (!in_array($role, self::ALLOWED_ROLES, true)) {
            throw new InvalidArgumentException('Nieobsługiwana rola SSO.');
        }

        if ($timestamp <= 0) {
            throw new InvalidArgumentException('Nieprawidłowy timestamp SSO.');
        }

        if (preg_match('/^[A-Za-z0-9_-]{16,128}$/', $nonce) !== 1) {
            throw new InvalidArgumentException('Nieprawidłowy nonce SSO.');
        }

        return [
            'owner' => $ownerEmail,
            'email' => $email,
            'previous_emails' => self::normalizeEmailHistory($previousEmails, $email, 5),
            'role' => $role,
            'ts' => $timestamp,
            'nonce' => $nonce,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function encodePayload(array $payload): string
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        $json = function_exists('wp_json_encode')
            ? wp_json_encode($payload, $flags)
            : json_encode($payload, $flags);

        if (!is_string($json)) {
            throw new RuntimeException('Nie udało się zakodować payloadu SSO.');
        }

        return self::base64UrlEncode($json);
    }

    public static function sign(string $encodedPayload, string $sharedSecret): string
    {
        if (!self::isValidSecret($sharedSecret)) {
            throw new InvalidArgumentException('Nieprawidłowy sekret SSO.');
        }

        return hash_hmac('sha256', $encodedPayload, $sharedSecret);
    }
}

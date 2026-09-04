<?php

declare(strict_types=1);

namespace Chmajster\PlanWordPressSSO;

use Throwable;

final class Sso
{
    private const QUERY_FLAG = 'plan_sso';

    public static function boot(): void
    {
        add_action('template_redirect', [self::class, 'handle']);
    }

    public static function handle(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only flag initiating SSO; no state is changed.
        $flag = isset($_GET[self::QUERY_FLAG])
            ? sanitize_text_field(wp_unslash((string) $_GET[self::QUERY_FLAG]))
            : '';

        if ($flag !== '1') {
            return;
        }

        if (!is_user_logged_in()) {
            $returnUrl = add_query_arg(self::QUERY_FLAG, '1', home_url('/'));
            wp_safe_redirect(wp_login_url($returnUrl));
            exit;
        }

        $settings = Settings::get();
        $planUrl = untrailingslashit((string) ($settings['plan_url'] ?? ''));
        $ownerEmail = Protocol::normalizeEmail((string) ($settings['owner_email'] ?? ''));
        $sharedSecret = (string) ($settings['shared_secret'] ?? '');

        if (!Settings::isValidPlanUrl($planUrl) || $ownerEmail === '' || !Protocol::isValidSecret($sharedSecret)) {
            self::fail(
                'Plan WordPress SSO nie jest poprawnie skonfigurowane. Sprawdź URL Plan, owner e-mail i wspólny sekret.',
                500,
                'Błąd konfiguracji SSO'
            );
        }

        $user = wp_get_current_user();
        $role = Roles::mapRoles((array) $user->roles);

        if ($role === null) {
            self::fail(
                'Twoje konto WordPress nie ma roli uprawniającej do korzystania z aplikacji Plan.',
                403,
                'Brak dostępu'
            );
        }

        $email = Protocol::normalizeEmail((string) $user->user_email);
        if ($email === '') {
            self::fail(
                'Konto WordPress nie ma poprawnego adresu e-mail.',
                403,
                'Brak dostępu'
            );
        }

        if ($role === 'admin' && !hash_equals($ownerEmail, $email)) {
            self::fail(
                'Konto administratora WordPress musi używać adresu e-mail właściciela skonfigurowanego dla aplikacji Plan.',
                403,
                'Brak dostępu'
            );
        }

        try {
            $payload = Protocol::buildPayload(
                $ownerEmail,
                $email,
                EmailHistory::getForUser((int) $user->ID, $email),
                $role,
                time(),
                Protocol::generateNonce()
            );
            $encodedPayload = Protocol::encodePayload($payload);
            $signature = Protocol::sign($encodedPayload, $sharedSecret);
        } catch (Throwable $exception) {
            self::fail(
                'Nie udało się utworzyć tokenu Plan WordPress SSO.',
                500,
                'Błąd SSO'
            );
        }

        $target = $planUrl
            . '/auth/wordpress?payload=' . rawurlencode($encodedPayload)
            . '&sig=' . rawurlencode($signature);

        if (!self::targetMatchesConfiguredPlan($target, $planUrl)) {
            self::fail(
                'Skonfigurowany adres przekierowania Plan jest nieprawidłowy.',
                500,
                'Błąd konfiguracji SSO'
            );
        }

        $planHost = strtolower((string) wp_parse_url($planUrl, PHP_URL_HOST));
        $allowConfiguredHost = static function (array $hosts) use ($planHost): array {
            if ($planHost !== '' && !in_array($planHost, $hosts, true)) {
                $hosts[] = $planHost;
            }

            return $hosts;
        };

        add_filter('allowed_redirect_hosts', $allowConfiguredHost);
        $redirected = wp_safe_redirect($target, 302, 'Plan WordPress SSO');
        remove_filter('allowed_redirect_hosts', $allowConfiguredHost);

        if (!$redirected) {
            self::fail(
                'WordPress odrzucił skonfigurowany adres przekierowania Plan.',
                500,
                'Błąd konfiguracji SSO'
            );
        }

        exit;
    }

    private static function targetMatchesConfiguredPlan(string $target, string $planUrl): bool
    {
        $targetParts = wp_parse_url($target);
        $planParts = wp_parse_url($planUrl);

        if (!is_array($targetParts) || !is_array($planParts)) {
            return false;
        }

        $targetScheme = strtolower((string) ($targetParts['scheme'] ?? ''));
        $planScheme = strtolower((string) ($planParts['scheme'] ?? ''));
        $targetHost = strtolower((string) ($targetParts['host'] ?? ''));
        $planHost = strtolower((string) ($planParts['host'] ?? ''));
        $targetPort = (int) ($targetParts['port'] ?? self::defaultPort($targetScheme));
        $planPort = (int) ($planParts['port'] ?? self::defaultPort($planScheme));
        $targetPath = (string) ($targetParts['path'] ?? '');

        return $targetScheme === $planScheme
            && $targetHost !== ''
            && hash_equals($planHost, $targetHost)
            && $targetPort === $planPort
            && str_ends_with($targetPath, '/auth/wordpress');
    }

    private static function defaultPort(string $scheme): int
    {
        return $scheme === 'https' ? 443 : 80;
    }

    private static function fail(string $message, int $status, string $title): void
    {
        wp_die(
            esc_html($message),
            esc_html($title),
            ['response' => $status]
        );
    }
}

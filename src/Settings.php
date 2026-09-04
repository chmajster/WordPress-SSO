<?php

declare(strict_types=1);

namespace Chmajster\PlanWordPressSSO;

final class Settings
{
    public const OPTION_NAME = 'plan_wordpress_sso_settings';
    private const SETTINGS_GROUP = 'plan_wordpress_sso';

    public static function boot(): void
    {
        add_action('admin_menu', [self::class, 'registerPage']);
        add_action('admin_init', [self::class, 'registerSettings']);
    }

    public static function registerPage(): void
    {
        add_options_page(
            'Plan WordPress SSO',
            'Plan WordPress SSO',
            'manage_options',
            'plan-wordpress-sso',
            [self::class, 'renderPage']
        );
    }

    public static function registerSettings(): void
    {
        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_NAME,
            [
                'type' => 'array',
                'sanitize_callback' => [self::class, 'sanitize'],
                'default' => [],
            ]
        );
    }

    /**
     * @param mixed $input
     * @return array<string, string>
     */
    public static function sanitize($input): array
    {
        $input = is_array($input) ? $input : [];
        $current = self::get();

        $planUrlInput = isset($input['plan_url'])
            ? trim((string) wp_unslash($input['plan_url']))
            : '';
        $ownerEmailInput = isset($input['owner_email'])
            ? (string) wp_unslash($input['owner_email'])
            : '';
        $secretInput = isset($input['shared_secret'])
            ? trim((string) wp_unslash($input['shared_secret']))
            : '';

        $planUrl = '';
        if ($planUrlInput !== '') {
            $candidate = untrailingslashit(esc_url_raw($planUrlInput));
            if (self::isValidPlanUrl($candidate)) {
                $planUrl = $candidate;
            } else {
                add_settings_error(
                    self::OPTION_NAME,
                    'invalid_plan_url',
                    'Adres aplikacji Plan musi być poprawnym adresem HTTP lub HTTPS bez danych logowania, query string ani fragmentu.'
                );
                $planUrl = (string) ($current['plan_url'] ?? '');
            }
        }

        $ownerEmail = Protocol::normalizeEmail(sanitize_email($ownerEmailInput));
        if ($ownerEmailInput !== '' && $ownerEmail === '') {
            add_settings_error(
                self::OPTION_NAME,
                'invalid_owner_email',
                'E-mail administratora/właściciela Plan jest nieprawidłowy.'
            );
            $ownerEmail = (string) ($current['owner_email'] ?? '');
        }

        $secret = (string) ($current['shared_secret'] ?? '');
        if ($secretInput !== '') {
            if (Protocol::isValidSecret($secretInput)) {
                $secret = $secretInput;
            } else {
                add_settings_error(
                    self::OPTION_NAME,
                    'invalid_shared_secret',
                    'Sekret SSO musi zawierać od 64 do 128 znaków szesnastkowych.'
                );
            }
        }

        return [
            'plan_url' => $planUrl,
            'owner_email' => $ownerEmail,
            'shared_secret' => $secret,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function get(): array
    {
        $settings = get_option(self::OPTION_NAME, []);

        return is_array($settings) ? $settings : [];
    }

    public static function isValidPlanUrl(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        $parts = wp_parse_url($url);
        if (!is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = trim((string) ($parts['host'] ?? ''));
        $hasCredentials = isset($parts['user']) || isset($parts['pass']);
        $hasQuery = isset($parts['query']);
        $hasFragment = isset($parts['fragment']);

        return in_array($scheme, ['http', 'https'], true)
            && $host !== ''
            && !$hasCredentials
            && !$hasQuery
            && !$hasFragment;
    }

    public static function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Brak uprawnień do konfiguracji Plan WordPress SSO.', 'Brak dostępu', ['response' => 403]);
        }

        $settings = self::get();
        $planUrl = (string) ($settings['plan_url'] ?? '');
        $ownerEmail = (string) ($settings['owner_email'] ?? '');
        $sharedSecret = (string) ($settings['shared_secret'] ?? '');

        $urlValid = self::isValidPlanUrl($planUrl);
        $ownerValid = Protocol::normalizeEmail($ownerEmail) !== '';
        $secretValid = Protocol::isValidSecret($sharedSecret);
        $ready = $urlValid && $ownerValid && $secretValid;
        $scheme = $urlValid ? strtolower((string) wp_parse_url($planUrl, PHP_URL_SCHEME)) : '';
        $ssoUrl = add_query_arg('plan_sso', '1', home_url('/'));
        ?>
        <div class="wrap">
            <h1><?php echo esc_html('Plan WordPress SSO'); ?></h1>
            <p><?php echo esc_html('WordPress uwierzytelnia użytkownika i przekazuje do aplikacji Plan krótko żyjący, podpisany token SSO.'); ?></p>

            <?php settings_errors(self::OPTION_NAME); ?>

            <?php if ($scheme === 'http') : ?>
                <div class="notice notice-warning">
                    <p><?php echo esc_html('Adres Plan używa HTTP. W środowisku produkcyjnym skonfiguruj HTTPS.'); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields(self::SETTINGS_GROUP); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="plan_wordpress_sso_plan_url"><?php echo esc_html('Adres aplikacji Plan'); ?></label>
                        </th>
                        <td>
                            <input
                                class="regular-text"
                                id="plan_wordpress_sso_plan_url"
                                name="<?php echo esc_attr(self::OPTION_NAME); ?>[plan_url]"
                                type="url"
                                value="<?php echo esc_attr($planUrl); ?>"
                                placeholder="https://plan.example.org"
                            >
                            <p class="description"><?php echo esc_html('Adres jest zapisywany bez końcowego ukośnika. Preferowane jest HTTPS.'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="plan_wordpress_sso_owner_email"><?php echo esc_html('E-mail administratora/właściciela Plan'); ?></label>
                        </th>
                        <td>
                            <input
                                class="regular-text"
                                id="plan_wordpress_sso_owner_email"
                                name="<?php echo esc_attr(self::OPTION_NAME); ?>[owner_email]"
                                type="email"
                                value="<?php echo esc_attr($ownerEmail); ?>"
                                placeholder="admin@example.org"
                            >
                            <p class="description"><?php echo esc_html('Administrator WordPress może użyć SSO tylko wtedy, gdy jego bieżący e-mail jest identyczny z tym adresem.'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="plan_wordpress_sso_shared_secret"><?php echo esc_html('Wspólny sekret SSO'); ?></label>
                        </th>
                        <td>
                            <input
                                class="regular-text code"
                                id="plan_wordpress_sso_shared_secret"
                                name="<?php echo esc_attr(self::OPTION_NAME); ?>[shared_secret]"
                                type="password"
                                value=""
                                autocomplete="new-password"
                                spellcheck="false"
                            >
                            <p class="description">
                                <?php
                                echo esc_html(
                                    $secretValid
                                        ? 'Sekret jest skonfigurowany. Pozostaw pole puste, aby zachować aktualny sekret.'
                                        : 'Sekret nie jest skonfigurowany. Wklej sekret wygenerowany przez aplikację Plan.'
                                );
                                ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Zapisz zmiany'); ?>
            </form>

            <h2><?php echo esc_html('Status konfiguracji'); ?></h2>
            <table class="widefat striped" style="max-width: 760px;">
                <tbody>
                    <tr>
                        <th><?php echo esc_html('URL Plan'); ?></th>
                        <td><?php echo esc_html($urlValid ? 'Poprawny' : 'Niepoprawny lub brak'); ?></td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html('Owner e-mail'); ?></th>
                        <td><?php echo esc_html($ownerValid ? 'Poprawny' : 'Niepoprawny lub brak'); ?></td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html('Sekret'); ?></th>
                        <td><?php echo esc_html($secretValid ? 'Skonfigurowany' : 'Nieskonfigurowany'); ?></td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html('SSO'); ?></th>
                        <td><?php echo esc_html($ready ? 'Gotowe do użycia' : 'Wymaga konfiguracji'); ?></td>
                    </tr>
                </tbody>
            </table>

            <h2><?php echo esc_html('Mapowanie ról'); ?></h2>
            <table class="widefat striped" style="max-width: 760px;">
                <thead>
                    <tr>
                        <th><?php echo esc_html('WordPress'); ?></th>
                        <th><?php echo esc_html('Plan'); ?></th>
                        <th><?php echo esc_html('Zakres'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td><code>administrator</code></td><td><code>admin</code></td><td><?php echo esc_html('Administrator'); ?></td></tr>
                    <tr><td><code>plan_leader</code></td><td><code>leader</code></td><td><?php echo esc_html('Leader'); ?></td></tr>
                    <tr><td><code>plan_deputy</code></td><td><code>deputy</code></td><td><?php echo esc_html('Zastępca'); ?></td></tr>
                    <tr><td><code>plan_volunteer</code></td><td><code>volunteer</code></td><td><?php echo esc_html('Wolontariusz'); ?></td></tr>
                </tbody>
            </table>

            <h2><?php echo esc_html('Integracja'); ?></h2>
            <p>
                <strong><?php echo esc_html('Adres rozpoczęcia SSO:'); ?></strong>
                <code><?php echo esc_html($ssoUrl); ?></code>
            </p>
            <p>
                <strong><?php echo esc_html('Shortcode:'); ?></strong>
                <code>[plan_sso_button]</code>
            </p>
        </div>
        <?php
    }
}

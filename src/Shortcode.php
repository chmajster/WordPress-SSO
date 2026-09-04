<?php

declare(strict_types=1);

namespace Chmajster\PlanWordPressSSO;

final class Shortcode
{
    public static function boot(): void
    {
        add_shortcode('plan_sso_button', [self::class, 'render']);
    }

    /**
     * @param mixed $atts
     */
    public static function render($atts = []): string
    {
        $atts = shortcode_atts(
            ['label' => 'Otwórz Plan'],
            is_array($atts) ? $atts : [],
            'plan_sso_button'
        );

        $label = sanitize_text_field((string) ($atts['label'] ?? 'Otwórz Plan'));
        if ($label === '') {
            $label = 'Otwórz Plan';
        }

        $ssoUrl = add_query_arg('plan_sso', '1', home_url('/'));

        if (!is_user_logged_in()) {
            return sprintf(
                '<a class="button" href="%s">%s</a>',
                esc_url(wp_login_url($ssoUrl)),
                esc_html($label)
            );
        }

        $role = Roles::mapRoles((array) wp_get_current_user()->roles);
        if ($role === null) {
            return sprintf(
                '<span class="button disabled" aria-disabled="true">%s</span>',
                esc_html($label)
            );
        }

        return sprintf(
            '<a class="button" href="%s">%s</a>',
            esc_url($ssoUrl),
            esc_html($label)
        );
    }
}

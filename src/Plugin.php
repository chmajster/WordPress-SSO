<?php

declare(strict_types=1);

namespace Chmajster\PlanWordPressSSO;

final class Plugin
{
    public static function boot(): void
    {
        register_activation_hook(PLAN_WORDPRESS_SSO_FILE, [Roles::class, 'activate']);

        Settings::boot();
        EmailHistory::boot();
        Sso::boot();
        Shortcode::boot();
    }
}

<?php

declare(strict_types=1);

defined('WP_UNINSTALL_PLUGIN') || exit;

delete_option('plan_wordpress_sso_settings');

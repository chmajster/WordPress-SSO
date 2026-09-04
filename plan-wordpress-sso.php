<?php
/**
 * Plugin Name: Plan WordPress SSO
 * Plugin URI:  https://github.com/chmajster/WordPress-SSO
 * Description: Bezpieczne logowanie SSO z WordPress do aplikacji Plan.
 * Version:     1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author:      chmajster
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: plan-wordpress-sso
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

define('PLAN_WORDPRESS_SSO_FILE', __FILE__);

require_once __DIR__ . '/src/Protocol.php';
require_once __DIR__ . '/src/Roles.php';
require_once __DIR__ . '/src/Settings.php';
require_once __DIR__ . '/src/EmailHistory.php';
require_once __DIR__ . '/src/Sso.php';
require_once __DIR__ . '/src/Shortcode.php';
require_once __DIR__ . '/src/Plugin.php';

\Chmajster\PlanWordPressSSO\Plugin::boot();

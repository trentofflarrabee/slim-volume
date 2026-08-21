<?php
/**
 * Plugin Name: Slim Volume
 * Plugin URI: https://github.com/trentofflarrabee/slim-volume
 * Description: A WordPress-native music catalog, release archive, track deep-dive system, and global audio player foundation.
 * Version: 0.3.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Slim Volume
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI: https://github.com/trentofflarrabee/slim-volume
 * Text Domain: slim-volume
 * Domain Path: /languages
 */

if (! defined('ABSPATH')) {
    exit;
}

if (! defined('SLIM_VOLUME_VERSION')) {
    define('SLIM_VOLUME_VERSION', '0.3.0');
}

if (! defined('SLIM_VOLUME_FILE')) {
    define('SLIM_VOLUME_FILE', __FILE__);
}

if (! defined('SLIM_VOLUME_PATH')) {
    define('SLIM_VOLUME_PATH', plugin_dir_path(__FILE__));
}

if (! defined('SLIM_VOLUME_URL')) {
    define('SLIM_VOLUME_URL', plugin_dir_url(__FILE__));
}

$slim_volume_requirement_headers = get_file_data(
    SLIM_VOLUME_FILE,
    [
        'wordpress' => 'Requires at least',
        'php'       => 'Requires PHP',
    ],
    'plugin'
);

$slim_volume_required_wordpress = isset($slim_volume_requirement_headers['wordpress'])
    && '' !== trim((string) $slim_volume_requirement_headers['wordpress'])
        ? trim((string) $slim_volume_requirement_headers['wordpress'])
        : '6.0';

$slim_volume_required_php = isset($slim_volume_requirement_headers['php'])
    && '' !== trim((string) $slim_volume_requirement_headers['php'])
        ? trim((string) $slim_volume_requirement_headers['php'])
        : '8.0';

$slim_volume_current_wordpress = isset($GLOBALS['wp_version'])
    ? (string) $GLOBALS['wp_version']
    : '0';

$slim_volume_meets_requirements = version_compare(
    $slim_volume_current_wordpress,
    $slim_volume_required_wordpress,
    '>='
) && version_compare(
    PHP_VERSION,
    $slim_volume_required_php,
    '>='
);

if (! $slim_volume_meets_requirements) {
    $slim_volume_requirements_notice = static function () use (
        $slim_volume_required_wordpress,
        $slim_volume_required_php,
        $slim_volume_current_wordpress
    ) {
        if (! current_user_can('activate_plugins')) {
            return;
        }

        ?>
        <div class="notice notice-error">
            <p>
                <?php
                printf(
                    /* translators: 1: required WordPress version, 2: required PHP version, 3: current WordPress version, 4: current PHP version. */
                    esc_html__(
                        'Slim Volume did not start because it requires WordPress %1$s or newer and PHP %2$s or newer. This site is running WordPress %3$s and PHP %4$s.',
                        'slim-volume'
                    ),
                    esc_html($slim_volume_required_wordpress),
                    esc_html($slim_volume_required_php),
                    esc_html($slim_volume_current_wordpress),
                    esc_html(PHP_VERSION)
                );
                ?>
            </p>
        </div>
        <?php
    };

    add_action('admin_notices', $slim_volume_requirements_notice);
    add_action('network_admin_notices', $slim_volume_requirements_notice);

    return;
}

unset(
    $slim_volume_requirement_headers,
    $slim_volume_required_wordpress,
    $slim_volume_required_php,
    $slim_volume_current_wordpress,
    $slim_volume_meets_requirements
);

require_once SLIM_VOLUME_PATH . 'includes/Functions.php';
require_once SLIM_VOLUME_PATH . 'includes/Plugin.php';

register_activation_hook(__FILE__, ['SlimVolume\\Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['SlimVolume\\Plugin', 'deactivate']);

add_action('plugins_loaded', static function () {
    SlimVolume\Plugin::instance()->boot();
});
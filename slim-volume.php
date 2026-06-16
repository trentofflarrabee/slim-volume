<?php
/**
 * Plugin Name: Slim Volume
 * Description: A WordPress-native music catalog, release archive, track deep-dive system, and global audio player foundation.
 * Version: 0.1.0
 * Author: Slim Volume
 * Text Domain: slim-volume
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

if (! defined('SLIM_VOLUME_VERSION')) {
    define('SLIM_VOLUME_VERSION', '0.1.0');
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

require_once SLIM_VOLUME_PATH . 'includes/Functions.php';
require_once SLIM_VOLUME_PATH . 'includes/Plugin.php';

register_activation_hook(__FILE__, ['SlimVolume\\Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['SlimVolume\\Plugin', 'deactivate']);

add_action('plugins_loaded', static function (): void {
    SlimVolume\Plugin::instance()->boot();
});
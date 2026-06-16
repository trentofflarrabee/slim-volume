<?php

declare(strict_types=1);

namespace SlimVolume;

if (! defined('ABSPATH')) {
    exit;
}

final class Assets
{
    public static function enqueue_frontend(): void
    {
        if (
            ! is_post_type_archive(PostTypes::RELEASE)
            && ! is_singular(PostTypes::RELEASE)
            && ! is_singular(PostTypes::TRACK)
        ) {
            return;
        }

        $css_path = SLIM_VOLUME_PATH . 'assets/css/slim-volume.css';
        $js_path  = SLIM_VOLUME_PATH . 'assets/js/slim-volume-player.js';

        if (file_exists($css_path)) {
            wp_enqueue_style(
                'slim-volume',
                SLIM_VOLUME_URL . 'assets/css/slim-volume.css',
                [],
                self::asset_version($css_path)
            );

            $appearance_css = Admin\Settings::get_appearance_css();

            if ($appearance_css !== '') {
                wp_add_inline_style('slim-volume', $appearance_css);
            }
        }

        if (file_exists($js_path)) {
            wp_enqueue_script(
                'slim-volume-player',
                SLIM_VOLUME_URL . 'assets/js/slim-volume-player.js',
                [],
                self::asset_version($js_path),
                true
            );
        }


        $settings = Admin\Settings::get_settings();

        wp_add_inline_script(
            'slim-volume-player',
            'window.SVConfig = ' . wp_json_encode(
                [
                    'version'          => defined('SLIM_VOLUME_VERSION') ? SLIM_VOLUME_VERSION : '0.1.0',
                    'ajaxNavigation'   => ! empty($settings['ajax_navigation']),
                    'persistence'      => ! empty($settings['persistence']),
                    'visualizer'       => ! empty($settings['visualizer']),
                    'debug'            => ! empty($settings['debug']) || (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG),
                    'contentSelector'  => '[data-sv-page-content]',
                    'musicBaseUrl'     => home_url('/music/'),
                ]
            ) . ';',
            'before'
        );

        $navigation_js_path = SLIM_VOLUME_PATH . 'assets/js/slim-volume-navigation.js';

        if (file_exists($navigation_js_path)) {
            wp_enqueue_script(
                'slim-volume-navigation',
                SLIM_VOLUME_URL . 'assets/js/slim-volume-navigation.js',
                ['slim-volume-player'],
                self::asset_version($navigation_js_path),
                true
            );


        }
    }

    public static function enqueue_admin(string $hook): void
    {
        if (! in_array($hook, ['post.php', 'post-new.php', 'edit.php'], true)) {
            return;
        }

        $screen = get_current_screen();

        if (
            ! $screen
            || ! in_array($screen->post_type, [PostTypes::RELEASE, PostTypes::TRACK], true)
        ) {
            return;
        }

        $css_path = SLIM_VOLUME_PATH . 'assets/css/admin.css';

        if (file_exists($css_path)) {
            wp_enqueue_style(
                'slim-volume-admin',
                SLIM_VOLUME_URL . 'assets/css/admin.css',
                [],
                self::asset_version($css_path)
            );
        }

        if ($screen->post_type === PostTypes::RELEASE) {
            wp_enqueue_script('jquery-ui-sortable');

            $release_js_path = SLIM_VOLUME_PATH . 'assets/js/admin-release-tracks.js';

            if (file_exists($release_js_path)) {
                wp_enqueue_script(
                    'slim-volume-admin-release-tracks',
                    SLIM_VOLUME_URL . 'assets/js/admin-release-tracks.js',
                    ['jquery', 'jquery-ui-sortable'],
                    self::asset_version($release_js_path),
                    true
                );
            }

            return;
        }

        if ($screen->post_type !== PostTypes::TRACK) {
            return;
        }

        wp_enqueue_media();

        $js_path = SLIM_VOLUME_PATH . 'assets/js/admin-track-media.js';

        if (file_exists($js_path)) {
            wp_enqueue_script(
                'slim-volume-admin-track-media',
                SLIM_VOLUME_URL . 'assets/js/admin-track-media.js',
                ['jquery'],
                self::asset_version($js_path),
                true
            );
        }
    }

    private static function asset_version(string $path): string
    {
        if (file_exists($path)) {
            return (string) filemtime($path);
        }

        return defined('SLIM_VOLUME_VERSION')
            ? (string) SLIM_VOLUME_VERSION
            : '0.1.0';
    }
}
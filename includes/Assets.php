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
                filemtime($css_path)
            );
        }

        if (file_exists($js_path)) {
            wp_enqueue_script(
                'slim-volume-player',
                SLIM_VOLUME_URL . 'assets/js/slim-volume-player.js',
                [],
                filemtime($js_path),
                true
            );
        }

        $navigation_js_path = SLIM_VOLUME_PATH . 'assets/js/slim-volume-navigation.js';

        if (file_exists($navigation_js_path)) {
            wp_enqueue_script(
                'slim-volume-navigation',
                SLIM_VOLUME_URL . 'assets/js/slim-volume-navigation.js',
                ['slim-volume-player'],
                filemtime($navigation_js_path),
                true
            );

            wp_add_inline_script(
                'slim-volume-navigation',
                'window.SVNavigationConfig = ' . wp_json_encode(
                    [
                        'musicBaseUrl'    => home_url('/music/'),
                        'contentSelector' => '[data-sv-page-content]',
                    ]
                ) . ';',
                'before'
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
                filemtime($css_path)
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
                    filemtime($release_js_path),
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
                filemtime($js_path),
                true
            );
        }
    }
}
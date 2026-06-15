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
    }

    public static function enqueue_admin(string $hook): void
    {
        if (! in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }

        $screen = get_current_screen();

        if (! $screen || $screen->post_type !== PostTypes::TRACK) {
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
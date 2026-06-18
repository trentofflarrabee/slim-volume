<?php
/**
 * Track admin list columns.
 *
 * @package SlimVolume
 */

declare(strict_types=1);

namespace SlimVolume\Admin;

use WP_Post;
use WP_Query;

if (! defined('ABSPATH')) {
    exit;
}

final class TrackAdminColumns
{
    public static function register(): void
    {
        add_filter('manage_sv_track_posts_columns', [self::class, 'columns'], 20);
        add_action('manage_sv_track_posts_custom_column', [self::class, 'render_column'], 10, 2);
        add_filter('manage_edit-sv_track_sortable_columns', [self::class, 'sortable_columns']);
        add_action('pre_get_posts', [self::class, 'handle_sorting']);
    }

    /**
     * @param array<string,string> $columns
     * @return array<string,string>
     */
    public static function columns(array $columns): array
    {
        $new_columns = [];

        if (isset($columns['cb'])) {
            $new_columns['cb'] = $columns['cb'];
        }

        $new_columns['title']      = $columns['title'] ?? __('Track', 'slim-volume');
        $new_columns['sv_release'] = __('Release', 'slim-volume');
        $new_columns['sv_audio']   = __('Audio', 'slim-volume');
        $new_columns['sv_artwork'] = __('Artwork', 'slim-volume');
        $new_columns['sv_order']   = __('Order', 'slim-volume');

        foreach ($columns as $key => $label) {
            if (isset($new_columns[$key]) || in_array($key, ['cb', 'title', 'date'], true)) {
                continue;
            }

            $new_columns[$key] = $label;
        }

        if (isset($columns['date'])) {
            $new_columns['date'] = $columns['date'];
        }

        return $new_columns;
    }

    public static function render_column(string $column, int $post_id): void
    {
        switch ($column) {
            case 'sv_release':
                self::render_release_column($post_id);
                break;

            case 'sv_audio':
                self::render_audio_column($post_id);
                break;

            case 'sv_artwork':
                self::render_artwork_column($post_id);
                break;

            case 'sv_order':
                echo esc_html((string) get_post_field('menu_order', $post_id));
                break;
        }
    }

    /**
     * @param array<string,string> $columns
     * @return array<string,string>
     */
    public static function sortable_columns(array $columns): array
    {
        $columns['sv_order'] = 'menu_order';

        return $columns;
    }

    public static function handle_sorting(WP_Query $query): void
    {
        if (! is_admin() || ! $query->is_main_query()) {
            return;
        }

        if ('sv_track' !== self::get_current_post_type($query)) {
            return;
        }

        if ('menu_order' !== $query->get('orderby')) {
            return;
        }

        $query->set(
            'orderby',
            [
                'menu_order' => 'ASC',
                'title'      => 'ASC',
            ]
        );
    }

    private static function render_release_column(int $post_id): void
    {
        $release_id = self::get_release_id($post_id);

        if ($release_id <= 0) {
            self::render_badge(__('Missing', 'slim-volume'), 'missing');
            return;
        }

        $release = get_post($release_id);

        if (! $release instanceof WP_Post || 'sv_release' !== $release->post_type) {
            self::render_badge(__('Invalid', 'slim-volume'), 'missing');
            return;
        }

        $edit_url = get_edit_post_link($release_id, '');
        $title    = get_the_title($release_id);

        if (! $title) {
            $title = __('Untitled release', 'slim-volume');
        }

        if ($edit_url) {
            printf(
                '<a href="%s"><strong>%s</strong></a>',
                esc_url($edit_url),
                esc_html($title)
            );
        } else {
            echo '<strong>' . esc_html($title) . '</strong>';
        }

        if ('publish' !== $release->post_status) {
            $status_object = get_post_status_object($release->post_status);
            $status_label  = $status_object ? $status_object->label : $release->post_status;

            echo '<br>';
            self::render_badge((string) $status_label, 'warning');
        }
    }

    private static function render_audio_column(int $post_id): void
    {
        $audio_url = self::get_audio_url($post_id);

        if (! $audio_url) {
            self::render_badge(__('Missing', 'slim-volume'), 'missing');
            return;
        }

        printf(
            '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
            esc_url($audio_url),
            esc_html__('Audio set', 'slim-volume')
        );
    }

    private static function render_artwork_column(int $post_id): void
    {
        if (has_post_thumbnail($post_id)) {
            self::render_badge(__('Track', 'slim-volume'), 'good');
            return;
        }

        $release_id = self::get_release_id($post_id);

        if ($release_id > 0 && has_post_thumbnail($release_id)) {
            self::render_badge(__('Release fallback', 'slim-volume'), 'warning');
            return;
        }

        self::render_badge(__('Missing', 'slim-volume'), 'missing');
    }

    private static function render_badge(string $label, string $type = 'neutral'): void
    {
        printf(
            '<span class="sv-admin-badge sv-admin-badge--%s">%s</span>',
            esc_attr($type),
            esc_html($label)
        );
    }

    private static function get_release_id(int $post_id): int
    {
        $release_id = (int) get_post_meta($post_id, '_sv_release_id', true);

        if ($release_id > 0) {
            return $release_id;
        }

        return (int) get_post_field('post_parent', $post_id);
    }

    private static function get_audio_url(int $post_id): string
    {
        $attachment_keys = [
            '_sv_audio_id',
            '_sv_audio_file_id',
            '_sv_track_audio_id',
            '_sv_audio_attachment_id',
        ];

        foreach ($attachment_keys as $key) {
            $attachment_id = (int) get_post_meta($post_id, $key, true);

            if ($attachment_id <= 0) {
                continue;
            }

            $url = wp_get_attachment_url($attachment_id);

            if (is_string($url) && $url) {
                return $url;
            }
        }

        $url_keys = [
            '_sv_audio_url',
            '_sv_track_audio_url',
            '_sv_file_url',
        ];

        foreach ($url_keys as $key) {
            $url = (string) get_post_meta($post_id, $key, true);

            if ($url) {
                return $url;
            }
        }

        return '';
    }

    private static function get_current_post_type(WP_Query $query): string
    {
        $post_type = $query->get('post_type');

        if (is_array($post_type)) {
            return (string) reset($post_type);
        }

        if (is_string($post_type) && $post_type) {
            return $post_type;
        }

        if (isset($_GET['post_type'])) {
            return sanitize_key(wp_unslash($_GET['post_type']));
        }

        return 'post';
    }
}
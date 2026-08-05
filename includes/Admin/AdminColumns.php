<?php

declare(strict_types=1);

namespace SlimVolume\Admin;

use SlimVolume\PostTypes;

if (! defined('ABSPATH')) {
    exit;
}

final class AdminColumns
{
    public static function register(): void
    {
        add_filter('manage_' . PostTypes::RELEASE . '_posts_columns', [self::class, 'release_columns']);
        add_action('manage_' . PostTypes::RELEASE . '_posts_custom_column', [self::class, 'render_release_column'], 10, 2);
        add_filter('manage_edit-' . PostTypes::RELEASE . '_sortable_columns', [self::class, 'release_sortable_columns']);

        add_filter('manage_' . PostTypes::TRACK . '_posts_columns', [self::class, 'track_columns']);
        add_action('manage_' . PostTypes::TRACK . '_posts_custom_column', [self::class, 'render_track_column'], 10, 2);
        add_filter('manage_edit-' . PostTypes::TRACK . '_sortable_columns', [self::class, 'track_sortable_columns']);

        add_action('pre_get_posts', [self::class, 'handle_admin_sorting_and_filters']);
    }

    public static function release_columns(array $columns): array
    {
        $new = [];

        foreach ($columns as $key => $label) {
            if ($key === 'cb') {
                $new[$key] = $label;
                $new['sv_artwork'] = __('Art', 'slim-volume');
                continue;
            }

            $new[$key] = $label;

            if ($key === 'title') {
                $new['sv_release_type'] = __('Type', 'slim-volume');
                $new['sv_release_date'] = __('Release Date', 'slim-volume');
                $new['sv_track_count']  = __('Tracks', 'slim-volume');
                $new['sv_featured']     = __('Featured', 'slim-volume');
            }
        }

        return $new;
    }

    public static function render_release_column(string $column, int $post_id): void
    {
        switch ($column) {
            case 'sv_artwork':
                self::render_artwork_thumb($post_id);
                break;

            case 'sv_release_type':
                echo esc_html((string) get_post_meta($post_id, '_sv_release_type', true));
                break;

            case 'sv_release_date':
                $date = (string) get_post_meta($post_id, '_sv_release_date', true);
                echo $date ? esc_html($date) : '&mdash;';
                break;

            case 'sv_track_count':
                echo esc_html((string) self::count_tracks_for_release($post_id));
                break;

            case 'sv_featured':
                $featured = (bool) get_post_meta($post_id, '_sv_featured_release', true);
                echo $featured ? esc_html__('Yes', 'slim-volume') : '&mdash;';
                break;
        }
    }

    public static function release_sortable_columns(array $columns): array
    {
        $columns['sv_release_date'] = 'sv_release_date';
        $columns['sv_release_type'] = 'sv_release_type';

        return $columns;
    }

    public static function track_columns(array $columns): array
    {
        $new = [];

        foreach ($columns as $key => $label) {
            if ($key === 'cb') {
                $new[$key] = $label;
                $new['sv_artwork'] = __('Art', 'slim-volume');
                continue;
            }

            $new[$key] = $label;

            if ($key === 'title') {
                $new['sv_release']     = __('Release', 'slim-volume');
                $new['sv_track_number'] = __('#', 'slim-volume');
                $new['sv_duration']    = __('Duration', 'slim-volume');
                $new['sv_audio']       = __('Audio', 'slim-volume');
                $new['sv_lyrics']      = __('Lyrics', 'slim-volume');
                $new['sv_download']    = __('Download', 'slim-volume');
            }
        }

        return $new;
    }

    public static function render_track_column(string $column, int $post_id): void
    {
        switch ($column) {
            case 'sv_artwork':
                self::render_artwork_thumb($post_id, self::get_track_release_id($post_id));
                break;

            case 'sv_release':
                $release_id = self::get_track_release_id($post_id);

                if ($release_id) {
                    $edit_url = get_edit_post_link($release_id);

                    if ($edit_url) {
                        printf(
                            '<a href="%s">%s</a>',
                            esc_url($edit_url),
                            esc_html(get_the_title($release_id))
                        );
                    } else {
                        echo esc_html(get_the_title($release_id));
                    }
                } else {
                    echo '&mdash;';
                }
                break;

            case 'sv_track_number':
                $number = (int) get_post_meta($post_id, '_sv_track_number', true);
                echo $number > 0 ? esc_html((string) $number) : '&mdash;';
                break;

            case 'sv_duration':
                $duration = (string) get_post_meta($post_id, '_sv_duration', true);
                echo $duration ? esc_html($duration) : '&mdash;';
                break;

            case 'sv_audio':
                self::render_audio_status($post_id);
                break;

            case 'sv_lyrics':
                $lyrics = trim((string) get_post_meta($post_id, '_sv_lyrics', true));
                echo $lyrics !== '' ? esc_html__('Yes', 'slim-volume') : '&mdash;';
                break;

            case 'sv_download':
                self::render_download_status($post_id);
                break;
        }
    }

    public static function track_sortable_columns(array $columns): array
    {
        $columns['sv_track_number'] = 'sv_track_number';
        $columns['sv_duration']     = 'sv_duration';
        $columns['sv_release']      = 'sv_release';

        return $columns;
    }

    public static function handle_admin_sorting_and_filters(\WP_Query $query): void
    {
        if (! is_admin() || ! $query->is_main_query()) {
            return;
        }

        $post_type = $query->get('post_type');

        if ($post_type === PostTypes::RELEASE) {
            $orderby = $query->get('orderby');

            if ($orderby === 'sv_release_date') {
                $query->set('meta_key', '_sv_release_date');
                $query->set('orderby', 'meta_value');
            }

            if ($orderby === 'sv_release_type') {
                $query->set('meta_key', '_sv_release_type');
                $query->set('orderby', 'meta_value');
            }

            return;
        }

        if ($post_type !== PostTypes::TRACK) {
            return;
        }

        $orderby = $query->get('orderby');

        if ($orderby === 'sv_track_number') {
            $query->set('meta_key', '_sv_track_number');
            $query->set('orderby', 'meta_value_num');
        }

        if ($orderby === 'sv_duration') {
            $query->set('meta_key', '_sv_duration');
            $query->set('orderby', 'meta_value');
        }

        if ($orderby === 'sv_release') {
            $query->set('meta_key', '_sv_release_id');
            $query->set('orderby', 'meta_value_num');
        }
    }

    private static function render_artwork_thumb(int $post_id, int $fallback_post_id = 0): void
    {
        $thumb_id = get_post_thumbnail_id($post_id);

        if (! $thumb_id && $fallback_post_id > 0) {
            $thumb_id = get_post_thumbnail_id($fallback_post_id);
        }

        if (! $thumb_id) {
            echo '<span class="sv-admin-thumb sv-admin-thumb--empty" aria-hidden="true"></span>';
            return;
        }

        echo wp_get_attachment_image(
            $thumb_id,
            [56, 56],
            false,
            [
                'class' => 'sv-admin-thumb',
            ]
        );
    }

    private static function render_audio_status(int $post_id): void
    {
        $attachment_id = (int) get_post_meta($post_id, '_sv_audio_attachment_id', true);
        $external_url  = (string) get_post_meta($post_id, '_sv_audio_url', true);

        if ($attachment_id > 0) {
            $url = wp_get_attachment_url($attachment_id);
            $filename = $url ? basename(wp_parse_url($url, PHP_URL_PATH) ?: $url) : __('Attachment', 'slim-volume');
            echo '<span class="sv-admin-status sv-admin-status--yes">';
            echo esc_html($filename);
            echo '</span>';
            return;
        }

        if ($external_url) {
            echo '<span class="sv-admin-status sv-admin-status--yes">';
            esc_html_e('External URL', 'slim-volume');
            echo '</span>';
            return;
        }

        echo '<span class="sv-admin-status sv-admin-status--no">';
        esc_html_e('Missing', 'slim-volume');
        echo '</span>';
    }

    private static function render_download_status(int $post_id): void
    {
        $can_download = (bool) get_post_meta($post_id, '_sv_can_download', true);

        if (! $can_download) {
            echo '&mdash;';
            return;
        }

        $attachment_id = (int) get_post_meta($post_id, '_sv_download_attachment_id', true);
        $download_url  = (string) get_post_meta($post_id, '_sv_download_url', true);

        if ($attachment_id > 0 || $download_url) {
            echo '<span class="sv-admin-status sv-admin-status--yes">';
            esc_html_e('Enabled', 'slim-volume');
            echo '</span>';
            return;
        }

        echo '<span class="sv-admin-status sv-admin-status--warning">';
        esc_html_e('Enabled, no file', 'slim-volume');
        echo '</span>';
    }

    private static function count_tracks_for_release(int $release_id): int
    {
        return count(
            \SlimVolume\Relationships\TrackReleaseRelationship
                ::get_track_ids_for_release(
                    $release_id,
                    [
                        'publish',
                        'draft',
                        'private',
                    ]
                )
        );
    }

    private static function get_track_release_id(int $track_id): int
    {
        return \SlimVolume\Relationships\TrackReleaseRelationship
            ::get_release_id($track_id);
    }
}
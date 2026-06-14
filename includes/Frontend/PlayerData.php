<?php

declare(strict_types=1);

namespace SlimVolume\Frontend;

use SlimVolume\PostTypes;
use SlimVolume\Rewrite;
use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

final class PlayerData
{
    public static function get_track_data(int $track_id): array
    {
        $track = get_post($track_id);

        if (! $track || $track->post_type !== PostTypes::TRACK) {
            return [];
        }

        $release_id = Rewrite::get_track_release_id($track_id);
        $release    = $release_id ? get_post($release_id) : null;

        $audio_url = self::get_track_audio_url($track_id);
        $artwork   = self::get_track_artwork($track_id, $release_id);
        $links     = self::get_track_links($track_id, $release_id);

        return [
            'id'              => $track_id,
            'title'           => get_the_title($track_id),
            'slug'            => $track->post_name,
            'trackNumber'     => (int) get_post_meta($track_id, '_sv_track_number', true),
            'duration'        => (string) get_post_meta($track_id, '_sv_duration', true),
            'durationSeconds' => (int) get_post_meta($track_id, '_sv_duration_seconds', true),
            'audioUrl'        => $audio_url,
            'trackUrl'        => get_permalink($track_id),

            'release' => $release instanceof WP_Post
                ? [
                    'id'    => $release_id,
                    'title' => get_the_title($release_id),
                    'slug'  => $release->post_name,
                    'url'   => get_permalink($release_id),
                ]
                : null,

            'artwork' => $artwork,

            'links' => $links,

            'availability' => [
                'canPlay'     => $audio_url !== '',
                'canDownload' => (bool) get_post_meta($track_id, '_sv_can_download', true),
                'canPurchase' => ! empty($links['purchase']) || ! empty($links['bandcamp']),
            ],
        ];
    }

    public static function get_release_playlist(int $release_id): array
    {
        /*
        * Primary relationship: _sv_release_id.
        */
        $tracks = get_posts(
            [
                'post_type'      => PostTypes::TRACK,
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'meta_key'       => '_sv_track_number',
                'orderby'        => [
                    'meta_value_num' => 'ASC',
                    'menu_order'     => 'ASC',
                    'title'          => 'ASC',
                ],
                'order'          => 'ASC',
                'meta_query'     => [
                    [
                        'key'     => '_sv_release_id',
                        'value'   => $release_id,
                        'compare' => '=',
                        'type'    => 'NUMERIC',
                    ],
                ],
            ]
        );

        /*
        * Fallback relationship: post_parent.
        */
        if (! $tracks) {
            $tracks = get_posts(
                [
                    'post_type'      => PostTypes::TRACK,
                    'post_status'    => 'publish',
                    'post_parent'    => $release_id,
                    'posts_per_page' => -1,
                    'orderby'        => [
                        'menu_order' => 'ASC',
                        'title'      => 'ASC',
                    ],
                    'order'          => 'ASC',
                ]
            );
        }

        $playlist = [];

        foreach ($tracks as $track) {
            $data = self::get_track_data((int) $track->ID);

            if ($data) {
                $playlist[] = $data;
            }
        }

        return $playlist;
    }

    public static function get_release_page_config(int $release_id): array
    {
        return [
            'context'      => 'release',
            'releaseId'    => $release_id,
            'playlist'     => self::get_release_playlist($release_id),
            'currentIndex' => 0,
            'autoplay'     => false,
        ];
    }

    public static function get_track_page_config(int $track_id): array
    {
        $release_id = Rewrite::get_track_release_id($track_id);
        $playlist   = $release_id ? self::get_release_playlist($release_id) : [];

        $current_index = 0;

        foreach ($playlist as $index => $track) {
            if ((int) ($track['id'] ?? 0) === $track_id) {
                $current_index = (int) $index;
                break;
            }
        }

        return [
            'context'      => 'track',
            'releaseId'    => $release_id,
            'trackId'      => $track_id,
            'playlist'     => $playlist,
            'currentIndex' => $current_index,
            'autoplay'     => false,
        ];
    }

    private static function get_track_audio_url(int $track_id): string
    {
        $attachment_id = (int) get_post_meta($track_id, '_sv_audio_attachment_id', true);

        if ($attachment_id > 0) {
            $url = wp_get_attachment_url($attachment_id);

            if ($url) {
                return esc_url_raw($url);
            }
        }

        $external_url = (string) get_post_meta($track_id, '_sv_audio_url', true);

        return $external_url ? esc_url_raw($external_url) : '';
    }

    private static function get_track_artwork(int $track_id, int $release_id = 0): array
    {
        $image_id = get_post_thumbnail_id($track_id);

        if (! $image_id && $release_id > 0) {
            $image_id = get_post_thumbnail_id($release_id);
        }

        if (! $image_id) {
            return [
                'url' => '',
                'alt' => '',
            ];
        }

        $url = wp_get_attachment_image_url($image_id, 'large') ?: '';

        return [
            'url' => $url,
            'alt' => get_post_meta($image_id, '_wp_attachment_image_alt', true) ?: '',
        ];
    }

    private static function get_track_links(int $track_id, int $release_id = 0): array
    {
        $map = [
            'spotify'    => '_sv_spotify_url',
            'appleMusic' => '_sv_apple_music_url',
            'youtube'    => '_sv_youtube_url',
            'bandcamp'   => '_sv_bandcamp_url',
            'purchase'   => '_sv_purchase_url',
            'download'   => '_sv_download_url',
        ];

        $links = [];

        foreach ($map as $name => $meta_key) {
            $value = (string) get_post_meta($track_id, $meta_key, true);

            if (! $value && $release_id > 0 && $name !== 'download') {
                $value = (string) get_post_meta($release_id, $meta_key, true);
            }

            $links[$name] = $value ? esc_url_raw($value) : '';
        }

        return $links;
    }

    public static function render_page_config(array $config): void
    {
        echo '<script type="application/json" data-sv-player-config>';
        echo wp_json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        echo '</script>';
    }
}
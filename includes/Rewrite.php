<?php

declare(strict_types=1);

namespace SlimVolume;

use WP_Query;

if (! defined('ABSPATH')) {
    exit;
}

final class Rewrite
{
    public static function register(): void
    {
        add_rewrite_rule(
            '^music/([^/]+)/([^/]+)/?$',
            'index.php?sv_release_slug=$matches[1]&sv_track_slug=$matches[2]',
            'top'
        );
    }

    public static function add_query_vars(array $vars): array
    {
        $vars[] = 'sv_release_slug';
        $vars[] = 'sv_track_slug';

        return $vars;
    }

    public static function resolve_nested_track_query(WP_Query $query): void
    {
        if (is_admin() || ! $query->is_main_query()) {
            return;
        }

        $release_slug = get_query_var('sv_release_slug');
        $track_slug   = get_query_var('sv_track_slug');

        if (! $release_slug || ! $track_slug) {
            return;
        }

        $release = get_page_by_path(
            sanitize_title((string) $release_slug),
            OBJECT,
            PostTypes::RELEASE
        );

        if (! $release) {
            $query->set_404();
            status_header(404);
            return;
        }

        $track_id = self::find_track_for_release(
            (int) $release->ID,
            sanitize_title((string) $track_slug)
        );

        if (! $track_id) {
            $query->set_404();
            status_header(404);
            return;
        }

        $query->set('p', $track_id);
        $query->set('post_type', PostTypes::TRACK);
        $query->set('name', '');

        $query->is_single   = true;
        $query->is_singular = true;
        $query->is_archive  = false;
        $query->is_home     = false;
        $query->is_404      = false;
    }

    public static function find_track_for_release(int $release_id, string $track_slug): int
    {
        if ($release_id <= 0 || $track_slug === '') {
            return 0;
        }

        /*
         * Primary relationship: _sv_release_id.
         */
        $tracks = get_posts(
            [
                'post_type'      => PostTypes::TRACK,
                'post_status'    => 'publish',
                'name'           => $track_slug,
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
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

        if ($tracks) {
            return (int) $tracks[0];
        }

        /*
         * Fallback relationship: post_parent.
         */
        $tracks = get_posts(
            [
                'post_type'      => PostTypes::TRACK,
                'post_status'    => 'publish',
                'name'           => $track_slug,
                'post_parent'    => $release_id,
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
            ]
        );

        return $tracks ? (int) $tracks[0] : 0;
    }

    public static function filter_track_permalink(string $permalink, \WP_Post $post): string
    {
        if ($post->post_type !== PostTypes::TRACK) {
            return $permalink;
        }

        $release_id = self::get_track_release_id((int) $post->ID);

        if (! $release_id) {
            return $permalink;
        }

        $release = get_post($release_id);

        if (! $release || $release->post_type !== PostTypes::RELEASE) {
            return $permalink;
        }

        return home_url(
            user_trailingslashit(
                sprintf(
                    'music/%s/%s',
                    $release->post_name,
                    $post->post_name
                )
            )
        );
    }

    public static function get_track_release_id(int $track_id): int
    {
        $release_id = (int) get_post_meta($track_id, '_sv_release_id', true);

        if ($release_id > 0) {
            return $release_id;
        }

        $track = get_post($track_id);

        if ($track && $track->post_parent > 0) {
            return (int) $track->post_parent;
        }

        return 0;
    }
}
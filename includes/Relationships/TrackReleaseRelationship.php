<?php

declare(strict_types=1);

namespace SlimVolume\Relationships;

use SlimVolume\PostTypes;
use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Centralized reader for the relationship between tracks and releases.
 *
 * Relationship policy:
 *
 * - _sv_release_id is the canonical relationship.
 * - post_parent is a compatibility fallback and synchronized shadow.
 * - When both values are valid but disagree, _sv_release_id wins.
 */
final class TrackReleaseRelationship
{
    public const META_KEY = '_sv_release_id';

    /**
     * Resolve the release associated with a track.
     *
     * A valid canonical meta relationship wins. A valid post_parent is used
     * only when the canonical relationship is missing or invalid.
     */
    public static function get_release_id(int $track_id): int
    {
        $state = self::get_state($track_id);

        return $state['resolved_release_id'];
    }

    /**
     * Describe the current relationship state for a track.
     *
     * @return array{
     *     meta_release_id:int,
     *     parent_release_id:int,
     *     resolved_release_id:int,
     *     has_conflict:bool,
     *     needs_repair:bool
     * }
     */
    public static function get_state(int $track_id): array
    {
        $empty = [
            'meta_release_id'     => 0,
            'parent_release_id'   => 0,
            'resolved_release_id' => 0,
            'has_conflict'        => false,
            'needs_repair'        => false,
        ];

        if ($track_id <= 0) {
            return $empty;
        }

        $track = get_post($track_id);

        if (
            ! $track instanceof WP_Post
            || PostTypes::TRACK !== $track->post_type
        ) {
            return $empty;
        }

        $raw_meta_release_id = (int) get_post_meta(
            $track_id,
            self::META_KEY,
            true
        );

        $raw_parent_release_id = (int) $track->post_parent;

        $meta_release_id = self::is_valid_release(
            $raw_meta_release_id
        )
            ? $raw_meta_release_id
            : 0;

        $parent_release_id = self::is_valid_release(
            $raw_parent_release_id
        )
            ? $raw_parent_release_id
            : 0;

        $resolved_release_id = $meta_release_id > 0
            ? $meta_release_id
            : $parent_release_id;

        $has_conflict = (
            $meta_release_id > 0
            && $parent_release_id > 0
            && $meta_release_id !== $parent_release_id
        );

        $needs_repair = false;

        if ($resolved_release_id > 0) {
            $needs_repair = (
                $raw_meta_release_id !== $resolved_release_id
                || $raw_parent_release_id !== $resolved_release_id
            );
        } elseif (
            $raw_meta_release_id > 0
            || $raw_parent_release_id > 0
        ) {
            /*
             * One or both stored IDs point to objects that are not valid
             * Slim Volume releases.
             */
            $needs_repair = true;
        }

        return [
            'meta_release_id'     => $meta_release_id,
            'parent_release_id'   => $parent_release_id,
            'resolved_release_id' => $resolved_release_id,
            'has_conflict'        => $has_conflict,
            'needs_repair'        => $needs_repair,
        ];
    }

    /**
     * Return tracks that resolve to a release through either supported
     * relationship field.
     *
     * Candidates from both relationship sources are merged and deduplicated.
     * The canonical resolver is then applied so a stale post_parent cannot
     * place a track in two releases.
     *
     * @param string[] $post_statuses
     *
     * @return WP_Post[]
     */
    public static function get_tracks_for_release(
        int $release_id,
        array $post_statuses = ['publish']
    ): array {
        if (! self::is_valid_release($release_id)) {
            return [];
        }

        $post_statuses = array_values(
            array_filter(
                array_map(
                    static function (mixed $status): string {
                        return sanitize_key((string) $status);
                    },
                    $post_statuses
                )
            )
        );

        if (! $post_statuses) {
            $post_statuses = ['publish'];
        }

        $base_query = [
            'post_type'              => PostTypes::TRACK,
            'post_status'            => $post_statuses,
            'posts_per_page'         => -1,
            'orderby'                => [
                'menu_order' => 'ASC',
                'title'      => 'ASC',
            ],
            'order'                  => 'ASC',
            'no_found_rows'          => true,
            'update_post_meta_cache' => true,
            'update_post_term_cache' => false,
        ];

        $tracks_by_meta = get_posts(
            array_merge(
                $base_query,
                [
                    'meta_query' => [
                        [
                            'key'     => self::META_KEY,
                            'value'   => $release_id,
                            'compare' => '=',
                            'type'    => 'NUMERIC',
                        ],
                    ],
                ]
            )
        );

        $tracks_by_parent = get_posts(
            array_merge(
                $base_query,
                [
                    'post_parent' => $release_id,
                ]
            )
        );

        $tracks_by_id = [];

        foreach (
            array_merge($tracks_by_meta, $tracks_by_parent)
            as $track
        ) {
            if (
                ! $track instanceof WP_Post
                || PostTypes::TRACK !== $track->post_type
            ) {
                continue;
            }

            $track_id = (int) $track->ID;

            /*
             * Re-resolve every candidate. This prevents a stale post_parent
             * from making a canonically reassigned track appear on both
             * releases.
             */
            if (self::get_release_id($track_id) !== $release_id) {
                continue;
            }

            $tracks_by_id[$track_id] = $track;
        }

        $tracks = array_values($tracks_by_id);

        usort(
            $tracks,
            [self::class, 'compare_tracks']
        );

        return $tracks;
    }

    /**
     * Return only the IDs of tracks associated with a release.
     *
     * @param string[] $post_statuses
     *
     * @return int[]
     */
    public static function get_track_ids_for_release(
        int $release_id,
        array $post_statuses = ['publish']
    ): array {
        return array_map(
            static function (WP_Post $track): int {
                return (int) $track->ID;
            },
            self::get_tracks_for_release(
                $release_id,
                $post_statuses
            )
        );
    }

    /**
     * Store a track-to-release relationship in both supported fields.
     *
     * Passing zero clears the relationship. A positive release ID must belong
     * to a valid Slim Volume release.
     */
    public static function set_release_id(
        int $track_id,
        int $release_id
    ): bool {
        $track = get_post($track_id);

        if (
            ! $track instanceof WP_Post
            || PostTypes::TRACK !== $track->post_type
        ) {
            return false;
        }

        if (
            $release_id < 0
            || (
                $release_id > 0
                && ! self::is_valid_release($release_id)
            )
        ) {
            return false;
        }

        $previous_parent = (int) $track->post_parent;

        $previous_meta_exists = metadata_exists(
            'post',
            $track_id,
            self::META_KEY
        );

        $previous_meta_value = get_post_meta(
            $track_id,
            self::META_KEY,
            true
        );

        if ($release_id > 0) {
            update_post_meta(
                $track_id,
                self::META_KEY,
                $release_id
            );

            if (
                (int) get_post_meta(
                    $track_id,
                    self::META_KEY,
                    true
                ) !== $release_id
            ) {
                return false;
            }
        } else {
            delete_post_meta(
                $track_id,
                self::META_KEY
            );

            if (
                metadata_exists(
                    'post',
                    $track_id,
                    self::META_KEY
                )
            ) {
                return false;
            }
        }

        if ($previous_parent === $release_id) {
            return true;
        }

        $result = wp_update_post(
            [
                'ID'          => $track_id,
                'post_parent' => $release_id,
            ],
            true
        );

        if (! is_wp_error($result)) {
            return true;
        }

        /*
         * Restore the canonical meta value when WordPress cannot update the
         * compatibility post_parent field.
         */
        if ($previous_meta_exists) {
            update_post_meta(
                $track_id,
                self::META_KEY,
                $previous_meta_value
            );
        } else {
            delete_post_meta(
                $track_id,
                self::META_KEY
            );
        }

        return false;
    }

    public static function is_valid_release(int $release_id): bool
    {
        if ($release_id <= 0) {
            return false;
        }

        return PostTypes::RELEASE === get_post_type($release_id);
    }

    private static function compare_tracks(
        WP_Post $first,
        WP_Post $second
    ): int {
        $first_number = (int) get_post_meta(
            (int) $first->ID,
            '_sv_track_number',
            true
        );

        $second_number = (int) get_post_meta(
            (int) $second->ID,
            '_sv_track_number',
            true
        );

        $first_order = $first_number > 0
            ? $first_number
            : (
                (int) $first->menu_order > 0
                    ? (int) $first->menu_order
                    : PHP_INT_MAX
            );

        $second_order = $second_number > 0
            ? $second_number
            : (
                (int) $second->menu_order > 0
                    ? (int) $second->menu_order
                    : PHP_INT_MAX
            );

        if ($first_order !== $second_order) {
            return $first_order <=> $second_order;
        }

        $title_comparison = strcasecmp(
            get_the_title((int) $first->ID),
            get_the_title((int) $second->ID)
        );

        if (0 !== $title_comparison) {
            return $title_comparison;
        }

        return (int) $first->ID <=> (int) $second->ID;
    }
}
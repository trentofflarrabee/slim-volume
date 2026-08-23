<?php

declare(strict_types=1);

namespace SlimVolume\Frontend;

use SlimVolume\Artists\ProjectTaxonomy;
use SlimVolume\PostTypes;
use SlimVolume\Relationships\TrackReleaseRelationship;
use WP_Post;
use WP_Query;
use WP_Term;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Canonical query state for the public Slim Volume release archive.
 *
 * The archive template and SEO layer must use the same query so rendered
 * music entities always describe the releases actually shown to visitors.
 */
final class ArchiveQuery
{
    private static ?array $state = null;

    private static ?WP_Query $query = null;

    public static function state(array $settings): array
    {
        if (self::$state !== null) {
            return self::$state;
        }

        $search_query = isset($_GET['sv_release_q'])
            ? sanitize_text_field(
                wp_unslash($_GET['sv_release_q'])
            )
            : '';

        $projects_enabled = ! empty(
            $settings['projects_enabled']
        );

        $show_project_filter = (
            $projects_enabled
            && ! empty($settings['projects_archive_filter'])
        );

        $selected_project_id = (
            $show_project_filter
            && isset($_GET['sv_project'])
        )
            ? absint($_GET['sv_project'])
            : 0;

        $selected_project = null;

        if ($selected_project_id > 0) {
            $term = get_term(
                $selected_project_id,
                ProjectTaxonomy::TAXONOMY
            );

            if ($term instanceof WP_Term) {
                $selected_project = $term;
            } else {
                $selected_project_id = 0;
            }
        }

        $sort = isset($_GET['sv_release_sort'])
            ? sanitize_key(
                wp_unslash($_GET['sv_release_sort'])
            )
            : 'newest';

        $allowed_sorts = [
            'newest',
            'oldest',
            'title_asc',
            'title_desc',
        ];

        if (! in_array($sort, $allowed_sorts, true)) {
            $sort = 'newest';
        }

        $paged = max(
            1,
            (int) get_query_var('paged'),
            (int) get_query_var('page')
        );

        self::$state = [
            'search_query'        => $search_query,
            'show_project_filter' => $show_project_filter,
            'selected_project_id' => $selected_project_id,
            'selected_project'    => $selected_project,
            'sort'                => $sort,
            'paged'               => $paged,
        ];

        return self::$state;
    }

    public static function query(array $settings): WP_Query
    {
        if (self::$query instanceof WP_Query) {
            return self::$query;
        }

        $state = self::state($settings);

        $query_args = [
            'post_type'      => PostTypes::RELEASE,
            'post_status'    => 'publish',
            'posts_per_page' => (int) get_option(
                'posts_per_page'
            ),
            'paged'          => (int) $state['paged'],
        ];

        $selected_project_id = (int) (
            $state['selected_project_id'] ?? 0
        );

        if ($selected_project_id > 0) {
            $query_args['tax_query'] = [
                [
                    'taxonomy' => ProjectTaxonomy::TAXONOMY,
                    'field'    => 'term_id',
                    'terms'    => [$selected_project_id],
                ],
            ];
        }

        $search_query = (string) (
            $state['search_query'] ?? ''
        );

        if ($search_query !== '') {
            $matching_release_ids =
                self::matching_release_ids(
                    $search_query
                );

            $query_args['post__in'] =
                $matching_release_ids ?: [0];
        }

        switch ((string) ($state['sort'] ?? 'newest')) {
            case 'oldest':
                $query_args['meta_key'] = '_sv_release_date';
                $query_args['orderby']  = [
                    'meta_value' => 'ASC',
                    'title'      => 'ASC',
                ];
                break;

            case 'title_asc':
                $query_args['orderby'] = 'title';
                $query_args['order']   = 'ASC';
                break;

            case 'title_desc':
                $query_args['orderby'] = 'title';
                $query_args['order']   = 'DESC';
                break;

            case 'newest':
            default:
                $query_args['meta_key'] = '_sv_release_date';
                $query_args['orderby']  = [
                    'meta_value' => 'DESC',
                    'title'      => 'ASC',
                ];
                break;
        }

        self::$query = new WP_Query($query_args);

        return self::$query;
    }

    /**
     * Releases rendered on the current archive result page.
     *
     * @return WP_Post[]
     */
    public static function posts(array $settings): array
    {
        $query = self::query($settings);

        return array_values(
            array_filter(
                $query->posts,
                static function ($post): bool {
                    return (
                        $post instanceof WP_Post
                        && PostTypes::RELEASE
                            === $post->post_type
                        && 'publish'
                            === $post->post_status
                    );
                }
            )
        );
    }

    /**
     * Find releases matching release content, track content, or lyrics.
     *
     * @return int[]
     */
    private static function matching_release_ids(
        string $search_query
    ): array {
        if (trim($search_query) === '') {
            return [];
        }

        $release_ids = get_posts(
            [
                'post_type'      => PostTypes::RELEASE,
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
                's'              => $search_query,
            ]
        );

        $track_title_or_content_ids = get_posts(
            [
                'post_type'      => PostTypes::TRACK,
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
                's'              => $search_query,
            ]
        );

        $track_lyrics_ids = get_posts(
            [
                'post_type'      => PostTypes::TRACK,
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
                'meta_query'     => [
                    [
                        'key'     => '_sv_lyrics',
                        'value'   => $search_query,
                        'compare' => 'LIKE',
                    ],
                ],
            ]
        );

        $track_ids = array_values(
            array_unique(
                array_map(
                    'absint',
                    array_merge(
                        $track_title_or_content_ids,
                        $track_lyrics_ids
                    )
                )
            )
        );

        foreach ($track_ids as $track_id) {
            $release_id =
                TrackReleaseRelationship::get_release_id(
                    $track_id
                );

            if ($release_id <= 0) {
                continue;
            }

            $release = get_post($release_id);

            if (
                $release instanceof WP_Post
                && PostTypes::RELEASE
                    === $release->post_type
                && 'publish'
                    === $release->post_status
            ) {
                $release_ids[] = $release_id;
            }
        }

        return array_values(
            array_unique(
                array_filter(
                    array_map(
                        'absint',
                        $release_ids
                    )
                )
            )
        );
    }
}
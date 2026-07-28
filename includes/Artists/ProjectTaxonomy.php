<?php

declare(strict_types=1);

namespace SlimVolume\Artists;

use SlimVolume\PostTypes;
use WP_Error;
use WP_Post;
use WP_Term;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Registers and manages optional artist/project assignments for releases.
 *
 * The taxonomy is intentionally additive. Existing releases may remain
 * unassigned and continue using Slim Volume's global/default artist identity.
 *
 * Phase one exposes the term-management screen under Music but suppresses the
 * standard taxonomy metabox. A later admin phase will add a controlled,
 * single-select release field that calls assign_to_release().
 */
final class ProjectTaxonomy
{
    public const TAXONOMY = 'sv_project';

    public const META_ENTITY_TYPE = '_sv_project_entity_type';
    public const META_URL         = '_sv_project_url';
    public const META_IMAGE_ID    = '_sv_project_image_id';

    public const ENTITY_GROUP  = 'group';
    public const ENTITY_PERSON = 'person';

    public static function register(): void
    {
        register_taxonomy(
            self::TAXONOMY,
            [PostTypes::RELEASE],
            [
                'labels' => [
                    'name'                       => __('Artists & Projects', 'slim-volume'),
                    'singular_name'              => __('Artist / Project', 'slim-volume'),
                    'menu_name'                  => __('Artists & Projects', 'slim-volume'),
                    'search_items'               => __('Search artists and projects', 'slim-volume'),
                    'popular_items'              => __('Popular artists and projects', 'slim-volume'),
                    'all_items'                  => __('All artists and projects', 'slim-volume'),
                    'edit_item'                  => __('Edit artist or project', 'slim-volume'),
                    'view_item'                  => __('View artist or project', 'slim-volume'),
                    'update_item'                => __('Update artist or project', 'slim-volume'),
                    'add_new_item'               => __('Add new artist or project', 'slim-volume'),
                    'new_item_name'              => __('New artist or project name', 'slim-volume'),
                    'separate_items_with_commas' => __('Separate artists and projects with commas', 'slim-volume'),
                    'add_or_remove_items'         => __('Add or remove artists and projects', 'slim-volume'),
                    'choose_from_most_used'       => __('Choose from the most used artists and projects', 'slim-volume'),
                    'not_found'                  => __('No artists or projects found.', 'slim-volume'),
                    'back_to_items'              => __('Back to artists and projects', 'slim-volume'),
                ],
                'public'             => false,
                'publicly_queryable' => false,
                'hierarchical'       => false,
                'show_ui'            => true,
                'show_in_menu'       => true,
                'show_in_nav_menus'  => false,
                'show_tagcloud'      => false,
                'show_admin_column'  => false,
                'show_in_quick_edit' => false,
                'show_in_rest'       => false,
                'rewrite'            => false,
                'query_var'          => false,

                // One primary project per release will be assigned through a
                // dedicated Slim Volume control in the next phase.
                'meta_box_cb'        => false,
            ]
        );

        self::register_term_meta();
    }

    public static function register_term_meta(): void
    {
        register_term_meta(
            self::TAXONOMY,
            self::META_ENTITY_TYPE,
            [
                'single'            => true,
                'type'              => 'string',
                'default'           => self::ENTITY_GROUP,
                'show_in_rest'      => false,
                'sanitize_callback' => [self::class, 'sanitize_entity_type'],
                'auth_callback'     => [self::class, 'can_manage_term_meta'],
            ]
        );

        register_term_meta(
            self::TAXONOMY,
            self::META_URL,
            [
                'single'            => true,
                'type'              => 'string',
                'default'           => '',
                'show_in_rest'      => false,
                'sanitize_callback' => 'esc_url_raw',
                'auth_callback'     => [self::class, 'can_manage_term_meta'],
            ]
        );

        register_term_meta(
            self::TAXONOMY,
            self::META_IMAGE_ID,
            [
                'single'            => true,
                'type'              => 'integer',
                'default'           => 0,
                'show_in_rest'      => false,
                'sanitize_callback' => 'absint',
                'auth_callback'     => [self::class, 'can_manage_term_meta'],
            ]
        );
    }

    public static function sanitize_entity_type($value): string
    {
        $value = sanitize_key((string) $value);

        return in_array($value, [self::ENTITY_GROUP, self::ENTITY_PERSON], true)
            ? $value
            : self::ENTITY_GROUP;
    }

    public static function can_manage_term_meta(): bool
    {
        $taxonomy = get_taxonomy(self::TAXONOMY);

        if (! $taxonomy) {
            return current_user_can('manage_categories');
        }

        return current_user_can($taxonomy->cap->manage_terms);
    }

    /**
     * Return the single primary project currently assigned to a release.
     *
     * If outside code has assigned multiple terms, the lowest term ID wins so
     * resolution remains deterministic. The controlled admin selector added in
     * phase two will enforce a single assignment.
     */
    public static function get_release_project_term(int $release_id): ?WP_Term
    {
        if ($release_id <= 0) {
            return null;
        }

        $release = get_post($release_id);

        if (! $release instanceof WP_Post || $release->post_type !== PostTypes::RELEASE) {
            return null;
        }

        $terms = wp_get_object_terms(
            $release_id,
            self::TAXONOMY,
            [
                'orderby' => 'term_id',
                'order'   => 'ASC',
            ]
        );

        if (is_wp_error($terms) || ! $terms) {
            return null;
        }

        $term = reset($terms);

        return $term instanceof WP_Term ? $term : null;
    }

    /**
     * Enforce one primary project on a release.
     *
     * Passing 0 clears the assignment and restores default-artist fallback.
     *
     * @return array<int,int>|WP_Error Term taxonomy IDs on success.
     */
    public static function assign_to_release(int $release_id, int $term_id)
    {
        $release = get_post($release_id);

        if (! $release instanceof WP_Post || $release->post_type !== PostTypes::RELEASE) {
            return new WP_Error(
                'slim_volume_invalid_release',
                __('A valid Slim Volume release is required.', 'slim-volume')
            );
        }

        if ($term_id <= 0) {
            return wp_set_object_terms($release_id, [], self::TAXONOMY, false);
        }

        $term = get_term($term_id, self::TAXONOMY);

        if (! $term instanceof WP_Term) {
            return new WP_Error(
                'slim_volume_invalid_project',
                __('A valid artist or project is required.', 'slim-volume')
            );
        }

        return wp_set_object_terms($release_id, [$term_id], self::TAXONOMY, false);
    }
}

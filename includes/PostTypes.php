<?php

declare(strict_types=1);

namespace SlimVolume;

if (! defined('ABSPATH')) {
    exit;
}

final class PostTypes
{
    public const RELEASE = 'sv_release';
    public const TRACK   = 'sv_track';

    public static function register(): void
    {
        self::register_releases();
        self::register_tracks();
    }

    private static function register_releases(): void
    {
        register_post_type(
            self::RELEASE,
            [
                'labels' => [
                    'name'                  => __('Releases', 'slim-volume'),
                    'singular_name'         => __('Release', 'slim-volume'),
                    'menu_name'             => __('Music', 'slim-volume'),
                    'name_admin_bar'        => __('Release', 'slim-volume'),
                    'add_new'               => __('Add New', 'slim-volume'),
                    'add_new_item'          => __('Add New Release', 'slim-volume'),
                    'edit_item'             => __('Edit Release', 'slim-volume'),
                    'new_item'              => __('New Release', 'slim-volume'),
                    'view_item'             => __('View Release', 'slim-volume'),
                    'search_items'          => __('Search Releases', 'slim-volume'),
                    'not_found'             => __('No releases found.', 'slim-volume'),
                    'not_found_in_trash'    => __('No releases found in Trash.', 'slim-volume'),
                    'all_items'             => __('Releases', 'slim-volume'),
                ],
                'public'              => true,
                'publicly_queryable'  => true,
                'show_ui'             => true,
                'show_in_menu'        => true,
                'show_in_rest'        => true,
                'menu_position'       => 25,
                'menu_icon'           => 'dashicons-format-audio',
                'has_archive'         => 'music',
                'rewrite'             => [
                    'slug'       => 'music',
                    'with_front' => false,
                ],
                'query_var'           => true,
                'supports'            => [
                    'title',
                    'editor',
                    'excerpt',
                    'thumbnail',
                    'revisions',
                    'custom-fields',
                ],
            ]
        );
    }

    private static function register_tracks(): void
    {
        register_post_type(
            self::TRACK,
            [
                'labels' => [
                    'name'               => __('Tracks', 'slim-volume'),
                    'singular_name'      => __('Track', 'slim-volume'),
                    'add_new'            => __('Add New', 'slim-volume'),
                    'add_new_item'       => __('Add New Track', 'slim-volume'),
                    'edit_item'          => __('Edit Track', 'slim-volume'),
                    'new_item'           => __('New Track', 'slim-volume'),
                    'view_item'          => __('View Track', 'slim-volume'),
                    'search_items'       => __('Search Tracks', 'slim-volume'),
                    'not_found'          => __('No tracks found.', 'slim-volume'),
                    'not_found_in_trash' => __('No tracks found in Trash.', 'slim-volume'),
                    'all_items'          => __('Tracks', 'slim-volume'),
                ],
                'public'              => true,
                'publicly_queryable'  => true,
                'show_ui'             => true,
                'show_in_menu'        => 'edit.php?post_type=' . self::RELEASE,
                'show_in_rest'        => true,

                /*
                 * Important:
                 * hierarchical=true allows duplicate track slugs across different
                 * release parents. This supports:
                 * /music/album-one/intro
                 * /music/album-two/intro
                 */
                'hierarchical'        => true,

                /*
                 * We handle track permalinks ourselves:
                 * /music/{release-slug}/{track-slug}
                 */
                'rewrite'             => false,
                'query_var'           => false,

                'supports'            => [
                    'title',
                    'editor',
                    'excerpt',
                    'thumbnail',
                    'revisions',
                    'custom-fields',
                    'page-attributes',
                ],
            ]
        );
    }
}
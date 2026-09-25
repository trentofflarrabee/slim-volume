<?php

declare(strict_types=1);

namespace SlimVolume\Frontend;

use SlimVolume\Catalog;
use SlimVolume\PostTypes;
use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Normalizes WordPress nav-menu state on Slim Volume catalog routes.
 */
final class NavMenu
{
    /**
     * @param array<int, WP_Post> $items
     * @return array<int, WP_Post>
     */
    public static function filter_items(array $items): array
    {
        if (! self::is_catalog_request()) {
            return $items;
        }

        $archive_url  = Catalog::get_archive_url();
        $archive_path = self::normalize_path($archive_url);
        $posts_page   = (int) get_option('page_for_posts', 0);
        $is_archive   = is_post_type_archive(PostTypes::RELEASE);

        foreach ($items as $item) {
            if (! $item instanceof WP_Post) {
                continue;
            }

            /*
             * WordPress can mark the configured Posts page as
             * current_page_parent on unrelated custom post type routes.
             * That produces a false "Blog is active" state while browsing
             * Slim Volume catalog content.
             */
            if (
                $posts_page > 0
                && isset($item->object_id)
                && (int) $item->object_id === $posts_page
            ) {
                self::remove_classes(
                    $item,
                    [
                        'current_page_parent',
                        'current-menu-parent',
                        'current-menu-ancestor',
                    ]
                );

                $item->current = false;
            }

            if (
                ! isset($item->url)
                || self::normalize_path((string) $item->url) !== $archive_path
            ) {
                continue;
            }

            /*
             * The catalog archive itself is the exact current destination.
             */
            if ($is_archive) {
                self::add_classes(
                    $item,
                    [
                        'current-menu-item',
                        'current_page_item',
                    ]
                );

                $item->current = true;
                continue;
            }

            /*
             * Release and track screens live beneath the catalog archive.
             * Treat the matching archive item as the active section without
             * claiming that the archive URL itself is the current page.
             */
            self::add_classes(
                $item,
                [
                    'current-menu-parent',
                    'current-menu-ancestor',
                ]
            );

            $item->current = false;
        }

        return $items;
    }

    private static function is_catalog_request(): bool
    {
        return is_post_type_archive(PostTypes::RELEASE)
            || is_singular(PostTypes::RELEASE)
            || is_singular(PostTypes::TRACK);
    }

    private static function normalize_path(string $url): string
    {
        $path = wp_parse_url($url, PHP_URL_PATH);

        if (! is_string($path)) {
            return '';
        }

        $path = '/' . trim($path, '/');

        return $path === '/'
            ? '/'
            : trailingslashit($path);
    }

    /**
     * @param string[] $classes
     */
    private static function add_classes(WP_Post $item, array $classes): void
    {
        $current = isset($item->classes) && is_array($item->classes)
            ? $item->classes
            : [];

        $item->classes = array_values(
            array_unique(
                array_merge($current, $classes)
            )
        );
    }

    /**
     * @param string[] $classes
     */
    private static function remove_classes(WP_Post $item, array $classes): void
    {
        $current = isset($item->classes) && is_array($item->classes)
            ? $item->classes
            : [];

        $item->classes = array_values(
            array_diff($current, $classes)
        );
    }
}
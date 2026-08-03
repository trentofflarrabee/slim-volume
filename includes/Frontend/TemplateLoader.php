<?php

declare(strict_types=1);

namespace SlimVolume\Frontend;

use SlimVolume\PostTypes;

if (! defined('ABSPATH')) {
    exit;
}

final class TemplateLoader
{
    public static function template_include(string $template): string
    {
        if (is_post_type_archive(PostTypes::RELEASE)) {
            return self::locate_template('archive-sv_release.php') ?: $template;
        }

        if (is_singular(PostTypes::RELEASE)) {
            return self::locate_template('single-sv_release.php') ?: $template;
        }

        if (is_singular(PostTypes::TRACK)) {
            return self::locate_template('single-sv_track.php') ?: $template;
        }

        return $template;
    }

    public static function locate_template(string $template_name): string
    {
        $template_name = ltrim($template_name, '/');

        $theme_path = trailingslashit(get_stylesheet_directory()) . 'slim-volume/' . $template_name;

        if (file_exists($theme_path)) {
            return $theme_path;
        }

        $plugin_path = trailingslashit(SLIM_VOLUME_PATH) . 'templates/' . $template_name;

        if (file_exists($plugin_path)) {
            return $plugin_path;
        }

        return '';
    }

    public static function render(string $template_name, array $args = []): void
    {
        $template = self::locate_template($template_name);

        if (! $template) {
            return;
        }

        if ($args) {
            extract($args, EXTR_SKIP);
        }

        include $template;
    }
}

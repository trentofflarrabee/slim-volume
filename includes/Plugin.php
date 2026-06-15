<?php

declare(strict_types=1);

namespace SlimVolume;

if (! defined('ABSPATH')) {
    exit;
}

require_once SLIM_VOLUME_PATH . 'includes/PostTypes.php';
require_once SLIM_VOLUME_PATH . 'includes/Meta.php';
require_once SLIM_VOLUME_PATH . 'includes/Rewrite.php';
require_once SLIM_VOLUME_PATH . 'includes/Assets.php';
require_once SLIM_VOLUME_PATH . 'includes/Frontend/TemplateLoader.php';
require_once SLIM_VOLUME_PATH . 'includes/Frontend/PlayerData.php';
require_once SLIM_VOLUME_PATH . 'includes/Admin/ReleaseMetaBoxes.php';
require_once SLIM_VOLUME_PATH . 'includes/Admin/TrackMetaBoxes.php';

final class Plugin
{
    private static ?self $instance = null;

    public static function instance(): self
    {
        if (! self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function boot(): void
    {
        add_action('init', [PostTypes::class, 'register']);
        add_action('init', [Meta::class, 'register']);
        add_action('init', [Rewrite::class, 'register']);

        add_action('add_meta_boxes', [Admin\ReleaseMetaBoxes::class, 'register']);
        add_action('add_meta_boxes', [Admin\TrackMetaBoxes::class, 'register']);

        add_action('save_post_' . PostTypes::RELEASE, [Admin\ReleaseMetaBoxes::class, 'save']);
        add_action('save_post_' . PostTypes::TRACK, [Admin\TrackMetaBoxes::class, 'save']);

        add_filter('query_vars', [Rewrite::class, 'add_query_vars']);
        add_action('pre_get_posts', [Rewrite::class, 'resolve_nested_track_query']);
        add_filter('post_type_link', [Rewrite::class, 'filter_track_permalink'], 10, 2);

        add_filter('template_include', [Frontend\TemplateLoader::class, 'template_include']);

        add_action('wp_enqueue_scripts', [Assets::class, 'enqueue_frontend']);
        add_action('admin_enqueue_scripts', [Assets::class, 'enqueue_admin']);
    }

    public static function activate(): void
    {
        PostTypes::register();
        Meta::register();
        Rewrite::register();

        flush_rewrite_rules();
    }

    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }
}
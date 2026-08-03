<?php

declare(strict_types=1);

namespace SlimVolume;

if (! defined('ABSPATH')) {
    exit;
}

require_once SLIM_VOLUME_PATH . 'includes/PostTypes.php';
require_once SLIM_VOLUME_PATH . 'includes/TimedLyrics.php';
require_once SLIM_VOLUME_PATH . 'includes/Meta.php';
require_once SLIM_VOLUME_PATH . 'includes/Relationships/TrackReleaseRelationship.php';
require_once SLIM_VOLUME_PATH . 'includes/Rewrite.php';
require_once SLIM_VOLUME_PATH . 'includes/Assets.php';
require_once SLIM_VOLUME_PATH . 'includes/Frontend/TemplateLoader.php';
require_once SLIM_VOLUME_PATH . 'includes/Frontend/PlayerData.php';
require_once SLIM_VOLUME_PATH . 'includes/Frontend/Seo.php';
require_once SLIM_VOLUME_PATH . 'includes/Admin/ReleaseMetaBoxes.php';
require_once SLIM_VOLUME_PATH . 'includes/Admin/TrackMetaBoxes.php';
require_once SLIM_VOLUME_PATH . 'includes/Admin/AdminColumns.php';
require_once SLIM_VOLUME_PATH . 'includes/Admin/ReleaseTrackManager.php';
require_once SLIM_VOLUME_PATH . 'includes/Admin/Settings.php';
require_once SLIM_VOLUME_PATH . 'includes/Artists/ProjectTaxonomy.php';
require_once SLIM_VOLUME_PATH . 'includes/Artists/ArtistResolver.php';
require_once SLIM_VOLUME_PATH . 'includes/Admin/ReleaseProjectMetaBox.php';
require_once SLIM_VOLUME_PATH . 'includes/Admin/ReleaseDashboardMetaBox.php';
require_once SLIM_VOLUME_PATH . 'includes/Admin/TrackReleasePrefill.php';
require_once SLIM_VOLUME_PATH . 'includes/Admin/TrackContextMetaBox.php';
require_once SLIM_VOLUME_PATH . 'includes/Admin/TrackReleaseFilter.php';
require_once SLIM_VOLUME_PATH . 'includes/Admin/TimedLyricsAdmin.php';

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
        add_action('after_setup_theme', [PostTypes::class, 'add_theme_support']);

        add_action('init', [PostTypes::class, 'register']);
        add_action('init', [Artists\ProjectTaxonomy::class, 'register']);
        add_action('init', [Meta::class, 'register']);
        add_action('init', [Rewrite::class, 'register']);

        add_action('add_meta_boxes', [Admin\ReleaseMetaBoxes::class, 'register']);
        add_action('add_meta_boxes', [Admin\ReleaseProjectMetaBox::class, 'register']);
        add_action('add_meta_boxes', [Admin\TrackMetaBoxes::class, 'register']);
        add_action('add_meta_boxes', [Admin\ReleaseTrackManager::class, 'register']);
        add_action('add_meta_boxes', [Admin\ReleaseDashboardMetaBox::class, 'register']);
        add_action('add_meta_boxes', [Admin\TrackContextMetaBox::class, 'register']);
        add_action('add_meta_boxes', [Admin\TimedLyricsAdmin::class, 'register_meta_box']);

        add_action(
            'admin_post_sv_move_track',
            [Admin\TrackContextMetaBox::class, 'handle_reorder']
        );
        
        Admin\TrackReleasePrefill::register();
        Admin\TrackReleaseFilter::register();

        add_action('save_post_' . PostTypes::RELEASE, [Admin\ReleaseMetaBoxes::class, 'save']);
        add_action('save_post_' . PostTypes::RELEASE, [Admin\ReleaseProjectMetaBox::class, 'save'], 20);
        add_action('save_post_' . PostTypes::RELEASE, [Admin\ReleaseTrackManager::class, 'save_order']);
        add_action('save_post_' . PostTypes::TRACK, [Admin\TrackMetaBoxes::class, 'save']);
        add_action('save_post_' . PostTypes::TRACK, [TimedLyrics::class, 'reconcile'], 20);

        add_action('admin_init', [Admin\AdminColumns::class, 'register']);
        add_action('admin_menu', [Admin\TimedLyricsAdmin::class, 'register_page']);
        add_action(
            'wp_ajax_' . Admin\TimedLyricsAdmin::AJAX_ACTION,
            [Admin\TimedLyricsAdmin::class, 'ajax_save']
        );
        
        Admin\Settings::init();

        add_filter('query_vars', [Rewrite::class, 'add_query_vars']);
        add_action('pre_get_posts', [Rewrite::class, 'resolve_nested_track_query']);
        add_filter('post_type_link', [Rewrite::class, 'filter_track_permalink'], 10, 2);

        add_filter('template_include', [Frontend\TemplateLoader::class, 'template_include']);
        add_action('wp_head', [Frontend\Seo::class, 'render'], 2);

        add_action('wp_enqueue_scripts', [Assets::class, 'enqueue_frontend']);
        add_action('admin_enqueue_scripts', [Assets::class, 'enqueue_admin']);
    }

    public static function activate(): void
    {
        PostTypes::register();
        Artists\ProjectTaxonomy::register();
        Meta::register();
        Rewrite::register();

        flush_rewrite_rules();
    }

    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }
}
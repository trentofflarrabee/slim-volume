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
require_once SLIM_VOLUME_PATH . 'includes/Frontend/ArchiveQuery.php';
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
    private const VERSION_OPTION = 'slim_volume_version';

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
        self::maybe_upgrade();

        add_action(
            'init',
            static function (): void {
                load_plugin_textdomain(
                    'slim-volume',
                    false,
                    dirname(plugin_basename(SLIM_VOLUME_FILE)) . '/languages'
                );
            },
            0
        );

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

        add_action(
            'admin_post_sv_repair_track_relationship',
            [Admin\TrackContextMetaBox::class, 'handle_repair']
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

        add_filter(
            'plugin_action_links_' . plugin_basename(SLIM_VOLUME_FILE),
            static function (array $links): array {
                if (! current_user_can('manage_options')) {
                    return $links;
                }

                $settings_url = add_query_arg(
                    [
                        'post_type' => PostTypes::RELEASE,
                        'page'      => Admin\Settings::MENU_SLUG,
                    ],
                    admin_url('edit.php')
                );

                $settings_link = sprintf(
                    '<a href="%1$s">%2$s</a>',
                    esc_url($settings_url),
                    esc_html__('Settings', 'slim-volume')
                );

                return ['settings' => $settings_link] + $links;
            }
        );

        add_filter('query_vars', [Rewrite::class, 'add_query_vars']);
        add_action('pre_get_posts', [Rewrite::class, 'resolve_nested_track_query']); 
        add_filter('post_type_link', [Rewrite::class, 'filter_track_permalink'], 10, 2);

add_filter('template_include', [Frontend\TemplateLoader::class, 'template_include']);

add_filter(
    'document_title_parts',
    [Frontend\Seo::class, 'filter_document_title']
);

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

        self::maybe_upgrade();

        flush_rewrite_rules();
    }

    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }

    private static function maybe_upgrade(): void
    {
        /*
         * Setting migrations are intentionally idempotent and inspect raw
         * stored options before default values are merged by Settings.
         */
        self::migrate_seo_mode_setting();

        $installed_version = (string) get_option(self::VERSION_OPTION, '');

        if ($installed_version === SLIM_VOLUME_VERSION) {
            return;
        }

        /*
         * Future version-specific data migrations should run here before the
         * stored version is advanced.
         */
        update_option(self::VERSION_OPTION, SLIM_VOLUME_VERSION, true);
    }

    private static function migrate_seo_mode_setting(): void
    {
        $raw_settings = get_option(Admin\Settings::OPTION_NAME, []);

        if (! is_array($raw_settings)) {
            return;
        }

        /*
         * If seo_mode already exists, normalize it and leave it authoritative.
         */
if (array_key_exists('seo_mode', $raw_settings)) {
    $mode = Admin\Settings::normalize_seo_mode(
        $raw_settings['seo_mode']
    );

    if (($raw_settings['seo_mode'] ?? '') !== $mode) {
        $raw_settings['seo_mode'] = $mode;
        update_option(Admin\Settings::OPTION_NAME, $raw_settings);
    }

    return;
}

        /*
         * Existing installations migrate according to the raw legacy setting.
         * Do not use Settings::get_settings() here because its defaults would
         * make "never stored" indistinguishable from "stored as disabled".
         */
        if (! array_key_exists('seo_enabled', $raw_settings)) {
            return;
        }

        $raw_settings['seo_mode'] = ! empty($raw_settings['seo_enabled'])
            ? 'full'
            : 'off';

        update_option(Admin\Settings::OPTION_NAME, $raw_settings);
    }
}
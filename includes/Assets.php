<?php

declare(strict_types=1);

namespace SlimVolume;

if (! defined('ABSPATH')) {
    exit;
}

final class Assets
{
    public static function enqueue_frontend(): void
    {
        if (
            ! is_post_type_archive(PostTypes::RELEASE)
            && ! is_singular(PostTypes::RELEASE)
            && ! is_singular(PostTypes::TRACK)
        ) {
            return;
        }

        $css_path = SLIM_VOLUME_PATH . 'assets/css/slim-volume.css';
        $js_path  = SLIM_VOLUME_PATH . 'assets/js/slim-volume-player.js';

        if (file_exists($css_path)) {
            wp_enqueue_style(
                'slim-volume',
                SLIM_VOLUME_URL . 'assets/css/slim-volume.css',
                [],
                self::asset_version($css_path)
            );

            $appearance_css = Admin\Settings::get_appearance_css();

            if ($appearance_css !== '') {
                wp_add_inline_style('slim-volume', $appearance_css);
            }
        }

        $artist_projects_css_path = SLIM_VOLUME_PATH . 'assets/css/artist-projects.css';

        if (file_exists($artist_projects_css_path)) {
            wp_enqueue_style(
                'slim-volume-artist-projects',
                SLIM_VOLUME_URL . 'assets/css/artist-projects.css',
                ['slim-volume'],
                self::asset_version($artist_projects_css_path)
            );
        }

$settings = Admin\Settings::get_settings();

if (empty($settings['player_enabled'])) {
    return;
}

$visualizer_mode = isset($settings['visualizer_mode'])
    ? sanitize_key((string) $settings['visualizer_mode'])
    : 'bars';

$allowed_visualizer_modes = ['bars'];

if (Admin\Settings::is_butterchurn_available()) {
    $allowed_visualizer_modes[] = 'butterchurn';
}

if (! in_array($visualizer_mode, $allowed_visualizer_modes, true)) {
    $visualizer_mode = 'bars';
}

if (file_exists($js_path)) {
    $player_dependencies = [];

    if ($visualizer_mode === 'butterchurn' && Admin\Settings::is_butterchurn_available()) {
        $butterchurn_path         = SLIM_VOLUME_PATH . 'assets/vendor/butterchurn/butterchurn.min.js';
        $butterchurn_presets_path = SLIM_VOLUME_PATH . 'assets/vendor/butterchurn/butterchurn-presets.min.js';

        wp_enqueue_script(
            'slim-volume-butterchurn',
            SLIM_VOLUME_URL . 'assets/vendor/butterchurn/butterchurn.min.js',
            [],
            self::asset_version($butterchurn_path),
            true
        );

        wp_enqueue_script(
            'slim-volume-butterchurn-presets',
            SLIM_VOLUME_URL . 'assets/vendor/butterchurn/butterchurn-presets.min.js',
            ['slim-volume-butterchurn'],
            self::asset_version($butterchurn_presets_path),
            true
        );

        $butterchurn_adapter_path = SLIM_VOLUME_PATH . 'assets/js/slim-volume-butterchurn-adapter.js';

if (file_exists($butterchurn_adapter_path)) {
    wp_enqueue_script(
        'slim-volume-butterchurn-adapter',
        SLIM_VOLUME_URL . 'assets/js/slim-volume-butterchurn-adapter.js',
        ['slim-volume-butterchurn-presets'],
        self::asset_version($butterchurn_adapter_path),
        true
    );

    $player_dependencies[] = 'slim-volume-butterchurn-adapter';
} else {
    $player_dependencies[] = 'slim-volume-butterchurn-presets';
}

    }

    wp_enqueue_script(
        'slim-volume-player',
        SLIM_VOLUME_URL . 'assets/js/slim-volume-player.js',
        $player_dependencies,
        self::asset_version($js_path),
        true
    );

    wp_add_inline_script(
        'slim-volume-player',
        'window.SVConfig = ' . wp_json_encode(
            [
                'version'          => defined('SLIM_VOLUME_VERSION') ? SLIM_VOLUME_VERSION : '0.1.0',
                'ajaxNavigation'   => ! empty($settings['ajax_navigation']),
                'persistence'      => ! empty($settings['persistence']),
                'visualizer'       => ! empty($settings['visualizer']),
                'visualizerMode'   => $visualizer_mode,
                'debug'            => ! empty($settings['debug']) || (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG),
                'contentSelector'  => '[data-sv-page-content]',
                'musicBaseUrl'     => home_url('/music/'),
            ]
        ) . ';',
        'before'
    );
}

        $navigation_js_path = SLIM_VOLUME_PATH . 'assets/js/slim-volume-navigation.js';

        if (file_exists($navigation_js_path)) {
            wp_enqueue_script(
                'slim-volume-navigation',
                SLIM_VOLUME_URL . 'assets/js/slim-volume-navigation.js',
                ['slim-volume-player'],
                self::asset_version($navigation_js_path),
                true
            );


        }
    }

    public static function enqueue_admin(string $hook): void
    {
        $screen = get_current_screen();

        if (! $screen) {
            return;
        }

        $is_project_taxonomy_screen = (
            (isset($screen->taxonomy) && $screen->taxonomy === Artists\ProjectTaxonomy::TAXONOMY)
            || $screen->id === 'edit-' . Artists\ProjectTaxonomy::TAXONOMY
        );

        $is_timed_lyrics_screen = (
            $screen->id === PostTypes::RELEASE . '_page_slim-volume-lyrics-sync'
            || $hook === PostTypes::RELEASE . '_page_slim-volume-lyrics-sync'
        );

        $is_music_post_screen = (
            in_array($hook, ['post.php', 'post-new.php', 'edit.php'], true)
            && in_array($screen->post_type, [PostTypes::RELEASE, PostTypes::TRACK], true)
        );

        if (! $is_project_taxonomy_screen && ! $is_timed_lyrics_screen && ! $is_music_post_screen) {
            return;
        }

        $css_path = SLIM_VOLUME_PATH . 'assets/css/admin.css';

        if (file_exists($css_path)) {
            wp_enqueue_style(
                'slim-volume-admin',
                SLIM_VOLUME_URL . 'assets/css/admin.css',
                [],
                self::asset_version($css_path)
            );
        }

        if ($is_project_taxonomy_screen) {
            wp_enqueue_media();

            $project_media_path = SLIM_VOLUME_PATH . 'assets/js/admin-project-media.js';

            if (file_exists($project_media_path)) {
                wp_enqueue_script(
                    'slim-volume-admin-project-media',
                    SLIM_VOLUME_URL . 'assets/js/admin-project-media.js',
                    [],
                    self::asset_version($project_media_path),
                    true
                );
            }

            return;
        }

        if ($is_timed_lyrics_screen) {
            $sync_js_path = SLIM_VOLUME_PATH . 'assets/js/admin-timed-lyrics.js';
            $track_id     = isset($_GET['track_id'])
                ? absint($_GET['track_id'])
                : 0;

            if (
                $track_id > 0
                && get_post_type($track_id) === PostTypes::TRACK
                && current_user_can('edit_post', $track_id)
                && file_exists($sync_js_path)
            ) {
                wp_enqueue_script(
                    'slim-volume-admin-timed-lyrics',
                    SLIM_VOLUME_URL . 'assets/js/admin-timed-lyrics.js',
                    [],
                    self::asset_version($sync_js_path),
                    true
                );

                $document = TimedLyrics::get_authoring_document($track_id);

                wp_add_inline_script(
                    'slim-volume-admin-timed-lyrics',
                    'window.SVTimedLyricsAdmin = ' . wp_json_encode(
                        [
                            'ajaxUrl'  => admin_url('admin-ajax.php'),
                            'action'   => Admin\TimedLyricsAdmin::AJAX_ACTION,
                            'nonce'    => wp_create_nonce(
                                Admin\TimedLyricsAdmin::NONCE_ACTION . ':' . $track_id
                            ),
                            'trackId'  => $track_id,
                            'document' => $document,
                            'strings'  => [
                                'ready'              => __('Ready. Start Sync when you are prepared to tap each line.', 'slim-volume'),
                                'armed'              => __('Sync armed. Press Space slightly before each lyric should activate.', 'slim-volume'),
                                'reviewing'          => __('Review mode. Playback follows the saved timestamps.', 'slim-volume'),
                                'finished'           => __('Timing pass reached the final lyric. Review or save your work.', 'slim-volume'),
                                'dirty'              => __('Unsaved timing changes.', 'slim-volume'),
                                'saving'             => __('Saving timed lyrics…', 'slim-volume'),
                                'savedDraft'          => __('Timed lyrics draft saved.', 'slim-volume'),
                                'savedComplete'       => __('Timed lyrics are complete and eligible for public display.', 'slim-volume'),
                                'saveFailed'          => __('Timed lyrics could not be saved.', 'slim-volume'),
                                'noTimestamp'         => __('Select a timed lyric line before adjusting it.', 'slim-volume'),
                                'orderConflict'       => __('That timestamp would overlap the previous or next lyric line.', 'slim-volume'),
                                'confirmReset'        => __('Clear every lyric timestamp in this workspace?', 'slim-volume'),
                                'unsavedWarning'      => __('You have unsaved timed-lyrics changes.', 'slim-volume'),
                                'allLinesRequired'    => __('Every lyric line needs a timestamp before it can be marked complete.', 'slim-volume'),
                                'audioUnavailable'    => __('The audio source is unavailable.', 'slim-volume'),
                                'startSync'           => __('Start Sync', 'slim-volume'),
                                'resumeSync'          => __('Resume Sync', 'slim-volume'),
                                'stopSync'            => __('Stop Sync', 'slim-volume'),
                                'review'              => __('Review', 'slim-volume'),
                                'stopReview'          => __('Stop Review', 'slim-volume'),
                            ],
                        ]
                    ) . ';',
                    'before'
                );
            }

            return;
        }

        if ($screen->post_type === PostTypes::RELEASE) {
            wp_enqueue_script('jquery-ui-sortable');

            $release_js_path = SLIM_VOLUME_PATH . 'assets/js/admin-release-tracks.js';

            if (file_exists($release_js_path)) {
                wp_enqueue_script(
                    'slim-volume-admin-release-tracks',
                    SLIM_VOLUME_URL . 'assets/js/admin-release-tracks.js',
                    ['jquery', 'jquery-ui-sortable'],
                    self::asset_version($release_js_path),
                    true
                );
            }

            return;
        }

        if ($screen->post_type !== PostTypes::TRACK) {
            return;
        }

        wp_enqueue_media();

        $js_path = SLIM_VOLUME_PATH . 'assets/js/admin-track-media.js';

        if (file_exists($js_path)) {
            wp_enqueue_script(
                'slim-volume-admin-track-media',
                SLIM_VOLUME_URL . 'assets/js/admin-track-media.js',
                ['jquery'],
                self::asset_version($js_path),
                true
            );
        }
    }

    private static function asset_version(string $path): string
    {
        if (file_exists($path)) {
            return (string) filemtime($path);
        }

        return defined('SLIM_VOLUME_VERSION')
            ? (string) SLIM_VOLUME_VERSION
            : '0.1.0';
    }
}
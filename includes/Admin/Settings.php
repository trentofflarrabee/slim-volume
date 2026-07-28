<?php

declare(strict_types=1);

namespace SlimVolume\Admin;

use SlimVolume\PostTypes;

if (! defined('ABSPATH')) {
    exit;
}

final class Settings
{
    public const OPTION_NAME  = 'slim_volume_settings';
    public const OPTION_GROUP = 'slim_volume_settings_group';
    public const MENU_SLUG    = 'slim-volume-settings';

    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'add_settings_page']);
        add_action('admin_init', [self::class, 'register_settings']);
    }

    public static function defaults(): array
    {
        return [
            'player_enabled'             => true,
            'release_card_link_behavior' => 'internal',

            'ajax_navigation' => true,
            'persistence'    => true,
            'visualizer'     => true,
            'visualizer_mode' => 'bars',
            'debug'          => false,

            'seo_enabled'             => false,
            'seo_artist_name'         => '',
            'seo_artist_url'          => '',
            'seo_archive_description' => '',
            'seo_default_image'       => '',

            'appearance_preset' => 'custom',

            'player_bg'      => '#111',
            'player_text'    => '#fff',
            'player_muted'   => 'rgba(255, 255, 255, 0.68)',
            'player_border'  => 'rgba(255, 255, 255, 0.14)',
            'player_accent'  => 'currentColor',

            'button_bg'      => '#111',
            'button_text'    => '#fff',
            'button_border'  => '#111',

            'card_border'    => 'rgba(0, 0, 0, 0.12)',

            'radius_card'    => '16px',
            'radius_art'     => '18px',
            'radius_control' => '14px',
            'radius_small'   => '8px',
            'radius_pill'    => '999px',
        ];
    }

    public static function appearance_presets(): array
{
    return [
        'custom' => [
            'label'  => __('Custom', 'slim-volume'),
            'values' => [],
        ],
        'dark' => [
            'label'  => __('Dark', 'slim-volume'),
            'values' => [
                'player_bg'      => '#111',
                'player_text'    => '#fff',
                'player_muted'   => 'rgba(255, 255, 255, 0.68)',
                'player_border'  => 'rgba(255, 255, 255, 0.14)',
                'player_accent'  => 'currentColor',
                'button_bg'      => '#111',
                'button_text'    => '#fff',
                'button_border'  => '#111',
                'card_border'    => 'rgba(0, 0, 0, 0.12)',
                'radius_card'    => '16px',
                'radius_art'     => '18px',
                'radius_control' => '14px',
                'radius_small'   => '8px',
                'radius_pill'    => '999px',
            ],
        ],
        'light' => [
            'label'  => __('Light', 'slim-volume'),
            'values' => [
                'player_bg'      => '#f8f3ea',
                'player_text'    => '#201914',
                'player_muted'   => 'rgba(32, 25, 20, 0.68)',
                'player_border'  => 'rgba(32, 25, 20, 0.18)',
                'player_accent'  => '#b14d2a',
                'button_bg'      => '#201914',
                'button_text'    => '#fff8f0',
                'button_border'  => '#201914',
                'card_border'    => 'rgba(32, 25, 20, 0.16)',
                'radius_card'    => '16px',
                'radius_art'     => '18px',
                'radius_control' => '14px',
                'radius_small'   => '8px',
                'radius_pill'    => '999px',
            ],
        ],
        'warm_vintage' => [
            'label'  => __('Warm Vintage', 'slim-volume'),
            'values' => [
                'player_bg'      => '#2b1d14',
                'player_text'    => '#fff3df',
                'player_muted'   => 'rgba(255, 243, 223, 0.68)',
                'player_border'  => 'rgba(255, 243, 223, 0.18)',
                'player_accent'  => '#d58a45',
                'button_bg'      => '#d58a45',
                'button_text'    => '#21140d',
                'button_border'  => '#d58a45',
                'card_border'    => 'rgba(43, 29, 20, 0.18)',
                'radius_card'    => '20px',
                'radius_art'     => '22px',
                'radius_control' => '14px',
                'radius_small'   => '8px',
                'radius_pill'    => '999px',
            ],
        ],
        'neon' => [
            'label'  => __('Neon', 'slim-volume'),
            'values' => [
                'player_bg'      => '#070714',
                'player_text'    => '#f8f7ff',
                'player_muted'   => 'rgba(248, 247, 255, 0.62)',
                'player_border'  => 'rgba(255, 79, 216, 0.28)',
                'player_accent'  => '#ff4fd8',
                'button_bg'      => '#ff4fd8',
                'button_text'    => '#070714',
                'button_border'  => '#ff4fd8',
                'card_border'    => 'rgba(255, 79, 216, 0.22)',
                'radius_card'    => '18px',
                'radius_art'     => '20px',
                'radius_control' => '14px',
                'radius_small'   => '8px',
                'radius_pill'    => '999px',
            ],
        ],
    ];
}

    public static function get_settings(): array
    {
        $saved = get_option(self::OPTION_NAME, []);

        if (! is_array($saved)) {
            $saved = [];
        }

        return array_merge(self::defaults(), $saved);
    }

    public static function get_appearance_css(): string
    {
        $settings = self::get_settings();
        $defaults = self::defaults();

        $map = [
            'player_bg'      => '--sv-player-bg',
            'player_text'    => '--sv-player-text',
            'player_muted'   => '--sv-player-muted',
            'player_border'  => '--sv-player-border',
            'player_accent'  => '--sv-player-accent',

            'button_bg'      => '--sv-button-bg',
            'button_text'    => '--sv-button-text',
            'button_border'  => '--sv-button-border',

            'card_border'    => '--sv-card-border',

            'radius_card'    => '--sv-radius-card',
            'radius_art'     => '--sv-radius-art',
            'radius_control' => '--sv-radius-control',
            'radius_small'   => '--sv-radius-small',
            'radius_pill'    => '--sv-radius-pill',
        ];

        $css = ":root {\n";

        foreach ($map as $setting_key => $css_variable) {
            $fallback = isset($defaults[$setting_key]) && is_string($defaults[$setting_key])
                ? $defaults[$setting_key]
                : '';

            $value = self::sanitize_css_value(
                $settings[$setting_key] ?? $fallback,
                $fallback
            );

            if ($value === '') {
                continue;
            }

            $css .= sprintf(
                "  %s: %s;\n",
                $css_variable,
                $value
            );
        }

        $css .= "}\n";

        return $css;
    }

    public static function register_settings(): void
    {
        register_setting(
            self::OPTION_GROUP,
            self::OPTION_NAME,
            [
                'type'              => 'array',
                'sanitize_callback' => [self::class, 'sanitize_settings'],
                'default'           => self::defaults(),
            ]
        );
    }

    public static function sanitize_settings(array $input): array
    {
        $defaults = self::defaults();

        $presets = self::appearance_presets();

        $preset_key = isset($input['appearance_preset'])
            ? sanitize_key((string) $input['appearance_preset'])
            : 'custom';

        if (! isset($presets[$preset_key])) {
            $preset_key = 'custom';
        }

$appearance = [
    'appearance_preset' => $preset_key,

    'player_bg'      => self::sanitize_css_value($input['player_bg'] ?? $defaults['player_bg'], $defaults['player_bg']),
    'player_text'    => self::sanitize_css_value($input['player_text'] ?? $defaults['player_text'], $defaults['player_text']),
    'player_muted'   => self::sanitize_css_value($input['player_muted'] ?? $defaults['player_muted'], $defaults['player_muted']),
    'player_border'  => self::sanitize_css_value($input['player_border'] ?? $defaults['player_border'], $defaults['player_border']),
    'player_accent'  => self::sanitize_css_value($input['player_accent'] ?? $defaults['player_accent'], $defaults['player_accent']),

    'button_bg'      => self::sanitize_css_value($input['button_bg'] ?? $defaults['button_bg'], $defaults['button_bg']),
    'button_text'    => self::sanitize_css_value($input['button_text'] ?? $defaults['button_text'], $defaults['button_text']),
    'button_border'  => self::sanitize_css_value($input['button_border'] ?? $defaults['button_border'], $defaults['button_border']),

    'card_border'    => self::sanitize_css_value($input['card_border'] ?? $defaults['card_border'], $defaults['card_border']),

    'radius_card'    => self::sanitize_css_value($input['radius_card'] ?? $defaults['radius_card'], $defaults['radius_card']),
    'radius_art'     => self::sanitize_css_value($input['radius_art'] ?? $defaults['radius_art'], $defaults['radius_art']),
    'radius_control' => self::sanitize_css_value($input['radius_control'] ?? $defaults['radius_control'], $defaults['radius_control']),
    'radius_small'   => self::sanitize_css_value($input['radius_small'] ?? $defaults['radius_small'], $defaults['radius_small']),
    'radius_pill'    => self::sanitize_css_value($input['radius_pill'] ?? $defaults['radius_pill'], $defaults['radius_pill']),
];

if ($preset_key !== 'custom' && ! empty($presets[$preset_key]['values']) && is_array($presets[$preset_key]['values'])) {
    foreach ($presets[$preset_key]['values'] as $key => $value) {
        if (array_key_exists($key, $appearance)) {
            $appearance[$key] = self::sanitize_css_value($value, $defaults[$key] ?? '');
        }
    }
}

$visualizer_modes = method_exists(self::class, 'visualizer_modes')
    ? self::visualizer_modes()
    : ['bars' => __('Bars', 'slim-volume')];

$visualizer_mode = isset($input['visualizer_mode'])
    ? sanitize_key((string) $input['visualizer_mode'])
    : (string) ($defaults['visualizer_mode'] ?? 'bars');

if (! isset($visualizer_modes[$visualizer_mode])) {
    $visualizer_mode = 'bars';
}

$release_card_link_behavior = isset($input['release_card_link_behavior'])
    ? sanitize_key((string) $input['release_card_link_behavior'])
    : (string) ($defaults['release_card_link_behavior'] ?? 'internal');

$allowed_release_card_link_behaviors = [
    'internal',
    'external_when_available',
];

if (! in_array($release_card_link_behavior, $allowed_release_card_link_behaviors, true)) {
    $release_card_link_behavior = 'internal';
}

$seo = [
    'seo_enabled'             => ! empty($input['seo_enabled']),
    'seo_artist_name'         => sanitize_text_field((string) ($input['seo_artist_name'] ?? '')),
    'seo_artist_url'          => esc_url_raw((string) ($input['seo_artist_url'] ?? '')),
    'seo_archive_description' => sanitize_textarea_field((string) ($input['seo_archive_description'] ?? '')),
    'seo_default_image'       => esc_url_raw((string) ($input['seo_default_image'] ?? '')),
];

return array_merge(
    [
        'player_enabled'             => ! empty($input['player_enabled']),
        'release_card_link_behavior' => $release_card_link_behavior,

        'ajax_navigation' => ! empty($input['ajax_navigation']),
        'persistence'    => ! empty($input['persistence']),
        'visualizer'      => ! empty($input['visualizer']),
        'visualizer_mode' => $visualizer_mode,
        'debug'           => ! empty($input['debug']),
    ],
    $seo,
    $appearance
);
    }

    private static function sanitize_css_value($value, string $fallback): string
    {
        if (! is_string($value)) {
            return $fallback;
        }

        $value = trim(wp_strip_all_tags($value));

        if ($value === '') {
            return $fallback;
        }

        /**
         * Keep CSS variable output safe.
         *
         * This intentionally blocks semicolons, braces, angle brackets, backslashes,
         * and quotes so values cannot break out of a custom property declaration.
         */
        if (preg_match('/[;{}<>"\'\\\\]/', $value)) {
            return $fallback;
        }

        if (strlen($value) > 120) {
            return $fallback;
        }

        return $value;
    }

    public static function add_settings_page(): void
    {
        add_submenu_page(
            'edit.php?post_type=' . PostTypes::RELEASE,
            __('Slim Volume Settings', 'slim-volume'),
            __('Settings', 'slim-volume'),
            'manage_options',
            self::MENU_SLUG,
            [self::class, 'render_page']
        );
    }

    public static function render_page(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'slim-volume'));
        }

        $settings      = self::get_settings();
        $preview_style = self::get_preview_style($settings);

        ?>
        <div class="wrap sv-settings">
            <?php self::render_preview_css(); ?>

            <h1><?php echo esc_html__('Slim Volume Settings', 'slim-volume'); ?></h1>

            <p class="sv-settings__intro">
                <?php echo esc_html__('Configure the music catalog, frontend player, music SEO, and visual appearance.', 'slim-volume'); ?>
            </p>

            <?php settings_errors(); ?>

            <form method="post" action="options.php" data-sv-settings-form>
                <?php settings_fields(self::OPTION_GROUP); ?>

                <nav class="nav-tab-wrapper sv-settings-tabs" aria-label="<?php echo esc_attr__('Slim Volume settings sections', 'slim-volume'); ?>" role="tablist">
                    <?php
                    $tabs = [
                        'general'    => __('General', 'slim-volume'),
                        'catalog'    => __('Catalog', 'slim-volume'),
                        'seo'        => __('SEO', 'slim-volume'),
                        'appearance' => __('Appearance', 'slim-volume'),
                    ];
                    ?>

                    <?php foreach ($tabs as $tab_key => $tab_label) : ?>
                        <a
                            class="nav-tab<?php echo $tab_key === 'general' ? ' nav-tab-active' : ''; ?>"
                            href="#sv-settings-<?php echo esc_attr($tab_key); ?>"
                            id="sv-settings-tab-<?php echo esc_attr($tab_key); ?>"
                            role="tab"
                            aria-controls="sv-settings-<?php echo esc_attr($tab_key); ?>"
                            aria-selected="<?php echo $tab_key === 'general' ? 'true' : 'false'; ?>"
                            data-sv-settings-tab="<?php echo esc_attr($tab_key); ?>"
                        >
                            <?php echo esc_html($tab_label); ?>
                        </a>
                    <?php endforeach; ?>
                </nav>

                <section
                    class="sv-settings-panel is-active"
                    id="sv-settings-general"
                    role="tabpanel"
                    aria-labelledby="sv-settings-tab-general"
                    data-sv-settings-panel="general"
                >
                    <div class="sv-settings-section">
                        <h2><?php echo esc_html__('Frontend Player', 'slim-volume'); ?></h2>
                        <p class="description">
                            <?php echo esc_html__('Choose whether Slim Volume behaves as a full audio-player experience or a catalog-only discography.', 'slim-volume'); ?>
                        </p>

                        <table class="form-table" role="presentation">
                            <tbody>
                                <?php self::render_checkbox_row(
                                    'player_enabled',
                                    __('Frontend player', 'slim-volume'),
                                    __('Enable the persistent audio player, queue drawer, play/queue buttons, AJAX player navigation, and visualizer features.', 'slim-volume'),
                                    $settings,
                                    __('Disable this for a catalog-only discography with release pages and external links but no site audio player.', 'slim-volume')
                                ); ?>

                                <?php self::render_checkbox_row(
                                    'ajax_navigation',
                                    __('AJAX music navigation', 'slim-volume'),
                                    __('Keep audio playing while visitors move between the /music archive, release pages, and track pages.', 'slim-volume'),
                                    $settings,
                                    __('Requires the frontend player to be enabled.', 'slim-volume')
                                ); ?>

                                <?php self::render_checkbox_row(
                                    'persistence',
                                    __('Persistent player state', 'slim-volume'),
                                    __('Restore the queue, current track, drawer state, and playback position after refresh.', 'slim-volume'),
                                    $settings
                                ); ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="sv-settings-section">
                        <h2><?php echo esc_html__('Visualizer', 'slim-volume'); ?></h2>
                        <p class="description">
                            <?php echo esc_html__('Control the visualizer shown inside the expanded player drawer.', 'slim-volume'); ?>
                        </p>

                        <table class="form-table" role="presentation">
                            <tbody>
                                <?php self::render_checkbox_row(
                                    'visualizer',
                                    __('Enable visualizer', 'slim-volume'),
                                    __('Show a visualizer panel in the expanded player drawer.', 'slim-volume'),
                                    $settings,
                                    __('This setting has no effect when the frontend player is disabled.', 'slim-volume')
                                ); ?>

                                <?php self::render_setting_select_row(
                                    'visualizer_mode',
                                    __('Visualizer mode', 'slim-volume'),
                                    self::visualizer_modes(),
                                    $settings,
                                    __('Bars uses the built-in canvas visualizer. Butterchurn uses the installed Butterchurn vendor files and preset library.', 'slim-volume')
                                ); ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="sv-settings-section">
                        <h2><?php echo esc_html__('Troubleshooting', 'slim-volume'); ?></h2>

                        <table class="form-table" role="presentation">
                            <tbody>
                                <?php self::render_checkbox_row(
                                    'debug',
                                    __('Debug mode', 'slim-volume'),
                                    __('Expose extra Slim Volume JavaScript diagnostics in the browser console.', 'slim-volume'),
                                    $settings,
                                    __('Leave this disabled on production sites unless actively troubleshooting.', 'slim-volume')
                                ); ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section
                    class="sv-settings-panel"
                    id="sv-settings-catalog"
                    role="tabpanel"
                    aria-labelledby="sv-settings-tab-catalog"
                    data-sv-settings-panel="catalog"
                    hidden
                >
                    <div class="sv-settings-section">
                        <h2><?php echo esc_html__('Catalog Mode', 'slim-volume'); ?></h2>
                        <p class="description">
                            <?php echo esc_html__('Control where the clickable artwork and title on the /music release grid send visitors.', 'slim-volume'); ?>
                        </p>

                        <table class="form-table" role="presentation">
                            <tbody>
                                <?php self::render_setting_select_row(
                                    'release_card_link_behavior',
                                    __('Release card destination', 'slim-volume'),
                                    [
                                        'internal'                => __('Slim Volume release page', 'slim-volume'),
                                        'external_when_available' => __('Primary External URL when available', 'slim-volume'),
                                    ],
                                    $settings,
                                    __('External mode uses each release\'s Primary External URL and falls back to the internal release page when that field is empty.', 'slim-volume')
                                ); ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section
                    class="sv-settings-panel"
                    id="sv-settings-seo"
                    role="tabpanel"
                    aria-labelledby="sv-settings-tab-seo"
                    data-sv-settings-panel="seo"
                    hidden
                >
                    <div class="sv-settings-section">
                        <h2><?php echo esc_html__('Music SEO Metadata', 'slim-volume'); ?></h2>
                        <p class="description">
                            <?php echo esc_html__('Output music-focused meta descriptions, Open Graph tags, Twitter card tags, and JSON-LD for /music, releases, and tracks.', 'slim-volume'); ?>
                        </p>

                        <table class="form-table" role="presentation">
                            <tbody>
                                <?php self::render_checkbox_row(
                                    'seo_enabled',
                                    __('Enable Slim Volume SEO metadata', 'slim-volume'),
                                    __('Output Slim Volume SEO tags on the music archive, release pages, and track pages.', 'slim-volume'),
                                    $settings,
                                    __('Leave this disabled when another SEO plugin is already controlling these tags for Slim Volume pages.', 'slim-volume')
                                ); ?>

                                <?php self::render_setting_text_row(
                                    'seo_artist_name',
                                    __('Artist / project name', 'slim-volume'),
                                    $settings,
                                    __('Used as the MusicGroup name in JSON-LD and in generated /music social titles. Defaults to the site title when blank.', 'slim-volume')
                                ); ?>

                                <?php self::render_setting_text_row(
                                    'seo_artist_url',
                                    __('Artist / project URL', 'slim-volume'),
                                    $settings,
                                    __('Used as the artist URL inside MusicGroup, MusicAlbum, and MusicRecording data. Defaults to the site home URL when blank.', 'slim-volume'),
                                    'url',
                                    'regular-text code'
                                ); ?>

                                <?php self::render_setting_textarea_row(
                                    'seo_archive_description',
                                    __('Music archive description', 'slim-volume'),
                                    $settings,
                                    __('Used for the /music meta description, Open Graph description, Twitter description, and MusicGroup JSON-LD description.', 'slim-volume')
                                ); ?>

                                <?php self::render_setting_text_row(
                                    'seo_default_image',
                                    __('Default social image URL', 'slim-volume'),
                                    $settings,
                                    __('Fallback image for /music and for releases or tracks without artwork. Release and track artwork still take priority.', 'slim-volume'),
                                    'url',
                                    'regular-text code'
                                ); ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section
                    class="sv-settings-panel"
                    id="sv-settings-appearance"
                    role="tabpanel"
                    aria-labelledby="sv-settings-tab-appearance"
                    data-sv-settings-panel="appearance"
                    hidden
                >
                    <div class="sv-settings-section">
                        <h2><?php echo esc_html__('Player and Music Page Appearance', 'slim-volume'); ?></h2>
                        <p class="description">
                            <?php echo esc_html__('Adjust colors and corner shapes while the live player preview updates immediately.', 'slim-volume'); ?>
                        </p>

                        <details class="sv-settings-developer-note">
                            <summary><?php echo esc_html__('Developer CSS variable reference', 'slim-volume'); ?></summary>
                            <p><?php echo esc_html__('Slim Volume outputs these values as CSS custom properties on :root. Theme and child-theme CSS can still override them.', 'slim-volume'); ?></p>
                            <p>
                                <code>--sv-player-bg</code>,
                                <code>--sv-player-text</code>,
                                <code>--sv-player-muted</code>,
                                <code>--sv-player-border</code>,
                                <code>--sv-player-accent</code>,
                                <code>--sv-button-bg</code>,
                                <code>--sv-button-text</code>,
                                <code>--sv-button-border</code>,
                                <code>--sv-card-border</code>,
                                <code>--sv-radius-card</code>,
                                <code>--sv-radius-art</code>,
                                <code>--sv-radius-control</code>,
                                <code>--sv-radius-small</code>,
                                <code>--sv-radius-pill</code>
                            </p>
                        </details>

                        <div class="sv-settings-appearance-layout">
                            <div class="sv-settings-appearance-fields">
                                <div class="sv-settings-subsection">
                                    <h3><?php echo esc_html__('Preset', 'slim-volume'); ?></h3>
                                    <table class="form-table" role="presentation">
                                        <tbody><?php self::render_preset_row($settings); ?></tbody>
                                    </table>
                                </div>

                                <div class="sv-settings-subsection">
                                    <h3><?php echo esc_html__('Player colors', 'slim-volume'); ?></h3>
                                    <table class="form-table" role="presentation">
                                        <tbody>
                                            <?php
                                            self::render_text_row(
                                                'player_bg',
                                                __('Player and drawer background', 'slim-volume'),
                                                $settings,
                                                '--sv-player-bg',
                                                __('Fixed player bar, expanded queue drawer, and fullscreen visualizer background.', 'slim-volume'),
                                                __('Player + drawer', 'slim-volume')
                                            );
                                            self::render_text_row(
                                                'player_text',
                                                __('Primary player text', 'slim-volume'),
                                                $settings,
                                                '--sv-player-text',
                                                __('Current track title, drawer headings, primary labels, and player service icons.', 'slim-volume'),
                                                __('Player + drawer', 'slim-volume')
                                            );
                                            self::render_text_row(
                                                'player_muted',
                                                __('Secondary player text', 'slim-volume'),
                                                $settings,
                                                '--sv-player-muted',
                                                __('Release name, elapsed time, queue metadata/status, drag handles, and visualizer labels.', 'slim-volume'),
                                                __('Player + drawer', 'slim-volume')
                                            );
                                            self::render_text_row(
                                                'player_border',
                                                __('Passive player borders', 'slim-volume'),
                                                $settings,
                                                '--sv-player-border',
                                                __('Player top edge, inactive progress track, drawer cards, queue rows, visualizer outline, and secondary player controls.', 'slim-volume'),
                                                __('Player + drawer', 'slim-volume')
                                            );
                                            self::render_text_row(
                                                'player_accent',
                                                __('Active player accent', 'slim-volume'),
                                                $settings,
                                                '--sv-player-accent',
                                                __('Played progress, queue-count badge, current/playing queue state, and player hover or focus highlights.', 'slim-volume'),
                                                __('Active states', 'slim-volume')
                                            );
                                            ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="sv-settings-subsection">
                                    <h3><?php echo esc_html__('Buttons and page structure', 'slim-volume'); ?></h3>
                                    <table class="form-table" role="presentation">
                                        <tbody>
                                            <?php
                                            self::render_text_row(
                                                'button_bg',
                                                __('Primary action background', 'slim-volume'),
                                                $settings,
                                                '--sv-button-bg',
                                                __('Primary Play and Queue buttons on music pages, plus the player transport buttons.', 'slim-volume'),
                                                __('Buttons', 'slim-volume')
                                            );
                                            self::render_text_row(
                                                'button_text',
                                                __('Primary action text and icons', 'slim-volume'),
                                                $settings,
                                                '--sv-button-text',
                                                __('Text and transport icons inside primary action buttons.', 'slim-volume'),
                                                __('Buttons', 'slim-volume')
                                            );
                                            self::render_text_row(
                                                'button_border',
                                                __('Primary action and current-track outline', 'slim-volume'),
                                                $settings,
                                                '--sv-button-border',
                                                __('Primary button outlines, queued-button states, and the active track highlight in release tracklists.', 'slim-volume'),
                                                __('Buttons + tracklist', 'slim-volume')
                                            );
                                            self::render_text_row(
                                                'card_border',
                                                __('Content and secondary-control borders', 'slim-volume'),
                                                $settings,
                                                '--sv-card-border',
                                                __('Archive artwork frames, search/sort fields, release track rows, lyrics/story separators, secondary buttons, and track navigation cards.', 'slim-volume'),
                                                __('Music pages', 'slim-volume')
                                            );
                                            ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="sv-settings-subsection">
                                    <h3><?php echo esc_html__('Corner shapes', 'slim-volume'); ?></h3>
                                    <table class="form-table" role="presentation">
                                        <tbody>
                                            <?php
                                            self::render_text_row(
                                                'radius_card',
                                                __('Panel and card radius', 'slim-volume'),
                                                $settings,
                                                '--sv-radius-card',
                                                __('Expanded drawer current-track card, visualizer panel, and previous/next track navigation cards.', 'slim-volume'),
                                                __('Panels', 'slim-volume')
                                            );
                                            self::render_text_row(
                                                'radius_art',
                                                __('Hero artwork radius', 'slim-volume'),
                                                $settings,
                                                '--sv-radius-art',
                                                __('Large featured artwork on single release and single track pages.', 'slim-volume'),
                                                __('Release + track pages', 'slim-volume')
                                            );
                                            self::render_text_row(
                                                'radius_control',
                                                __('Track and queue row radius', 'slim-volume'),
                                                $settings,
                                                '--sv-radius-control',
                                                __('Release track rows, queue rows, drawer artwork, and the empty-queue panel.', 'slim-volume'),
                                                __('Rows + drawer', 'slim-volume')
                                            );
                                            self::render_text_row(
                                                'radius_small',
                                                __('Thumbnail radius', 'slim-volume'),
                                                $settings,
                                                '--sv-radius-small',
                                                __('Archive artwork, player-bar artwork, queue thumbnails, and compact drag controls.', 'slim-volume'),
                                                __('Artwork thumbnails', 'slim-volume')
                                            );
                                            self::render_text_row(
                                                'radius_pill',
                                                __('Button and pill radius', 'slim-volume'),
                                                $settings,
                                                '--sv-radius-pill',
                                                __('Action buttons, transport controls, queue badges, archive search fields, and compact track controls.', 'slim-volume'),
                                                __('Global controls', 'slim-volume')
                                            );
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <aside class="sv-settings-appearance-preview">
                                <?php self::render_appearance_preview($preview_style); ?>
                            </aside>
                        </div>
                    </div>
                </section>

                <div class="sv-settings-save-bar">
                    <?php submit_button(__('Save Settings', 'slim-volume'), 'primary', 'submit', false); ?>
                    <span class="description"><?php echo esc_html__('All tabs are saved together.', 'slim-volume'); ?></span>
                </div>
            </form>

            <?php self::render_tabs_script(); ?>
            <?php self::render_preview_script(); ?>
        </div>
        <?php
    }

    private static function get_preview_style(array $settings): string
{
    $defaults = self::defaults();

    $map = [
        'player_bg'      => '--sv-player-bg',
        'player_text'    => '--sv-player-text',
        'player_muted'   => '--sv-player-muted',
        'player_border'  => '--sv-player-border',
        'player_accent'  => '--sv-player-accent',

        'button_bg'      => '--sv-button-bg',
        'button_text'    => '--sv-button-text',
        'button_border'  => '--sv-button-border',

        'card_border'    => '--sv-card-border',

        'radius_card'    => '--sv-radius-card',
        'radius_art'     => '--sv-radius-art',
        'radius_control' => '--sv-radius-control',
        'radius_small'   => '--sv-radius-small',
        'radius_pill'    => '--sv-radius-pill',
    ];

    $style = [];

    foreach ($map as $setting_key => $css_variable) {
        $fallback = isset($defaults[$setting_key]) && is_string($defaults[$setting_key])
            ? $defaults[$setting_key]
            : '';

        $value = self::sanitize_css_value(
            $settings[$setting_key] ?? $fallback,
            $fallback
        );

        if ($value === '') {
            continue;
        }

        $style[] = sprintf('%s: %s', $css_variable, $value);
    }

    return implode('; ', $style);
}

private static function render_preview_css(): void
{
    ?>
    <style>
        .sv-settings-preview {
            max-width: 720px;
            margin: 18px 0 28px;
        }

        .sv-settings-preview__label {
            margin: 0 0 8px;
            font-weight: 600;
        }

        .sv-settings-preview__player {
            display: grid;
            grid-template-columns: 64px minmax(0, 1fr) auto;
            gap: 14px;
            align-items: center;
            padding: 14px;
            color: var(--sv-player-text);
            background: var(--sv-player-bg);
            border: 1px solid var(--sv-player-border);
            border-radius: var(--sv-radius-card);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.18);
        }

        .sv-settings-preview__art {
            width: 64px;
            height: 64px;
            border-radius: var(--sv-radius-small);
            background:
                linear-gradient(135deg, var(--sv-player-accent), transparent),
                rgba(255, 255, 255, 0.12);
            border: 1px solid var(--sv-player-border);
        }

        .sv-settings-preview__meta {
            min-width: 0;
        }

        .sv-settings-preview__title {
            margin: 0;
            color: var(--sv-player-text);
            font-weight: 700;
        }

        .sv-settings-preview__release {
            margin: 4px 0 0;
            color: var(--sv-player-muted);
        }

        .sv-settings-preview__controls {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: flex-end;
        }

        .sv-settings-preview__button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 34px;
            padding: 7px 12px;
            color: var(--sv-button-text);
            background: var(--sv-button-bg);
            border: 1px solid var(--sv-button-border);
            border-radius: var(--sv-radius-pill);
            font-weight: 700;
            line-height: 1;
        }

        .sv-settings-preview__button--ghost {
            color: var(--sv-player-text);
            background: transparent;
            border-color: var(--sv-player-border);
        }

        .sv-settings-preview__progress {
            grid-column: 1 / -1;
            height: 7px;
            overflow: hidden;
            border-radius: var(--sv-radius-pill);
            background: var(--sv-player-border);
        }

        .sv-settings-preview__progress span {
            display: block;
            width: 42%;
            height: 100%;
            border-radius: inherit;
            background: var(--sv-player-accent);
        }

        .sv-settings-color-control {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }

        .sv-settings-color-control input[type="color"] {
            width: 44px;
            height: 34px;
            padding: 0 2px;
            cursor: pointer;
        }

        .sv-settings-color-control .regular-text {
            max-width: 280px;
        }

        @media (max-width: 782px) {
            .sv-settings-preview__player {
                grid-template-columns: 52px minmax(0, 1fr);
            }

            .sv-settings-preview__art {
                width: 52px;
                height: 52px;
            }

            .sv-settings-preview__controls {
                grid-column: 1 / -1;
                justify-content: flex-start;
            }
        }
    </style>
    <?php
}

private static function render_appearance_preview(string $preview_style): void
{
    ?>
    <div class="sv-settings-preview" data-sv-settings-preview style="<?php echo esc_attr($preview_style); ?>">
        <p class="sv-settings-preview__label">
            <?php echo esc_html__('Appearance Preview', 'slim-volume'); ?>
        </p>

        <div class="sv-settings-preview__player" aria-hidden="true">
            <div class="sv-settings-preview__art"></div>

            <div class="sv-settings-preview__meta">
                <p class="sv-settings-preview__title">
                    <?php echo esc_html__('Example Track Title', 'slim-volume'); ?>
                </p>
                <p class="sv-settings-preview__release">
                    <?php echo esc_html__('Example Release Title', 'slim-volume'); ?>
                </p>
            </div>

            <div class="sv-settings-preview__controls">
                <span class="sv-settings-preview__button sv-settings-preview__button--ghost">
                    <?php echo esc_html__('Prev', 'slim-volume'); ?>
                </span>
                <span class="sv-settings-preview__button">
                    <?php echo esc_html__('Play', 'slim-volume'); ?>
                </span>
                <span class="sv-settings-preview__button sv-settings-preview__button--ghost">
                    <?php echo esc_html__('Next', 'slim-volume'); ?>
                </span>
                <span class="sv-settings-preview__button sv-settings-preview__button--ghost">
                    <?php echo esc_html__('Queue 3', 'slim-volume'); ?>
                </span>
            </div>

            <div class="sv-settings-preview__progress">
                <span></span>
            </div>
        </div>
    </div>
    <?php
}

    private static function render_preview_script(): void
    {
        $presets_json = wp_json_encode(
            self::appearance_presets(),
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );

        if (! is_string($presets_json) || $presets_json === '') {
            $presets_json = '{}';
        }

        $defaults_json = wp_json_encode(
            self::defaults(),
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );

        if (! is_string($defaults_json) || $defaults_json === '') {
            $defaults_json = '{}';
        }

        $option_name_json = wp_json_encode(
            self::OPTION_NAME,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );

        if (! is_string($option_name_json) || $option_name_json === '') {
            $option_name_json = '"slim_volume_settings"';
        }

        ?>
        <script>
            (function () {
                const preview = document.querySelector('[data-sv-settings-preview]');

                if (!preview) {
                    return;
                }

                const optionName = <?php echo $option_name_json; ?>;
                const presets = <?php echo $presets_json; ?>;
                const defaults = <?php echo $defaults_json; ?>;

                const map = {
                    player_bg: '--sv-player-bg',
                    player_text: '--sv-player-text',
                    player_muted: '--sv-player-muted',
                    player_border: '--sv-player-border',
                    player_accent: '--sv-player-accent',
                    button_bg: '--sv-button-bg',
                    button_text: '--sv-button-text',
                    button_border: '--sv-button-border',
                    card_border: '--sv-card-border',
                    radius_card: '--sv-radius-card',
                    radius_art: '--sv-radius-art',
                    radius_control: '--sv-radius-control',
                    radius_small: '--sv-radius-small',
                    radius_pill: '--sv-radius-pill'
                };

                const colorKeys = [
                    'player_bg',
                    'player_text',
                    'player_muted',
                    'player_border',
                    'player_accent',
                    'button_bg',
                    'button_text',
                    'button_border',
                    'card_border'
                ];

                function getField(key) {
                    return document.querySelector('[name="' + optionName + '[' + key + ']"]');
                }

                function getColorField(key) {
                    return document.querySelector('[data-sv-color-picker="' + key + '"]');
                }

                function isHexColor(value) {
                    return /^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i.test(String(value || '').trim());
                }

                function normalizeHex(value) {
                    const nextValue = String(value || '').trim();

                    if (/^#[0-9a-f]{6}$/i.test(nextValue)) {
                        return nextValue;
                    }

                    if (/^#[0-9a-f]{3}$/i.test(nextValue)) {
                        return '#' + nextValue.slice(1).split('').map(function (part) {
                            return part + part;
                        }).join('');
                    }

                    return '';
                }

                function readFieldValue(key) {
                    const field = getField(key);

                    if (!field) {
                        return defaults[key] || '';
                    }

                    const value = String(field.value || '').trim();

                    return value || defaults[key] || '';
                }

                function applyValue(key, value) {
                    if (!map[key]) {
                        return;
                    }

                    const fallback = defaults[key] || '';
                    const nextValue = String(value || fallback || '').trim();

                    if (nextValue === '') {
                        preview.style.removeProperty(map[key]);
                        return;
                    }

                    preview.style.setProperty(map[key], nextValue);
                }

                function syncColorPicker(key, value) {
                    if (colorKeys.indexOf(key) === -1) {
                        return;
                    }

                    const colorField = getColorField(key);

                    if (!colorField) {
                        return;
                    }

                    const normalized = normalizeHex(value);

                    if (normalized !== '') {
                        colorField.value = normalized;
                    }
                }

                function applyCurrentFields() {
                    Object.keys(map).forEach(function (key) {
                        const value = readFieldValue(key);

                        syncColorPicker(key, value);
                        applyValue(key, value);
                    });
                }

                function setPresetToCustom() {
                    const presetField = getField('appearance_preset');

                    if (presetField && presetField.value !== 'custom') {
                        presetField.value = 'custom';
                    }
                }

                Object.keys(map).forEach(function (key) {
                    const field = getField(key);

                    if (!field) {
                        return;
                    }

                    const handleManualChange = function () {
                        const value = readFieldValue(key);

                        setPresetToCustom();
                        syncColorPicker(key, value);
                        applyValue(key, value);
                    };

                    field.addEventListener('input', handleManualChange);
                    field.addEventListener('change', handleManualChange);
                });

                colorKeys.forEach(function (key) {
                    const colorField = getColorField(key);
                    const textField = getField(key);

                    if (!colorField || !textField) {
                        return;
                    }

                    const handleColorChange = function () {
                        textField.value = colorField.value;
                        setPresetToCustom();
                        applyValue(key, colorField.value);
                    };

                    colorField.addEventListener('input', handleColorChange);
                    colorField.addEventListener('change', handleColorChange);
                });

                const presetField = getField('appearance_preset');

                if (presetField) {
                    presetField.addEventListener('change', function () {
                        const preset = presets[presetField.value] || {};
                        const values = preset.values || {};

                        if (presetField.value === 'custom') {
                            applyCurrentFields();
                            return;
                        }

                        Object.keys(map).forEach(function (key) {
                            const value = values[key] || defaults[key] || '';
                            const field = getField(key);

                            if (field && value !== '') {
                                field.value = value;
                            }

                            syncColorPicker(key, value);
                            applyValue(key, value);
                        });
                    });
                }

                applyCurrentFields();
            })();
        </script>
        <?php
    }

    private static function render_tabs_script(): void
    {
        ?>
        <script>
            (function () {
                const tabs = Array.from(document.querySelectorAll('[data-sv-settings-tab]'));
                const panels = Array.from(document.querySelectorAll('[data-sv-settings-panel]'));
                const form = document.querySelector('[data-sv-settings-form]');

                if (!tabs.length || !panels.length) {
                    return;
                }

                const storageKey = 'slimVolumeSettingsTab';

                function activateTab(tabKey, shouldFocus) {
                    const nextTab = tabs.find(function (tab) {
                        return tab.dataset.svSettingsTab === tabKey;
                    }) || tabs[0];

                    const nextKey = nextTab.dataset.svSettingsTab;

                    tabs.forEach(function (tab) {
                        const isActive = tab === nextTab;
                        tab.classList.toggle('nav-tab-active', isActive);
                        tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
                        tab.setAttribute('tabindex', isActive ? '0' : '-1');
                    });

                    panels.forEach(function (panel) {
                        const isActive = panel.dataset.svSettingsPanel === nextKey;
                        panel.hidden = !isActive;
                        panel.classList.toggle('is-active', isActive);
                    });

                    try {
                        window.localStorage.setItem(storageKey, nextKey);
                    } catch (error) {
                        // Storage is optional.
                    }

                    if (window.history && window.history.replaceState) {
                        window.history.replaceState(null, '', '#sv-settings-' + nextKey);
                    }

                    if (shouldFocus) {
                        nextTab.focus();
                    }
                }

                function initialTab() {
                    const match = window.location.hash.match(/^#sv-settings-(general|catalog|seo|appearance)$/);

                    if (match) {
                        return match[1];
                    }

                    try {
                        return window.localStorage.getItem(storageKey) || 'general';
                    } catch (error) {
                        return 'general';
                    }
                }

                tabs.forEach(function (tab, index) {
                    tab.addEventListener('click', function (event) {
                        event.preventDefault();
                        activateTab(tab.dataset.svSettingsTab, false);
                    });

                    tab.addEventListener('keydown', function (event) {
                        if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
                            return;
                        }

                        event.preventDefault();

                        let nextIndex = index;

                        if (event.key === 'ArrowRight') {
                            nextIndex = (index + 1) % tabs.length;
                        } else if (event.key === 'ArrowLeft') {
                            nextIndex = (index - 1 + tabs.length) % tabs.length;
                        } else if (event.key === 'Home') {
                            nextIndex = 0;
                        } else if (event.key === 'End') {
                            nextIndex = tabs.length - 1;
                        }

                        activateTab(tabs[nextIndex].dataset.svSettingsTab, true);
                    });
                });

                if (form) {
                    form.addEventListener('submit', function () {
                        const active = tabs.find(function (tab) {
                            return tab.classList.contains('nav-tab-active');
                        });

                        if (!active) {
                            return;
                        }

                        try {
                            window.localStorage.setItem(storageKey, active.dataset.svSettingsTab);
                        } catch (error) {
                            // Storage is optional.
                        }
                    });
                }

                activateTab(initialTab(), false);
            })();
        </script>
        <?php
    }

    private static function render_checkbox_row(
        string $key,
        string $label,
        string $description,
        array $settings,
        string $extra_description = ''
    ): void {
        ?>
        <tr>
            <th scope="row">
                <?php echo esc_html($label); ?>
            </th>
            <td>
                <label>
                    <input
                        type="checkbox"
                        name="<?php echo esc_attr(self::OPTION_NAME); ?>[<?php echo esc_attr($key); ?>]"
                        value="1"
                        <?php checked(! empty($settings[$key])); ?>
                    >
                    <?php echo esc_html($description); ?>
                </label>

                <?php if ($extra_description !== '') : ?>
                    <p class="description"><?php echo esc_html($extra_description); ?></p>
                <?php endif; ?>
            </td>
        </tr>
        
        <?php
    }

    

    private static function color_setting_keys(): array
    {
        return [
            'player_bg',
            'player_text',
            'player_muted',
            'player_border',
            'player_accent',
            'button_bg',
            'button_text',
            'button_border',
            'card_border',
        ];
    }

    private static function is_color_setting(string $key): bool
    {
        return in_array($key, self::color_setting_keys(), true);
    }

    private static function normalize_hex_color(string $value): string
    {
        $value = trim($value);

        if (preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1) {
            return $value;
        }

        if (preg_match('/^#([0-9a-fA-F])([0-9a-fA-F])([0-9a-fA-F])$/', $value, $matches) === 1) {
            return '#' . $matches[1] . $matches[1] . $matches[2] . $matches[2] . $matches[3] . $matches[3];
        }

        return '';
    }

    private static function color_picker_value(string $key, array $settings): string
    {
        $value = self::normalize_hex_color((string) ($settings[$key] ?? ''));

        if ($value !== '') {
            return $value;
        }

        $default_value = self::normalize_hex_color((string) (self::defaults()[$key] ?? ''));

        if ($default_value !== '') {
            return $default_value;
        }

        return '#000000';
    }

    private static function render_text_row(
        string $key,
        string $label,
        array $settings,
        string $css_variable,
        string $description = '',
        string $scope = ''
    ): void {
        $value       = (string) ($settings[$key] ?? '');
        $placeholder = (string) (self::defaults()[$key] ?? '');
        $is_color    = self::is_color_setting($key);
        ?>
        <tr class="sv-settings-field-row">
            <th scope="row">
                <label for="slim-volume-<?php echo esc_attr($key); ?>">
                    <?php echo esc_html($label); ?>
                </label>

                <?php if ($scope !== '') : ?>
                    <span class="sv-settings-scope"><?php echo esc_html($scope); ?></span>
                <?php endif; ?>
            </th>
            <td>
                <?php if ($is_color) : ?>
                    <div class="sv-settings-color-control">
                        <input
                            type="color"
                            value="<?php echo esc_attr(self::color_picker_value($key, $settings)); ?>"
                            data-sv-color-picker="<?php echo esc_attr($key); ?>"
                            aria-label="<?php echo esc_attr(sprintf(__('%s color picker', 'slim-volume'), $label)); ?>"
                        >

                        <input
                            id="slim-volume-<?php echo esc_attr($key); ?>"
                            class="regular-text code"
                            type="text"
                            name="<?php echo esc_attr(self::OPTION_NAME); ?>[<?php echo esc_attr($key); ?>]"
                            value="<?php echo esc_attr($value); ?>"
                            placeholder="<?php echo esc_attr($placeholder); ?>"
                        >
                    </div>
                <?php else : ?>
                    <input
                        id="slim-volume-<?php echo esc_attr($key); ?>"
                        class="regular-text code"
                        type="text"
                        name="<?php echo esc_attr(self::OPTION_NAME); ?>[<?php echo esc_attr($key); ?>]"
                        value="<?php echo esc_attr($value); ?>"
                        placeholder="<?php echo esc_attr($placeholder); ?>"
                    >
                <?php endif; ?>

                <?php if ($description !== '') : ?>
                    <p class="description sv-settings-field-description"><?php echo esc_html($description); ?></p>
                <?php endif; ?>

                <p class="description sv-settings-variable">
                    <?php
                    printf(
                        wp_kses(
                            __('CSS variable: %s', 'slim-volume'),
                            ['code' => []]
                        ),
                        '<code>' . esc_html($css_variable) . '</code>'
                    );
                    ?>
                </p>

                <?php if ($is_color) : ?>
                    <p class="description sv-settings-advanced-color-note">
                        <?php echo esc_html__('Use the picker for a hex color, or type an advanced CSS value such as rgba(), currentColor, or var(--theme-color).', 'slim-volume'); ?>
                    </p>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }

    private static function render_setting_text_row(
        string $key,
        string $label,
        array $settings,
        string $description = '',
        string $type = 'text',
        string $class = 'regular-text'
    ): void {
        $value = (string) ($settings[$key] ?? '');

        ?>
        <tr>
            <th scope="row">
                <label for="slim-volume-<?php echo esc_attr($key); ?>">
                    <?php echo esc_html($label); ?>
                </label>
            </th>
            <td>
                <input
                    id="slim-volume-<?php echo esc_attr($key); ?>"
                    class="<?php echo esc_attr($class); ?>"
                    type="<?php echo esc_attr($type); ?>"
                    name="<?php echo esc_attr(self::OPTION_NAME); ?>[<?php echo esc_attr($key); ?>]"
                    value="<?php echo esc_attr($value); ?>"
                >

                <?php if ($description !== '') : ?>
                    <p class="description"><?php echo esc_html($description); ?></p>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }

    private static function render_setting_textarea_row(
        string $key,
        string $label,
        array $settings,
        string $description = ''
    ): void {
        $value = (string) ($settings[$key] ?? '');

        ?>
        <tr>
            <th scope="row">
                <label for="slim-volume-<?php echo esc_attr($key); ?>">
                    <?php echo esc_html($label); ?>
                </label>
            </th>
            <td>
                <textarea
                    id="slim-volume-<?php echo esc_attr($key); ?>"
                    class="large-text"
                    rows="4"
                    name="<?php echo esc_attr(self::OPTION_NAME); ?>[<?php echo esc_attr($key); ?>]"
                ><?php echo esc_textarea($value); ?></textarea>

                <?php if ($description !== '') : ?>
                    <p class="description"><?php echo esc_html($description); ?></p>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }



    private static function render_setting_select_row(
        string $key,
        string $label,
        array $options,
        array $settings,
        string $description = ''
    ): void {
        $current = (string) ($settings[$key] ?? (self::defaults()[$key] ?? ''));

        ?>
        <tr>
            <th scope="row">
                <label for="slim-volume-<?php echo esc_attr($key); ?>">
                    <?php echo esc_html($label); ?>
                </label>
            </th>
            <td>
                <select
                    id="slim-volume-<?php echo esc_attr($key); ?>"
                    name="<?php echo esc_attr(self::OPTION_NAME); ?>[<?php echo esc_attr($key); ?>]"
                >
                    <?php foreach ($options as $option_value => $option_label) : ?>
                        <option value="<?php echo esc_attr((string) $option_value); ?>" <?php selected($current, (string) $option_value); ?>>
                            <?php echo esc_html((string) $option_label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <?php if ($description !== '') : ?>
                    <p class="description"><?php echo esc_html($description); ?></p>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }


    private static function render_preset_row(array $settings): void
    {
        $presets = self::appearance_presets();
        $current = (string) ($settings['appearance_preset'] ?? 'custom');

        ?>
        <tr>
            <th scope="row">
                <label for="slim-volume-appearance-preset">
                    <?php echo esc_html__('Appearance preset', 'slim-volume'); ?>
                </label>
            </th>
            <td>
                <select
                    id="slim-volume-appearance-preset"
                    name="<?php echo esc_attr(self::OPTION_NAME); ?>[appearance_preset]"
                >
                    <?php foreach ($presets as $key => $preset) : ?>
                        <option value="<?php echo esc_attr((string) $key); ?>" <?php selected($current, (string) $key); ?>>
                            <?php echo esc_html((string) ($preset['label'] ?? $key)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <p class="description">
                    <?php echo esc_html__('Choose a starter appearance preset. Selecting a preset updates the preview immediately and fills the color/radius fields below.', 'slim-volume'); ?>
                </p>
            </td>
        </tr>
        <?php
    }

    public static function visualizer_modes(): array
    {
        $modes = [
            'bars' => __('Bars', 'slim-volume'),
        ];

        if (self::is_butterchurn_available()) {
            $modes['butterchurn'] = __('Butterchurn', 'slim-volume');
        }

        return $modes;
    }

    public static function is_butterchurn_available(): bool
    {
        if (! defined('SLIM_VOLUME_PATH')) {
            return false;
        }

        return file_exists(SLIM_VOLUME_PATH . 'assets/vendor/butterchurn/butterchurn.min.js')
            && file_exists(SLIM_VOLUME_PATH . 'assets/vendor/butterchurn/butterchurn-presets.min.js');
    }


}
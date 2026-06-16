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
            'ajax_navigation' => true,
            'persistence'    => true,
            'visualizer'     => true,
            'debug'          => false,

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
        $presets  = self::appearance_presets();

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
                    $appearance[$key] = self::sanitize_css_value($value, (string) ($defaults[$key] ?? ''));
                }
            }
        }

        return array_merge(
            [
                'ajax_navigation' => ! empty($input['ajax_navigation']),
                'persistence'    => ! empty($input['persistence']),
                'visualizer'     => ! empty($input['visualizer']),
                'debug'          => ! empty($input['debug']),
            ],
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
        <div class="wrap">
            <?php self::render_preview_css(); ?>

            <h1><?php echo esc_html__('Slim Volume Settings', 'slim-volume'); ?></h1>

            <p>
                <?php
                echo esc_html__(
                    'Configure Slim Volume frontend behavior, player features, and appearance options.',
                    'slim-volume'
                );
                ?>
            </p>

            <form method="post" action="options.php">
                <?php settings_fields(self::OPTION_GROUP); ?>

                <h2><?php echo esc_html__('Frontend Features', 'slim-volume'); ?></h2>

                <table class="form-table" role="presentation">
                    <tbody>
                        <?php self::render_checkbox_row(
                            'ajax_navigation',
                            __('AJAX music navigation', 'slim-volume'),
                            __('Keep the player alive while navigating between music pages.', 'slim-volume'),
                            $settings
                        ); ?>

                        <?php self::render_checkbox_row(
                            'persistence',
                            __('Persistent player state', 'slim-volume'),
                            __('Restore the queue, current track, drawer state, and playback position after refresh.', 'slim-volume'),
                            $settings
                        ); ?>

                        <?php self::render_checkbox_row(
                            'visualizer',
                            __('Visualizer', 'slim-volume'),
                            __('Enable the player drawer visualizer.', 'slim-volume'),
                            $settings
                        ); ?>

                        <?php self::render_checkbox_row(
                            'debug',
                            __('Debug mode', 'slim-volume'),
                            __('Expose extra JavaScript debugging tools in the browser console.', 'slim-volume'),
                            $settings,
                            __('Leave this disabled on production sites unless actively troubleshooting.', 'slim-volume')
                        ); ?>
                    </tbody>
                </table>

                <h2><?php echo esc_html__('Player Appearance', 'slim-volume'); ?></h2>

                <p>
                    <?php
                    echo esc_html__(
                        'These values map directly to Slim Volume CSS variables. You can use hex colors, rgba(), currentColor, or var(--theme-variable) values.',
                        'slim-volume'
                    );
                    ?>
                </p>

                <p class="description">
                    <?php
                    echo esc_html__(
                        'Theme CSS and child theme CSS can still override these variables. The settings below are intended as convenient defaults for site owners.',
                        'slim-volume'
                    );
                    ?>
                </p>

                <div class="notice notice-info inline" style="padding: 12px 16px;">
                    <p>
                        <strong><?php echo esc_html__('Developer note:', 'slim-volume'); ?></strong>
                        <?php
                        echo esc_html__(
                            'Slim Volume outputs these appearance settings as CSS custom properties on :root.',
                            'slim-volume'
                        );
                        ?>
                    </p>

                    <p style="margin-bottom: 0;">
                        <code>--sv-player-bg</code>,
                        <code>--sv-player-text</code>,
                        <code>--sv-player-muted</code>,
                        <code>--sv-player-border</code>,
                        <code>--sv-player-accent</code>,
                        <code>--sv-button-bg</code>,
                        <code>--sv-button-text</code>,
                        <code>--sv-button-border</code>,
                        <code>--sv-radius-pill</code>
                    </p>
                </div>

                <?php self::render_appearance_preview($preview_style); ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <?php
                        self::render_preset_row($settings);

                        self::render_text_row('player_bg', __('Player background', 'slim-volume'), $settings, '--sv-player-bg');
                        self::render_text_row('player_text', __('Player text', 'slim-volume'), $settings, '--sv-player-text');
                        self::render_text_row('player_muted', __('Player muted text', 'slim-volume'), $settings, '--sv-player-muted');
                        self::render_text_row('player_border', __('Player border', 'slim-volume'), $settings, '--sv-player-border');
                        self::render_text_row('player_accent', __('Player accent', 'slim-volume'), $settings, '--sv-player-accent');

                        self::render_text_row('button_bg', __('Button background', 'slim-volume'), $settings, '--sv-button-bg');
                        self::render_text_row('button_text', __('Button text', 'slim-volume'), $settings, '--sv-button-text');
                        self::render_text_row('button_border', __('Button border', 'slim-volume'), $settings, '--sv-button-border');

                        self::render_text_row('card_border', __('Card border', 'slim-volume'), $settings, '--sv-card-border');

                        self::render_text_row('radius_card', __('Card radius', 'slim-volume'), $settings, '--sv-radius-card');
                        self::render_text_row('radius_art', __('Artwork radius', 'slim-volume'), $settings, '--sv-radius-art');
                        self::render_text_row('radius_control', __('Control radius', 'slim-volume'), $settings, '--sv-radius-control');
                        self::render_text_row('radius_small', __('Small radius', 'slim-volume'), $settings, '--sv-radius-small');
                        self::render_text_row('radius_pill', __('Pill radius', 'slim-volume'), $settings, '--sv-radius-pill');
                        ?>
                    </tbody>
                </table>

                <?php submit_button(__('Save Settings', 'slim-volume')); ?>
            </form>
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

            .sv-settings-preview__hint {
                margin: -2px 0 10px;
                color: #646970;
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
        <div class="sv-settings-preview" style="<?php echo esc_attr($preview_style); ?>">
            <p class="sv-settings-preview__label">
                <?php echo esc_html__('Appearance Preview', 'slim-volume'); ?>
            </p>

            <p class="sv-settings-preview__hint">
                <?php echo esc_html__('Preview updates after saving settings.', 'slim-volume'); ?>
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

    private static function render_preset_row(array $settings): void
    {
        $presets = self::appearance_presets();
        $current = isset($settings['appearance_preset'])
            ? sanitize_key((string) $settings['appearance_preset'])
            : 'custom';

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
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($current, $key); ?>>
                            <?php echo esc_html((string) $preset['label']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <p class="description">
                    <?php
                    echo esc_html__(
                        'Choose a preset and save settings to apply it. Select Custom to edit values manually.',
                        'slim-volume'
                    );
                    ?>
                </p>
            </td>
        </tr>
        <?php
    }

    private static function render_text_row(
        string $key,
        string $label,
        array $settings,
        string $css_variable
    ): void {
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
                    class="regular-text code"
                    type="text"
                    name="<?php echo esc_attr(self::OPTION_NAME); ?>[<?php echo esc_attr($key); ?>]"
                    value="<?php echo esc_attr((string) ($settings[$key] ?? '')); ?>"
                    placeholder="<?php echo esc_attr((string) (self::defaults()[$key] ?? '')); ?>"
                >

                <p class="description">
                    <?php
                    printf(
                        esc_html__('Outputs %s.', 'slim-volume'),
                        '<code>' . esc_html($css_variable) . '</code>'
                    );
                    ?>
                </p>
            </td>
        </tr>
        <?php
    }
}

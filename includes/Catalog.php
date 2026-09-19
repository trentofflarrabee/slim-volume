<?php

declare(strict_types=1);

namespace SlimVolume;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Authoritative Slim Volume catalog identity and URL values.
 *
 * Routing code should retrieve catalog values from this class rather than
 * hardcoding public URL bases or archive identity strings.
 */
final class Catalog
{
    public const DEFAULT_BASE = 'music';

    public const DEFAULT_ARCHIVE_TITLE = 'Discography';

    public const DEFAULT_ARCHIVE_INTRO =
        'Browse releases, singles, and track-by-track deep dives.';

    private const SETTINGS_OPTION = 'slim_volume_settings';

    /**
     * Return the effective public catalog base.
     *
     * The runtime value is always a valid, non-empty slug. Existing installs
     * without a saved catalog setting continue to use "music".
     */
    public static function get_base(): string
    {
        $settings = self::get_settings();

        $base = isset($settings['music_base'])
            && is_scalar($settings['music_base'])
                ? sanitize_title((string) $settings['music_base'])
                : '';

        return $base !== ''
            ? $base
            : self::DEFAULT_BASE;
    }

    /**
     * Return the canonical catalog archive URL.
     */
    public static function get_archive_url(): string
    {
        return trailingslashit(
            home_url('/' . self::get_base() . '/')
        );
    }

    /**
     * Return the configured archive identity/title.
     */
    public static function get_archive_title(): string
    {
        $settings = self::get_settings();

        $title = isset($settings['archive_title'])
            && is_scalar($settings['archive_title'])
                ? sanitize_text_field((string) $settings['archive_title'])
                : '';

        return $title !== ''
            ? $title
            : self::DEFAULT_ARCHIVE_TITLE;
    }

    /**
     * Return the configured visible archive introduction.
     */
    public static function get_archive_intro(): string
    {
        $settings = self::get_settings();

        if (! isset($settings['archive_intro'])
            || ! is_scalar($settings['archive_intro'])
        ) {
            return self::DEFAULT_ARCHIVE_INTRO;
        }

        $intro = wp_kses_post((string) $settings['archive_intro']);

        return trim($intro) !== ''
            ? $intro
            : self::DEFAULT_ARCHIVE_INTRO;
    }

    /**
     * Read raw Slim Volume settings without introducing a dependency on the
     * admin Settings class.
     *
     * @return array<string, mixed>
     */
    private static function get_settings(): array
    {
        $settings = get_option(self::SETTINGS_OPTION, []);

        return is_array($settings)
            ? $settings
            : [];
    }
}
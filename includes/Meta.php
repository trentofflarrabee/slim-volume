<?php

declare(strict_types=1);

namespace SlimVolume;

if (! defined('ABSPATH')) {
    exit;
}

final class Meta
{
    public static function register(): void
    {
        self::register_release_meta();
        self::register_track_meta();
    }

    private static function register_release_meta(): void
    {
        $string_fields = [
            '_sv_release_date',
            '_sv_release_type',
            '_sv_label',
            '_sv_catalog_number',
            '_sv_external_label',
        ];

        foreach ($string_fields as $key) {
            register_post_meta(
                PostTypes::RELEASE,
                $key,
                [
                    'single'            => true,
                    'type'              => 'string',
                    'show_in_rest'      => true,
                    'sanitize_callback' => [self::class, 'sanitize_string'],
                    'auth_callback'     => [self::class, 'can_edit_post_meta'],
                ]
            );
        }

        $url_fields = [
            '_sv_external_url',
            '_sv_spotify_url',
            '_sv_apple_music_url',
            '_sv_youtube_url',
            '_sv_bandcamp_url',
            '_sv_purchase_url',
        ];

        foreach ($url_fields as $key) {
            register_post_meta(
                PostTypes::RELEASE,
                $key,
                [
                    'single'            => true,
                    'type'              => 'string',
                    'show_in_rest'      => true,
                    'sanitize_callback' => 'esc_url_raw',
                    'auth_callback'     => [self::class, 'can_edit_post_meta'],
                ]
            );
        }

        register_post_meta(
            PostTypes::RELEASE,
            '_sv_release_credits',
            [
                'single'            => true,
                'type'              => 'string',
                'show_in_rest'      => true,
                'sanitize_callback' => 'wp_kses_post',
                'auth_callback'     => [self::class, 'can_edit_post_meta'],
            ]
        );

        register_post_meta(
            PostTypes::RELEASE,
            '_sv_external_new_tab',
            [
                'single'            => true,
                'type'              => 'boolean',
                'default'           => false,
                'show_in_rest'      => true,
                'sanitize_callback' => [self::class, 'sanitize_bool'],
                'auth_callback'     => [self::class, 'can_edit_post_meta'],
            ]
        );

        register_post_meta(
            PostTypes::RELEASE,
            '_sv_featured_release',
            [
                'single'            => true,
                'type'              => 'boolean',
                'default'           => false,
                'show_in_rest'      => true,
                'sanitize_callback' => [self::class, 'sanitize_bool'],
                'auth_callback'     => [self::class, 'can_edit_post_meta'],
            ]
        );
    }

    private static function register_track_meta(): void
    {
        register_post_meta(
            PostTypes::TRACK,
            '_sv_duration',
            [
                'single'            => true,
                'type'              => 'string',
                'show_in_rest'      => true,
                'sanitize_callback' => [self::class, 'sanitize_string'],
                'auth_callback'     => [self::class, 'can_edit_post_meta'],
            ]
        );

        $url_fields = [
            '_sv_audio_url',
            '_sv_spotify_url',
            '_sv_apple_music_url',
            '_sv_youtube_url',
            '_sv_bandcamp_url',
            '_sv_purchase_url',
            '_sv_download_url',
        ];

        foreach ($url_fields as $key) {
            register_post_meta(
                PostTypes::TRACK,
                $key,
                [
                    'single'            => true,
                    'type'              => 'string',
                    'show_in_rest'      => true,
                    'sanitize_callback' => 'esc_url_raw',
                    'auth_callback'     => [self::class, 'can_edit_post_meta'],
                ]
            );
        }

        $numeric_fields = [
            '_sv_track_number',
            '_sv_disc_number',
            '_sv_duration_seconds',
        ];

        foreach ($numeric_fields as $key) {
            register_post_meta(
                PostTypes::TRACK,
                $key,
                [
                    'single'            => true,
                    'type'              => 'integer',
                    'default'           => 0,
                    'show_in_rest'      => true,
                    'sanitize_callback' => 'absint',
                    'auth_callback'     => [self::class, 'can_edit_post_meta'],
                ]
            );
        }

        register_post_meta(
            PostTypes::TRACK,
            '_sv_release_id',
            [
                'single'       => true,
                'type'         => 'integer',
                'default'      => 0,
                'show_in_rest' => true,
                'sanitize_callback' => static function ($value): int {
                    $release_id = absint($value);

                    if ($release_id <= 0) {
                        return 0;
                    }

                    return PostTypes::RELEASE === get_post_type($release_id)
                        ? $release_id
                        : 0;
                },
                'auth_callback' => [self::class, 'can_edit_post_meta'],
            ]
        );

        register_post_meta(
            PostTypes::TRACK,
            '_sv_audio_attachment_id',
            [
                'single'       => true,
                'type'         => 'integer',
                'default'      => 0,
                'show_in_rest' => true,
                'sanitize_callback' => static function ($value): int {
                    $attachment_id = absint($value);

                    if (
                        $attachment_id <= 0
                        || 'attachment' !== get_post_type($attachment_id)
                    ) {
                        return 0;
                    }

                    $mime_type = (string) get_post_mime_type(
                        $attachment_id
                    );

                    return 0 === strpos($mime_type, 'audio/')
                        ? $attachment_id
                        : 0;
                },
                'auth_callback' => [self::class, 'can_edit_post_meta'],
            ]
        );

        register_post_meta(
            PostTypes::TRACK,
            '_sv_download_attachment_id',
            [
                'single'       => true,
                'type'         => 'integer',
                'default'      => 0,
                'show_in_rest' => true,
                'sanitize_callback' => static function ($value): int {
                    $attachment_id = absint($value);

                    if (
                        $attachment_id <= 0
                        || 'attachment' !== get_post_type($attachment_id)
                    ) {
                        return 0;
                    }

                    /*
                     * Download attachments may intentionally be audio,
                     * archives, PDFs, or other downloadable media, so only
                     * validate that the ID belongs to a real attachment.
                     */
                    return $attachment_id;
                },
                'auth_callback' => [self::class, 'can_edit_post_meta'],
            ]
        );

        register_post_meta(
            PostTypes::TRACK,
            '_sv_lyrics',
            [
                'single'            => true,
                'type'              => 'string',
                'show_in_rest'      => true,
                'sanitize_callback' => 'wp_kses_post',
                'auth_callback'     => [self::class, 'can_edit_post_meta'],
            ]
        );

        register_post_meta(
            PostTypes::TRACK,
            TimedLyrics::META_KEY,
            [
                'single'            => true,
                'type'              => 'string',
                'show_in_rest'      => false,
                'sanitize_callback' => [TimedLyrics::class, 'sanitize_json_meta'],
                'auth_callback'     => [self::class, 'can_edit_post_meta'],
            ]
        );

        register_post_meta(
            PostTypes::TRACK,
            TimedLyrics::STATUS_META_KEY,
            [
                'single'            => true,
                'type'              => 'string',
                'default'           => TimedLyrics::STATUS_NONE,
                'show_in_rest'      => false,
                'sanitize_callback' => [TimedLyrics::class, 'sanitize_status'],
                'auth_callback'     => [self::class, 'can_edit_post_meta'],
            ]
        );

        register_post_meta(
            PostTypes::TRACK,
            '_sv_track_credits',
            [
                'single'            => true,
                'type'              => 'string',
                'show_in_rest'      => true,
                'sanitize_callback' => 'wp_kses_post',
                'auth_callback'     => [self::class, 'can_edit_post_meta'],
            ]
        );

        register_post_meta(
            PostTypes::TRACK,
            '_sv_can_download',
            [
                'single'            => true,
                'type'              => 'boolean',
                'default'           => false,
                'show_in_rest'      => true,
                'sanitize_callback' => [self::class, 'sanitize_bool'],
                'auth_callback'     => [self::class, 'can_edit_post_meta'],
            ]
        );
    }

    public static function sanitize_string($value): string
    {
        return sanitize_text_field((string) $value);
    }

    public static function sanitize_bool($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public static function can_edit_post_meta(
        bool $allowed,
        string $meta_key,
        int $object_id,
        int $user_id,
        string $capability,
        array $required_capabilities
    ): bool {
        unset(
            $allowed,
            $meta_key,
            $capability,
            $required_capabilities
        );

        if ($object_id <= 0 || $user_id <= 0) {
            return false;
        }

        return user_can(
            $user_id,
            'edit_post',
            $object_id
        );
    }
}
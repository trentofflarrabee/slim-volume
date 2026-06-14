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
            '_sv_spotify_url',
            '_sv_apple_music_url',
            '_sv_youtube_url',
            '_sv_bandcamp_url',
            '_sv_purchase_url',
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
        $string_fields = [
            '_sv_duration',
            '_sv_audio_url',
            '_sv_spotify_url',
            '_sv_apple_music_url',
            '_sv_youtube_url',
            '_sv_bandcamp_url',
            '_sv_purchase_url',
            '_sv_download_url',
        ];

        foreach ($string_fields as $key) {
            register_post_meta(
                PostTypes::TRACK,
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

        $integer_fields = [
            '_sv_release_id',
            '_sv_track_number',
            '_sv_disc_number',
            '_sv_duration_seconds',
            '_sv_audio_attachment_id',
        ];

        foreach ($integer_fields as $key) {
            register_post_meta(
                PostTypes::TRACK,
                $key,
                [
                    'single'            => true,
                    'type'              => 'integer',
                    'show_in_rest'      => true,
                    'sanitize_callback' => 'absint',
                    'auth_callback'     => [self::class, 'can_edit_post_meta'],
                ]
            );
        }

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

    public static function can_edit_post_meta(): bool
    {
        return current_user_can('edit_posts');
    }
}
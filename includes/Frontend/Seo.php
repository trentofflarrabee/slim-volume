<?php

declare(strict_types=1);

namespace SlimVolume\Frontend;

use SlimVolume\Admin\Settings;
use SlimVolume\PostTypes;
use SlimVolume\Rewrite;
use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

final class Seo
{
    private const SAME_AS_LINK_KEYS = [
        'spotify',
        'appleMusic',
        'youtube',
        'bandcamp',
    ];

    public static function render(): void
    {
        if (is_admin() || wp_doing_ajax() || wp_is_json_request()) {
            return;
        }

        $settings = Settings::get_settings();

        $enabled = (bool) apply_filters(
            'slim_volume_seo_enabled',
            ! empty($settings['seo_enabled']),
            $settings
        );

        if (! $enabled) {
            return;
        }

        // Check singular music pages before the /music archive.
        //
        // Slim Volume track URLs live under the music base, and depending on the
        // rewrite/query context, WordPress can make the release archive condition
        // look true earlier than expected. Specific pages should always win.
        if (is_singular(PostTypes::TRACK)) {
            self::render_track((int) get_queried_object_id(), $settings);
            return;
        }

        if (is_singular(PostTypes::RELEASE)) {
            self::render_release((int) get_queried_object_id(), $settings);
            return;
        }

        if (is_post_type_archive(PostTypes::RELEASE)) {
            self::render_archive($settings);
        }
    }

    private static function render_archive(array $settings): void
    {
        $archive_url = self::archive_url();
        $artist      = self::artist($settings);
        $description = self::archive_description($settings);
        $releases    = self::get_releases_for_archive();

        $image = self::setting_url($settings, 'seo_default_image');

        if ($image === '' && $releases) {
            $image = self::artwork_url((int) $releases[0]->ID);
        }

        $albums = [];

        foreach ($releases as $release) {
            $album = self::album_schema((int) $release->ID, $artist, false);

            if ($album) {
                $albums[] = $album;
            }
        }

        $schema = self::clean_schema(
            [
                '@context'    => 'https://schema.org',
                '@type'       => 'MusicGroup',
                '@id'         => trailingslashit($artist['url']) . '#artist',
                'name'        => $artist['name'],
                'url'         => $artist['url'],
                'description' => $description,
                'image'       => $image,
                'album'       => $albums,
            ]
        );

        self::render_head_block(
            [
                'title'       => sprintf(
                    /* translators: %s: site/artist name. */
                    __('Music by %s', 'slim-volume'),
                    $artist['name']
                ),
                'description' => $description,
                'url'         => $archive_url,
                'image'       => $image,
                'type'        => 'website',
            ],
            $schema
        );
    }

    private static function render_release(int $release_id, array $settings): void
    {
        $release = get_post($release_id);

        if (! $release instanceof WP_Post || $release->post_type !== PostTypes::RELEASE) {
            return;
        }

        $artist      = self::artist($settings);
        $title       = get_the_title($release_id);
        $url         = get_permalink($release_id);
        $image       = self::artwork_url($release_id) ?: self::setting_url($settings, 'seo_default_image');
        $description = self::post_description($release_id);
        $release_date = self::release_date($release_id);
        $schema      = self::release_schema($release_id, $artist);

        self::render_head_block(
            [
                'title'       => $title,
                'description' => $description,
                'url'         => $url,
                'image'       => $image,
                'type'        => 'music.album',
                'extra'       => [
                    ['property' => 'music:release_date', 'content' => $release_date],
                ],
            ],
            $schema
        );
    }

    private static function render_track(int $track_id, array $settings): void
    {
        $track = get_post($track_id);

        if (! $track instanceof WP_Post || $track->post_type !== PostTypes::TRACK) {
            return;
        }

        $artist     = self::artist($settings);
        $release_id = Rewrite::get_track_release_id($track_id);
        $data       = PlayerData::get_track_data($track_id);

        $title       = get_the_title($track_id);
        $url         = get_permalink($track_id);
        $image       = self::artwork_url($track_id, $release_id) ?: self::setting_url($settings, 'seo_default_image');
        $description = self::post_description($track_id);

        if ($description === '' && $release_id > 0) {
            $description = sprintf(
                /* translators: 1: track title, 2: release title. */
                __('Listen to %1$s from %2$s.', 'slim-volume'),
                $title,
                get_the_title($release_id)
            );
        }

        $duration_seconds = isset($data['durationSeconds'])
            ? (int) $data['durationSeconds']
            : 0;

        if ($duration_seconds <= 0) {
            $duration_seconds = self::duration_string_to_seconds((string) ($data['duration'] ?? ''));
        }

        $schema = self::track_schema($track_id, $artist);

        self::render_head_block(
            [
                'title'       => $title,
                'description' => $description,
                'url'         => $url,
                'image'       => $image,
                'type'        => 'music.song',
                'extra'       => [
                    ['property' => 'music:duration', 'content' => $duration_seconds > 0 ? (string) $duration_seconds : ''],
                    ['property' => 'music:album', 'content' => $release_id > 0 ? get_permalink($release_id) : ''],
                ],
            ],
            $schema
        );
    }

    private static function render_head_block(array $meta, array $schema): void
    {
        $title       = self::single_line((string) ($meta['title'] ?? ''));
        $description = self::meta_description((string) ($meta['description'] ?? ''));
        $url         = esc_url((string) ($meta['url'] ?? ''));
        $image       = esc_url((string) ($meta['image'] ?? ''));
        $type        = self::single_line((string) ($meta['type'] ?? 'website'));
        $twitter_card = $image !== '' ? 'summary_large_image' : 'summary';

        echo "\n<!-- Slim Volume SEO metadata -->\n";

        if ($description !== '') {
            self::meta_name('description', $description);
        }

        self::meta_property('og:type', $type);
        self::meta_property('og:title', $title);
        self::meta_property('og:description', $description);
        self::meta_property('og:url', $url);

        if ($image !== '') {
            self::meta_property('og:image', $image);
        }

        foreach (($meta['extra'] ?? []) as $extra) {
            if (! is_array($extra)) {
                continue;
            }

            self::meta_property((string) ($extra['property'] ?? ''), (string) ($extra['content'] ?? ''));
        }

        self::meta_name('twitter:card', $twitter_card);
        self::meta_name('twitter:title', $title);
        self::meta_name('twitter:description', $description);

        if ($image !== '') {
            self::meta_name('twitter:image', $image);
        }

        if ($schema) {
            echo '<script type="application/ld+json">' . "\n";
            echo wp_json_encode(
                $schema,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
            );
            echo "\n</script>\n";
        }

        echo "<!-- /Slim Volume SEO metadata -->\n";
    }

    private static function release_schema(int $release_id, array $artist): array
    {
        $schema = self::album_schema($release_id, $artist, true);

        if (! $schema) {
            return [];
        }

        $schema = ['@context' => 'https://schema.org'] + $schema;

        $playlist = PlayerData::get_release_playlist($release_id);
        $items    = [];

        foreach ($playlist as $index => $track) {
            $track_id = (int) ($track['id'] ?? 0);

            if ($track_id <= 0) {
                continue;
            }

            $position = (int) ($track['trackNumber'] ?? 0);

            if ($position <= 0) {
                $position = $index + 1;
            }

            $items[] = [
                '@type'    => 'ListItem',
                'position' => $position,
                'item'     => self::track_schema($track_id, $artist, false),
            ];
        }

        if ($items) {
            $schema['track'] = [
                '@type'           => 'ItemList',
                'numberOfItems'   => count($items),
                'itemListElement' => $items,
            ];
        }

        return self::clean_schema($schema);
    }

    private static function album_schema(int $release_id, array $artist, bool $full = false): array
    {
        $release = get_post($release_id);

        if (! $release instanceof WP_Post || $release->post_type !== PostTypes::RELEASE) {
            return [];
        }

        $url          = get_permalink($release_id);
        $image        = self::artwork_url($release_id);
        $date         = self::release_date($release_id);
        $description  = $full ? self::post_description($release_id) : '';
        $release_type = (string) get_post_meta($release_id, '_sv_release_type', true);
        $genre        = (string) get_post_meta($release_id, '_sv_genre', true);

        return self::clean_schema(
            [
                '@type'            => 'MusicAlbum',
                '@id'              => trailingslashit($url) . '#album',
                'name'             => get_the_title($release_id),
                'url'              => $url,
                'image'            => $image,
                'datePublished'    => $date,
                'description'      => $description,
                'genre'            => $genre,
                'albumReleaseType' => self::album_release_type($release_type),
                'byArtist'         => self::artist_schema($artist),
            ]
        );
    }

    private static function track_schema(int $track_id, array $artist, bool $full = true): array
    {
        $track = get_post($track_id);

        if (! $track instanceof WP_Post || $track->post_type !== PostTypes::TRACK) {
            return [];
        }

        $release_id = Rewrite::get_track_release_id($track_id);
        $data       = PlayerData::get_track_data($track_id);
        $url        = get_permalink($track_id);

        $duration_seconds = isset($data['durationSeconds'])
            ? (int) $data['durationSeconds']
            : 0;

        if ($duration_seconds <= 0) {
            $duration_seconds = self::duration_string_to_seconds((string) ($data['duration'] ?? ''));
        }

        $schema = [
            '@type'     => 'MusicRecording',
            '@id'       => trailingslashit($url) . '#recording',
            'name'      => get_the_title($track_id),
            'url'       => $url,
            'image'     => self::artwork_url($track_id, $release_id),
            'duration'  => self::seconds_to_iso_duration($duration_seconds),
            'byArtist'  => self::artist_schema($artist),
            'sameAs'    => self::same_as_links((array) ($data['links'] ?? [])),
        ];

        if ($full) {
            $schema = ['@context' => 'https://schema.org'] + $schema;
            $schema['description'] = self::post_description($track_id);

            if (! empty($data['audioUrl'])) {
                $schema['audio'] = esc_url_raw((string) $data['audioUrl']);
            }
        }

        if ($release_id > 0) {
            $release_url = get_permalink($release_id);

            $schema['inAlbum'] = self::clean_schema(
                [
                    '@type'         => 'MusicAlbum',
                    '@id'           => trailingslashit($release_url) . '#album',
                    'name'          => get_the_title($release_id),
                    'url'           => $release_url,
                    'image'         => self::artwork_url($release_id),
                    'datePublished' => self::release_date($release_id),
                    'byArtist'      => self::artist_schema($artist),
                ]
            );
        }

        return self::clean_schema($schema);
    }


    private static function album_release_type(string $release_type): string
    {
        $release_type = strtolower(trim($release_type));

        if ($release_type === '') {
            return '';
        }

        if (str_contains($release_type, 'single')) {
            return 'https://schema.org/SingleRelease';
        }

        if (str_contains($release_type, 'ep')) {
            return 'https://schema.org/EPRelease';
        }

        if (str_contains($release_type, 'album') || str_contains($release_type, 'lp')) {
            return 'https://schema.org/AlbumRelease';
        }

        return '';
    }

    private static function artist(array $settings): array
    {
        $name = trim((string) ($settings['seo_artist_name'] ?? ''));
        $url  = self::setting_url($settings, 'seo_artist_url');

        if ($name === '') {
            $name = get_bloginfo('name') ?: __('Artist', 'slim-volume');
        }

        if ($url === '') {
            $url = home_url('/');
        }

        return [
            'name' => $name,
            'url'  => $url,
        ];
    }

    private static function artist_schema(array $artist): array
    {
        return self::clean_schema(
            [
                '@type' => 'MusicGroup',
                '@id'   => trailingslashit($artist['url']) . '#artist',
                'name'  => $artist['name'],
                'url'   => $artist['url'],
            ]
        );
    }

    private static function archive_description(array $settings): string
    {
        $description = trim((string) ($settings['seo_archive_description'] ?? ''));

        if ($description !== '') {
            return $description;
        }

        return sprintf(
            /* translators: %s: site/artist name. */
            __('Explore %s music releases, singles, tracks, artwork, and stories.', 'slim-volume'),
            get_bloginfo('name') ?: __('this artist', 'slim-volume')
        );
    }

    private static function get_releases_for_archive(): array
    {
        $limit = (int) apply_filters('slim_volume_seo_archive_release_limit', 100);

        if ($limit <= 0) {
            $limit = 100;
        }

        $releases = get_posts(
            [
                'post_type'      => PostTypes::RELEASE,
                'post_status'    => 'publish',
                'posts_per_page' => $limit,
                'orderby'        => 'date',
                'order'          => 'DESC',
            ]
        );

        usort(
            $releases,
            static function (WP_Post $a, WP_Post $b): int {
                $a_date = (string) get_post_meta((int) $a->ID, '_sv_release_date', true);
                $b_date = (string) get_post_meta((int) $b->ID, '_sv_release_date', true);

                $a_time = $a_date !== '' ? strtotime($a_date) : strtotime($a->post_date_gmt ?: $a->post_date);
                $b_time = $b_date !== '' ? strtotime($b_date) : strtotime($b->post_date_gmt ?: $b->post_date);

                return ($b_time ?: 0) <=> ($a_time ?: 0);
            }
        );

        return $releases;
    }

    private static function release_date(int $release_id): string
    {
        $date = trim((string) get_post_meta($release_id, '_sv_release_date', true));

        if ($date === '') {
            return '';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }

        $timestamp = strtotime($date);

        return $timestamp ? gmdate('Y-m-d', $timestamp) : '';
    }

    private static function artwork_url(int $post_id, int $fallback_post_id = 0): string
    {
        $image_id = get_post_thumbnail_id($post_id);

        if (! $image_id && $fallback_post_id > 0) {
            $image_id = get_post_thumbnail_id($fallback_post_id);
        }

        if (! $image_id) {
            return '';
        }

        $url = wp_get_attachment_image_url($image_id, 'full');

        return $url ? esc_url_raw($url) : '';
    }

    private static function post_description(int $post_id): string
    {
        $excerpt = trim((string) get_the_excerpt($post_id));

        if ($excerpt === '') {
            $content = (string) get_post_field('post_content', $post_id);
            $excerpt = wp_trim_words(wp_strip_all_tags(strip_shortcodes($content)), 32, '…');
        }

        return self::single_line($excerpt);
    }

    private static function same_as_links(array $links): array
    {
        $same_as = [];

        foreach (self::SAME_AS_LINK_KEYS as $key) {
            $url = isset($links[$key]) ? esc_url_raw((string) $links[$key]) : '';

            if ($url === '' || ! self::is_canonical_external_music_url($url)) {
                continue;
            }

            $same_as[] = $url;
        }

        return array_values(array_unique($same_as));
    }

    private static function is_canonical_external_music_url(string $url): bool
    {
        $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));

        if ($host === '') {
            return false;
        }

        $blocked_fragments = [
            'awstrack.me',
            'ffm.to',
            'bit.ly',
            't.co',
        ];

        foreach ($blocked_fragments as $blocked) {
            if (str_contains($host, $blocked)) {
                return false;
            }
        }

        return true;
    }

    private static function seconds_to_iso_duration(int $seconds): string
    {
        if ($seconds <= 0) {
            return '';
        }

        $hours = intdiv($seconds, HOUR_IN_SECONDS);
        $seconds -= $hours * HOUR_IN_SECONDS;

        $minutes = intdiv($seconds, MINUTE_IN_SECONDS);
        $seconds -= $minutes * MINUTE_IN_SECONDS;

        $duration = 'PT';

        if ($hours > 0) {
            $duration .= $hours . 'H';
        }

        if ($minutes > 0) {
            $duration .= $minutes . 'M';
        }

        if ($seconds > 0) {
            $duration .= $seconds . 'S';
        }

        return $duration !== 'PT' ? $duration : '';
    }

    private static function duration_string_to_seconds(string $duration): int
    {
        $duration = trim($duration);

        if ($duration === '') {
            return 0;
        }

        $parts = array_map('intval', explode(':', $duration));

        if (count($parts) === 2) {
            [$minutes, $seconds] = $parts;

            return max(0, ($minutes * MINUTE_IN_SECONDS) + $seconds);
        }

        if (count($parts) === 3) {
            [$hours, $minutes, $seconds] = $parts;

            return max(0, ($hours * HOUR_IN_SECONDS) + ($minutes * MINUTE_IN_SECONDS) + $seconds);
        }

        return 0;
    }

    private static function archive_url(): string
    {
        $archive_url = get_post_type_archive_link(PostTypes::RELEASE);

        return $archive_url ? $archive_url : home_url('/music/');
    }

    private static function setting_url(array $settings, string $key): string
    {
        $url = isset($settings[$key]) ? esc_url_raw((string) $settings[$key]) : '';

        return $url;
    }

    private static function meta_name(string $name, string $content): void
    {
        $name    = trim($name);
        $content = trim($content);

        if ($name === '' || $content === '') {
            return;
        }

        printf(
            '<meta name="%s" content="%s">' . "\n",
            esc_attr($name),
            esc_attr($content)
        );
    }

    private static function meta_property(string $property, string $content): void
    {
        $property = trim($property);
        $content  = trim($content);

        if ($property === '' || $content === '') {
            return;
        }

        printf(
            '<meta property="%s" content="%s">' . "\n",
            esc_attr($property),
            esc_attr($content)
        );
    }

    private static function meta_description(string $description): string
    {
        $description = self::single_line($description);

        if ($description === '') {
            return '';
        }

        return wp_html_excerpt($description, 220, '…');
    }

    private static function single_line(string $value): string
    {
        $value = wp_strip_all_tags(strip_shortcodes($value));
        $value = html_entity_decode($value, ENT_QUOTES, get_bloginfo('charset') ?: 'UTF-8');
        $value = preg_replace('/\s+/', ' ', $value);

        return trim((string) $value);
    }

    private static function clean_schema(array $value): array
    {
        $clean   = [];
        $is_list = array_keys($value) === range(0, count($value) - 1);

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $item = self::clean_schema($item);

                if ($item === []) {
                    continue;
                }

                $clean[$key] = $item;
                continue;
            }

            if ($item === null || $item === '') {
                continue;
            }

            $clean[$key] = $item;
        }

        return $is_list ? array_values($clean) : $clean;
    }
}

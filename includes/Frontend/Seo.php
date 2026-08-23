<?php

declare(strict_types=1);

namespace SlimVolume\Frontend;

use SlimVolume\Admin\Settings;
use SlimVolume\Artists\ArtistResolver;
use SlimVolume\PostTypes;
use SlimVolume\Rewrite;
use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

final class Seo
{
    private const SAME_AS_LINK_KEYS = [
        'external',
        'spotify',
        'appleMusic',
        'youtube',
        'bandcamp',
    ];

    public static function filter_document_title(array $title): array
{
    if (is_admin() || wp_doing_ajax() || wp_is_json_request()) {
        return $title;
    }

    $settings = Settings::get_settings();

    $mode = apply_filters(
        'slim_volume_seo_mode',
        $settings['seo_mode'] ?? 'off',
        $settings
    );

    $mode = Settings::normalize_seo_mode($mode);

    if ($mode !== 'full') {
        return $title;
    }

    if (is_singular(PostTypes::TRACK)) {
        $track_id = (int) get_queried_object_id();

        if ($track_id > 0) {
            $track_title = self::single_line(
                get_the_title($track_id)
            );

            if ($track_title !== '') {
                $title['title'] = $track_title;
            }
        }

        return $title;
    }

    if (is_singular(PostTypes::RELEASE)) {
        $release_id = (int) get_queried_object_id();

        if ($release_id > 0) {
            $release_title = self::single_line(
                get_the_title($release_id)
            );

            if ($release_title !== '') {
                $title['title'] = $release_title;
            }
        }

        return $title;
    }

    if (is_post_type_archive(PostTypes::RELEASE)) {
        $archive_title = self::archive_title($settings);

        if ($archive_title !== '') {
            $title['title'] = $archive_title;
        }
    }

    return $title;
}

public static function render(): void
{
    if (is_admin() || wp_doing_ajax() || wp_is_json_request()) {
        return;
    }

    $settings = Settings::get_settings();

    $mode = apply_filters(
        'slim_volume_seo_mode',
        $settings['seo_mode'] ?? 'off',
        $settings
    );

    $mode = Settings::normalize_seo_mode($mode);

    if ($mode === 'off') {
        return;
    }

    // Check singular music pages before the /music archive.
    //
    // Slim Volume track URLs live under the music base, and depending on the
    // rewrite/query context, WordPress can make the release archive condition
    // look true earlier than expected. Specific pages should always win.
    if (is_singular(PostTypes::TRACK)) {
        self::render_track(
            (int) get_queried_object_id(),
            $settings,
            $mode
        );
        return;
    }

    if (is_singular(PostTypes::RELEASE)) {
        self::render_release(
            (int) get_queried_object_id(),
            $settings,
            $mode
        );
        return;
    }

    if (is_post_type_archive(PostTypes::RELEASE)) {
        self::render_archive($settings, $mode);
    }
}

private static function render_archive(array $settings, string $mode): void
{
    $archive_url = self::archive_url();
    $description = self::archive_description($settings);
    $releases    = ArchiveQuery::posts($settings);

    $release_ids = array_map(
        static fn (WP_Post $release): int => (int) $release->ID,
        $releases
    );

    $default_artist = ArtistResolver::default_artist($settings);
    $artists        = ArtistResolver::for_releases(
        $release_ids,
        $settings
    );

    if (! $artists) {
        $artists = [
            ArtistResolver::identity_key($default_artist) => $default_artist,
        ];
    }

    $image = self::setting_url(
        $settings,
        'seo_default_image'
    );

    if ($image === '' && $releases) {
        $image = self::artwork_url(
            (int) $releases[0]->ID
        );
    }

$title = self::archive_title($settings);

    $graph = [];

    foreach ($artists as $artist) {
        if (! is_array($artist)) {
            continue;
        }

        $artist_node = self::artist_schema($artist);

        if ($artist_node) {
            $graph[] = $artist_node;
        }
    }

    $items = [];

    foreach ($releases as $index => $release) {
        $release_id = (int) $release->ID;

        $release_artist = ArtistResolver::for_release(
            $release_id,
            $settings
        );

        $album = self::album_schema(
            $release_id,
            $release_artist,
            false
        );

        if (! $album) {
            continue;
        }

        $graph[] = $album;

        $album_id = (string) ($album['@id'] ?? '');

        if ($album_id !== '') {
            $items[] = [
                '@type'    => 'ListItem',
                'position' => $index + 1,
                'item'     => self::schema_reference($album_id),
            ];
        }
    }

    if ($items) {
        $graph[] = self::clean_schema(
            [
                '@type'           => 'ItemList',
                '@id'             => trailingslashit($archive_url)
                    . '#sv-music-catalog',
                'name'            => $title,
                'numberOfItems'   => count($items),
                'itemListElement' => $items,
            ]
        );
    }

    $schema = self::clean_schema(
        [
            '@context' => 'https://schema.org',
            '@graph'   => $graph,
        ]
    );

$document = self::build_document(
    'archive',
    [
        'title'       => $title,
        'description' => $description,
        'url'         => $archive_url,
        'image'       => $image,
        'og_type'     => 'website',
        'og_extra'    => [],
        'schema'      => $schema,
    ],
    $settings
);

self::render_document(
    $document,
    $mode,
    $settings
);
}

private static function render_release(int $release_id, array $settings, string $mode): void
    {
        $release = get_post($release_id);

        if (! $release instanceof WP_Post || $release->post_type !== PostTypes::RELEASE) {
            return;
        }

        $artist       = ArtistResolver::for_release($release_id, $settings);
        $title        = get_the_title($release_id);
        $url          = get_permalink($release_id);
        $image        = self::artwork_url($release_id) ?: self::setting_url($settings, 'seo_default_image');
        $description  = self::release_description($release_id, $artist);
        $release_date = self::release_date($release_id);
        $schema       = self::release_schema($release_id, $artist);

$document = self::build_document(
    'release',
    [
        'object_id'   => $release_id,
        'title'       => $title,
        'description' => $description,
        'url'         => $url,
        'image'       => $image,
        'og_type'     => 'music.album',
        'og_extra'    => [
            [
                'property' => 'music:release_date',
                'content'  => $release_date,
            ],
        ],
        'schema'      => $schema,
    ],
    $settings
);

self::render_document(
    $document,
    $mode,
    $settings
);
    }

private static function render_track(int $track_id, array $settings, string $mode): void
    {
        $track = get_post($track_id);

        if (! $track instanceof WP_Post || $track->post_type !== PostTypes::TRACK) {
            return;
        }

        $artist     = ArtistResolver::for_track($track_id, $settings);
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

$schema = self::track_document_schema(
    $track_id,
    $artist
);

$document = self::build_document(
    'track',
    [
        'object_id'   => $track_id,
        'title'       => $title,
        'description' => $description,
        'url'         => $url,
        'image'       => $image,
        'og_type'     => 'music.song',
        'og_extra'    => [
            [
                'property' => 'music:duration',
                'content'  => $duration_seconds > 0
                    ? (string) $duration_seconds
                    : '',
            ],
            [
                'property' => 'music:album',
                'content'  => $release_id > 0
                    ? get_permalink($release_id)
                    : '',
            ],
        ],
        'schema'      => $schema,
    ],
    $settings
);

self::render_document(
    $document,
    $mode,
    $settings
);
    }

/**
 * Build the normalized internal representation of a Slim Volume music page.
 */
private static function build_document(
    string $context,
    array $data,
    array $settings
): array {
    $allowed_contexts = [
        'archive',
        'release',
        'track',
    ];

    if (! in_array($context, $allowed_contexts, true)) {
        $context = '';
    }

    $document = [
        'context'     => $context,
        'object_id'   => absint($data['object_id'] ?? 0),
        'title'       => self::single_line(
            (string) ($data['title'] ?? '')
        ),
        'description' => self::meta_description(
            (string) ($data['description'] ?? '')
        ),
        'url'         => esc_url_raw(
            (string) ($data['url'] ?? '')
        ),
        'image'       => esc_url_raw(
            (string) ($data['image'] ?? '')
        ),
        'og_type'     => self::single_line(
            (string) ($data['og_type'] ?? 'website')
        ),
        'og_extra'    => is_array($data['og_extra'] ?? null)
            ? $data['og_extra']
            : [],
        'schema'      => is_array($data['schema'] ?? null)
            ? $data['schema']
            : [],
    ];

    $filtered = apply_filters(
        'slim_volume_seo_document',
        $document,
        $settings
    );

    if (is_array($filtered)) {
        $document = $filtered;
    }

    /*
     * Normalize again after filtering so extensions cannot accidentally
     * produce malformed document state.
     */
    $document['context'] = in_array(
        (string) ($document['context'] ?? ''),
        $allowed_contexts,
        true
    )
        ? (string) $document['context']
        : $context;

    $document['object_id'] = absint(
        $document['object_id'] ?? 0
    );

    $document['title'] = self::single_line(
        (string) ($document['title'] ?? '')
    );

    $document['description'] = self::meta_description(
        (string) ($document['description'] ?? '')
    );

    $document['url'] = esc_url_raw(
        (string) ($document['url'] ?? '')
    );

    $document['image'] = esc_url_raw(
        (string) ($document['image'] ?? '')
    );

    $document['og_type'] = self::single_line(
        (string) ($document['og_type'] ?? 'website')
    );

    if ($document['og_type'] === '') {
        $document['og_type'] = 'website';
    }

    $document['og_extra'] = is_array(
        $document['og_extra'] ?? null
    )
        ? $document['og_extra']
        : [];

    $document['schema'] = is_array(
        $document['schema'] ?? null
    )
        ? $document['schema']
        : [];

    return $document;
}

/**
 * Apply Slim Volume-owned schema extension points.
 */
private static function filter_document_schema(
    array $document,
    array $settings
): array {
    $schema  = is_array($document['schema'] ?? null)
        ? $document['schema']
        : [];
    $context = (string) ($document['context'] ?? '');

    switch ($context) {
        case 'archive':
            $schema = apply_filters(
                'slim_volume_seo_archive_schema',
                $schema,
                $document,
                $settings
            );
            break;

        case 'release':
            $schema = apply_filters(
                'slim_volume_seo_release_schema',
                $schema,
                $document,
                $settings
            );
            break;

        case 'track':
            $schema = apply_filters(
                'slim_volume_seo_track_schema',
                $schema,
                $document,
                $settings
            );
            break;
    }

    if (! is_array($schema)) {
        $schema = [];
    }

    $schema = apply_filters(
        'slim_volume_seo_schema',
        $schema,
        $document,
        $settings
    );

    $document['schema'] = is_array($schema)
        ? $schema
        : [];

    return $document;
}

private static function render_document(
    array $document,
    string $mode,
    array $settings
): void {
    $document = self::filter_document_schema(
        $document,
        $settings
    );

    if ($mode === 'schema' || $mode === 'full') {
        self::render_schema(
            (array) ($document['schema'] ?? [])
        );
    }

    if ($mode === 'full') {
        self::render_meta(
            [
                'title'       => $document['title'] ?? '',
                'description' => $document['description'] ?? '',
                'url'         => $document['url'] ?? '',
                'image'       => $document['image'] ?? '',
                'type'        => $document['og_type'] ?? 'website',
                'extra'       => $document['og_extra'] ?? [],
            ]
        );
    }
}

private static function render_schema(array $schema): void
{
    if (! $schema) {
        return;
    }

    $json = wp_json_encode(
        $schema,
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_PRETTY_PRINT
        | JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
    );

    if (! is_string($json) || $json === '') {
        return;
    }

    echo "\n<!-- Slim Volume music schema -->\n";
    echo '<script type="application/ld+json">' . "\n";
    echo wp_kses($json, []);
    echo "\n</script>\n";
    echo "<!-- /Slim Volume music schema -->\n";
}

private static function render_meta(array $meta): void
{
    $title        = self::single_line((string) ($meta['title'] ?? ''));
    $description  = self::meta_description((string) ($meta['description'] ?? ''));
    $url          = esc_url((string) ($meta['url'] ?? ''));
    $image        = esc_url((string) ($meta['image'] ?? ''));
    $type         = self::single_line((string) ($meta['type'] ?? 'website'));
    $twitter_card = $image !== ''
        ? 'summary_large_image'
        : 'summary';

    echo "\n<!-- Slim Volume metadata -->\n";

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

        self::meta_property(
            (string) ($extra['property'] ?? ''),
            (string) ($extra['content'] ?? '')
        );
    }

    self::meta_name('twitter:card', $twitter_card);
    self::meta_name('twitter:title', $title);
    self::meta_name('twitter:description', $description);

    if ($image !== '') {
        self::meta_name('twitter:image', $image);
    }

    echo "<!-- /Slim Volume metadata -->\n";
}

private static function release_schema(
    int $release_id,
    array $artist
): array {
    $artist_node = self::artist_schema($artist);

    $album = self::album_schema(
        $release_id,
        $artist,
        true
    );

    if (! $album) {
        return [];
    }

    $graph = [];

    if ($artist_node) {
        $graph[] = $artist_node;
    }

    $playlist = PlayerData::get_release_playlist(
        $release_id
    );

    $items      = [];
    $recordings = [];

    foreach ($playlist as $index => $track) {
        $track_id = (int) ($track['id'] ?? 0);

        if ($track_id <= 0) {
            continue;
        }

        $recording = self::track_schema(
            $track_id,
            $artist,
            false
        );

        if (! $recording) {
            continue;
        }

        $recording_id = (string) (
            $recording['@id'] ?? ''
        );

        if ($recording_id === '') {
            continue;
        }

        $position = (int) (
            $track['trackNumber'] ?? 0
        );

        if ($position <= 0) {
            $position = $index + 1;
        }

        $items[] = [
            '@type'    => 'ListItem',
            'position' => $position,
            'item'     => self::schema_reference(
                $recording_id
            ),
        ];

        $recordings[] = $recording;
    }

    if ($items) {
        $album['track'] = self::clean_schema(
            [
                '@type'           => 'ItemList',
                '@id'             => self::album_schema_id(
                    $release_id
                ) . '-tracks',
                'numberOfItems'   => count($items),
                'itemListElement' => $items,
            ]
        );
    }

    $graph[] = $album;

    foreach ($recordings as $recording) {
        $graph[] = $recording;
    }

    return self::clean_schema(
        [
            '@context' => 'https://schema.org',
            '@graph'   => $graph,
        ]
    );
}

private static function album_schema(
    int $release_id,
    array $artist,
    bool $full = false
): array {
    $release = get_post($release_id);

    if (
        ! $release instanceof WP_Post
        || $release->post_type !== PostTypes::RELEASE
    ) {
        return [];
    }

    $url = get_permalink($release_id);

    if (! is_string($url) || $url === '') {
        return [];
    }

    $image        = self::artwork_url($release_id);
    $date         = self::release_date($release_id);
    $description  = $full
        ? self::release_description(
            $release_id,
            $artist
        )
        : '';
    $release_type = (string) get_post_meta(
        $release_id,
        '_sv_release_type',
        true
    );
    $genre = (string) get_post_meta(
        $release_id,
        '_sv_genre',
        true
    );

    $same_as = self::same_as_links(
        [
            'external' => (string) get_post_meta(
                $release_id,
                '_sv_external_url',
                true
            ),
            'spotify' => (string) get_post_meta(
                $release_id,
                '_sv_spotify_url',
                true
            ),
            'appleMusic' => (string) get_post_meta(
                $release_id,
                '_sv_apple_music_url',
                true
            ),
            'youtube' => (string) get_post_meta(
                $release_id,
                '_sv_youtube_url',
                true
            ),
            'bandcamp' => (string) get_post_meta(
                $release_id,
                '_sv_bandcamp_url',
                true
            ),
        ]
    );

    return self::clean_schema(
        [
            '@type'            => 'MusicAlbum',
            '@id'              => self::album_schema_id(
                $release_id
            ),
            'name' => self::single_line(
                (string) get_the_title($release_id)
            ),
            'url'              => $url,
            'image'            => $image,
            'datePublished'    => $date,
            'description'      => $description,
            'genre'            => self::single_line($genre),
            'albumReleaseType' => self::album_release_type(
                $release_type
            ),
            'byArtist'         => self::schema_reference(
                (string) ($artist['schemaId'] ?? '')
            ),
            'sameAs'           => $same_as,
        ]
    );
}

private static function track_document_schema(
    int $track_id,
    array $artist
): array {
    $recording = self::track_schema(
        $track_id,
        $artist,
        true
    );

    if (! $recording) {
        return [];
    }

    $graph = [];

    $artist_node = self::artist_schema($artist);

    if ($artist_node) {
        $graph[] = $artist_node;
    }

    $release_id = Rewrite::get_track_release_id(
        $track_id
    );

    if ($release_id > 0) {
        $album = self::album_schema(
            $release_id,
            $artist,
            false
        );

        if ($album) {
            $graph[] = $album;
        }
    }

    $graph[] = $recording;

    return self::clean_schema(
        [
            '@context' => 'https://schema.org',
            '@graph'   => $graph,
        ]
    );
}

private static function track_schema(
    int $track_id,
    array $artist,
    bool $full = true
): array {
    $track = get_post($track_id);

    if (
        ! $track instanceof WP_Post
        || $track->post_type !== PostTypes::TRACK
    ) {
        return [];
    }

    $release_id = Rewrite::get_track_release_id(
        $track_id
    );

    $data = PlayerData::get_track_data($track_id);
    $url  = get_permalink($track_id);

    if (! is_string($url) || $url === '') {
        return [];
    }

    $duration_seconds = isset(
        $data['durationSeconds']
    )
        ? (int) $data['durationSeconds']
        : 0;

    if ($duration_seconds <= 0) {
        $duration_seconds = self::duration_string_to_seconds(
            (string) ($data['duration'] ?? '')
        );
    }

    $schema = [
        '@type'    => 'MusicRecording',
        '@id'      => self::recording_schema_id(
            $track_id
        ),
        'name'     => self::single_line(
            (string) get_the_title($track_id)
        ),
        'url'      => $url,
        'image'    => self::artwork_url(
            $track_id,
            $release_id
        ),
        'duration' => self::seconds_to_iso_duration(
            $duration_seconds
        ),
        'byArtist' => self::schema_reference(
            (string) ($artist['schemaId'] ?? '')
        ),
        'sameAs' => self::track_same_as_links(
            $track_id
        ),
    ];

    if ($full) {
        $schema['description'] = self::post_description(
            $track_id
        );

        if (! empty($data['audioUrl'])) {
            $schema['audio'] = esc_url_raw(
                (string) $data['audioUrl']
            );
        }
    }

    if ($release_id > 0) {
        $schema['inAlbum'] = self::schema_reference(
            self::album_schema_id($release_id)
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

    private static function artist_schema(array $artist): array
    {
        $entity_type = (string) ($artist['entityType'] ?? 'MusicGroup');

        if (! in_array($entity_type, ['MusicGroup', 'Person'], true)) {
            $entity_type = 'MusicGroup';
        }

$url       = esc_url_raw((string) ($artist['url'] ?? ''));
$schema_id = trim((string) ($artist['schemaId'] ?? ''));

        return self::clean_schema(
            [
                '@type'       => $entity_type,
                '@id'         => $schema_id,
                'name'        => self::single_line((string) ($artist['name'] ?? '')),
                'url'         => $url,
                'image'       => esc_url_raw((string) ($artist['image'] ?? '')),
                'description' => self::single_line((string) ($artist['description'] ?? '')),
                'sameAs' => is_array($artist['sameAs'] ?? null)
                    ? array_values($artist['sameAs'])
                    : [],
            ]
        );
    }

    private static function album_schema_id(
    int $release_id
): string {
    $url = get_permalink($release_id);

    if (! is_string($url) || $url === '') {
        return '';
    }

    return trailingslashit(
        esc_url_raw($url)
    ) . '#sv-album';
}

private static function recording_schema_id(
    int $track_id
): string {
    $url = get_permalink($track_id);

    if (! is_string($url) || $url === '') {
        return '';
    }

    return trailingslashit(
        esc_url_raw($url)
    ) . '#sv-recording';
}

private static function schema_reference(
    string $schema_id
): array {
    $schema_id = trim($schema_id);

    if ($schema_id === '') {
        return [];
    }

    return [
        '@id' => $schema_id,
    ];
}

private static function archive_title(array $settings): string
{
    $releases = ArchiveQuery::posts($settings);

    $release_ids = array_map(
        static fn (WP_Post $release): int => (int) $release->ID,
        $releases
    );

    $default_artist = ArtistResolver::default_artist(
        $settings
    );

    $artists = ArtistResolver::for_releases(
        $release_ids,
        $settings
    );

    if (! $artists) {
        $artists = [
            ArtistResolver::identity_key($default_artist)
                => $default_artist,
        ];
    }

    if (count($artists) > 1) {
        $site_name = trim(
            (string) get_bloginfo('name')
        );

        if ($site_name === '') {
            $site_name = (string) (
                $default_artist['name'] ?? ''
            );
        }

        return sprintf(
            /* translators: %s: website or catalog name. */
            __('Music from %s', 'slim-volume'),
            $site_name
        );
    }

    $artist = reset($artists);

    if (! is_array($artist)) {
        $artist = $default_artist;
    }

    $artist_name = self::single_line(
        (string) (
            $artist['name']
            ?? $default_artist['name']
            ?? ''
        )
    );

    if ($artist_name === '') {
        return __('Music', 'slim-volume');
    }

    return sprintf(
        /* translators: %s: artist/project name. */
        __('Music by %s', 'slim-volume'),
        $artist_name
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

    private static function release_description(int $release_id, array $artist): string
    {
        $description = self::post_description($release_id);

        if ($description !== '') {
            return $description;
        }

        $title       = self::single_line((string) get_the_title($release_id));
        $artist_name = self::single_line((string) ($artist['name'] ?? ''));

        if ($artist_name !== '') {
            return sprintf(
                /* translators: 1: release title, 2: artist or project name. */
                __(
                    'Listen to %1$s by %2$s. Explore the release, tracklist, lyrics, credits, artwork, and streaming links.',
                    'slim-volume'
                ),
                $title,
                $artist_name
            );
        }

        return sprintf(
            /* translators: %s: release title. */
            __(
                'Listen to %s. Explore the release, tracklist, lyrics, credits, artwork, and streaming links.',
                'slim-volume'
            ),
            $title
        );
    }

    /**
 * Return only links explicitly assigned to this recording.
 *
 * PlayerData intentionally allows track destinations to inherit release-level
 * links for frontend convenience. Schema.org sameAs has stricter semantics:
 * it should identify alternate representations of this recording itself.
 */
private static function track_same_as_links(
    int $track_id
): array {
    return self::same_as_links(
        [
            'external' => (string) get_post_meta(
                $track_id,
                '_sv_external_url',
                true
            ),
            'spotify' => (string) get_post_meta(
                $track_id,
                '_sv_spotify_url',
                true
            ),
            'appleMusic' => (string) get_post_meta(
                $track_id,
                '_sv_apple_music_url',
                true
            ),
            'youtube' => (string) get_post_meta(
                $track_id,
                '_sv_youtube_url',
                true
            ),
            'bandcamp' => (string) get_post_meta(
                $track_id,
                '_sv_bandcamp_url',
                true
            ),
        ]
    );
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
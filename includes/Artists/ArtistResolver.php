<?php

declare(strict_types=1);

namespace SlimVolume\Artists;

use SlimVolume\Admin\Settings;
use SlimVolume\PostTypes;
use SlimVolume\Rewrite;
use WP_Post;
use WP_Term;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Resolves a normalized artist identity without forcing project assignments.
 *
 * Resolution order:
 *  1. Artist/project assigned to the release.
 *  2. Existing global artist settings.
 *
 * Tracks inherit their release's resolved identity. Templates and SEO should
 * eventually consume this resolver instead of reading artist settings directly.
 */
final class ArtistResolver
{
    /**
     * @return array{
     *     source:string,
     *     termId:int,
     *     name:string,
     *     entityType:string,
     *     url:string,
     *     image:string,
     *     description:string,
     *     sameAs:list<string>,
     *     schemaId:string
     * }
     */
    public static function default_artist(?array $settings = null): array
    {
        $settings = is_array($settings) ? $settings : Settings::get_settings();

        $name = isset($settings['seo_artist_name'])
            ? trim((string) $settings['seo_artist_name'])
            : '';

        if ($name === '') {
            $name = (string) get_bloginfo('name');
        }

        $url = isset($settings['seo_artist_url'])
            ? esc_url_raw((string) $settings['seo_artist_url'])
            : '';

        if ($url === '') {
            $url = home_url('/');
        }

        $image = isset($settings['seo_default_image'])
            ? esc_url_raw((string) $settings['seo_default_image'])
            : '';

        $entity_type = (string) apply_filters(
            'slim_volume_default_artist_schema_type',
            'MusicGroup',
            $settings
        );

        if (! in_array($entity_type, ['MusicGroup', 'Person'], true)) {
            $entity_type = 'MusicGroup';
        }

        $artist = [
            'source'      => 'default',
            'termId'      => 0,
            'name'        => $name,
            'entityType'  => $entity_type,
            'url'         => $url,
            'image'       => $image,
            'description' => '',
            'sameAs' => (string) (
                $settings['seo_artist_same_as'] ?? ''
            ),
            'schemaId'    => trailingslashit($url) . '#sv-artist',
        ];

        /**
         * Filter Slim Volume's normalized fallback artist identity.
         *
         * @param array $artist   Normalized artist identity.
         * @param array $settings Current Slim Volume settings.
         */
$artist = (array) apply_filters(
    'slim_volume_default_artist',
    $artist,
    $settings
);

return self::normalize_identity($artist);
    }

    /**
     * @return array{
     *     source:string,
     *     termId:int,
     *     name:string,
     *     entityType:string,
     *     url:string,
     *     image:string,
     *     description:string,
     *     sameAs:list<string>,
     *     schemaId:string
     * }
     */
    public static function for_release(int $release_id, ?array $settings = null): array
    {
        $fallback = self::default_artist($settings);

        // Keep existing assignments stored when the optional project feature is
        // disabled, but resolve public/template/SEO identity through the global
        // fallback until the feature is enabled again.
        if (! ProjectTaxonomy::is_enabled()) {
            return $fallback;
        }

        $term = ProjectTaxonomy::get_release_project_term($release_id);

        if (! $term instanceof WP_Term) {
            return $fallback;
        }

        $artist = self::from_project_term($term, $fallback);

        /**
         * Filter the resolved artist/project for a release.
         *
         * @param array $artist     Normalized artist identity.
         * @param int   $release_id Release post ID.
         * @param array $fallback   Global/default artist identity.
         */
$artist = (array) apply_filters(
    'slim_volume_release_artist',
    $artist,
    $release_id,
    $fallback
);

return self::normalize_identity($artist);
    }

    /**
     * Tracks inherit the identity resolved for their parent release.
     *
     * @return array{
     *     source:string,
     *     termId:int,
     *     name:string,
     *     entityType:string,
     *     url:string,
     *     image:string,
     *     description:string,
     *     sameAs:list<string>,
     *     schemaId:string
     * }
     */
    public static function for_track(int $track_id, ?array $settings = null): array
    {
        $track = get_post($track_id);

        if (! $track instanceof WP_Post || $track->post_type !== PostTypes::TRACK) {
            return self::default_artist($settings);
        }

        $release_id = Rewrite::get_track_release_id($track_id);

        if ($release_id <= 0) {
            return self::default_artist($settings);
        }

        $artist = self::for_release($release_id, $settings);

        /**
         * Filter the inherited artist/project for a track.
         *
         * @param array $artist     Normalized artist identity.
         * @param int   $track_id   Track post ID.
         * @param int   $release_id Parent release post ID.
         */
$artist = (array) apply_filters(
    'slim_volume_track_artist',
    $artist,
    $track_id,
    $release_id
);

return self::normalize_identity($artist);
    }

    /**
     * Resolve unique identities for a release collection.
     *
     * @param array<int,int> $release_ids
     * @return array<string,array>
     */
    public static function for_releases(array $release_ids, ?array $settings = null): array
    {
        $artists = [];

        foreach (array_unique(array_map('absint', $release_ids)) as $release_id) {
            if ($release_id <= 0) {
                continue;
            }

            $artist = self::for_release($release_id, $settings);
            $key    = self::identity_key($artist);

            $artists[$key] = $artist;
        }

        return $artists;
    }

    public static function archive_is_mixed(array $release_ids, ?array $settings = null): bool
    {
        return count(self::for_releases($release_ids, $settings)) > 1;
    }

public static function identity_key(array $artist): string
{
    $schema_id = trim((string) ($artist['schemaId'] ?? ''));

    if ($schema_id !== '') {
        return strtolower($schema_id);
    }

    $term_id = absint($artist['termId'] ?? 0);

    if ($term_id > 0) {
        return 'project:' . $term_id;
    }

    return 'default';
}

/**
 * Normalize the identity fields Slim Volume owns.
 *
 * Artist/project names, images, descriptions, and URLs may be filtered, but
 * entity type and schema identity must remain deterministic and must never be
 * inferred from the display name.
 */
private static function normalize_identity(array $artist): array
{
    $term_id = absint($artist['termId'] ?? 0);
    $source  = $term_id > 0 ? 'project' : 'default';

    $entity_type = (string) ($artist['entityType'] ?? 'MusicGroup');

    if (! in_array($entity_type, ['MusicGroup', 'Person'], true)) {
        $entity_type = 'MusicGroup';
    }

    $url = esc_url_raw((string) ($artist['url'] ?? ''));

    if ($source === 'default' && $url === '') {
        $url = home_url('/');
    }

$artist['source']     = $source;
$artist['termId']     = $term_id;
$artist['entityType'] = $entity_type;
$artist['url']        = $url;
$artist['schemaId']   = self::schema_id(
    $source,
    $term_id,
    $url
);

$same_as = self::normalize_same_as(
    $artist['sameAs'] ?? []
);

$artist['sameAs'] = $same_as;

/**
 * Filter official external identities for a resolved artist/project.
 *
 * @param string[] $same_as Normalized identity URLs.
 * @param array    $artist  Resolved Slim Volume artist/project identity.
 */
$same_as = apply_filters(
    'slim_volume_artist_same_as',
    $same_as,
    $artist
);

$artist['sameAs'] = self::normalize_same_as(
    $same_as
);

return $artist;
}

/**
 * Normalize official external artist/project identity URLs.
 *
 * @param mixed $value
 * @return string[]
 */
private static function normalize_same_as(
    $value
): array {
    if (is_string($value)) {
        $value = preg_split(
            '/\R+/',
            $value
        );
    }

    if (! is_array($value)) {
        return [];
    }

    $urls = [];

    foreach ($value as $candidate) {
        if (! is_scalar($candidate)) {
            continue;
        }

        $url = esc_url_raw(
            trim((string) $candidate),
            ['http', 'https']
        );

        if ($url === '') {
            continue;
        }

        $urls[] = $url;
    }

    return array_values(
        array_unique($urls)
    );
}

/**
 * Build Slim Volume's stable artist/project schema identifier.
 */
private static function schema_id(
    string $source,
    int $term_id,
    string $url
): string {
    if ($source === 'project' && $term_id > 0) {
        if ($url !== '') {
            return trailingslashit($url) . '#sv-artist';
        }

        return trailingslashit(home_url('/'))
            . '#sv-artist-'
            . $term_id;
    }

    $base_url = $url !== ''
        ? $url
        : home_url('/');

    return trailingslashit($base_url) . '#sv-artist';
}

    /**
     * @return array{
     *     source:string,
     *     termId:int,
     *     name:string,
     *     entityType:string,
     *     url:string,
     *     image:string,
     *     description:string,
     *     sameAs:list<string>,
     *     schemaId:string
     * }
     */
    private static function from_project_term(WP_Term $term, array $fallback): array
    {
        $entity_type_key = ProjectTaxonomy::sanitize_entity_type(
            get_term_meta($term->term_id, ProjectTaxonomy::META_ENTITY_TYPE, true)
        );

        $entity_type = $entity_type_key === ProjectTaxonomy::ENTITY_PERSON
            ? 'Person'
            : 'MusicGroup';

        $url = esc_url_raw(
            (string) get_term_meta($term->term_id, ProjectTaxonomy::META_URL, true)
        );

        $image_id = absint(
            get_term_meta($term->term_id, ProjectTaxonomy::META_IMAGE_ID, true)
        );

        $image = $image_id > 0
            ? (string) wp_get_attachment_image_url($image_id, 'full')
            : '';

        return [
            'source'      => 'project',
            'termId'      => (int) $term->term_id,
            'name'        => $term->name !== '' ? $term->name : $fallback['name'],
            'entityType'  => $entity_type,
            'url'         => $url,
            'image'       => $image,
            'description' => trim(wp_strip_all_tags((string) $term->description)),
            'sameAs' => (string) get_term_meta(
                $term->term_id,
                ProjectTaxonomy::META_SAME_AS,
                true
            ),
            'schemaId'    => self::schema_id(
                'project',
                (int) $term->term_id,
                $url
            ),        ];
    }
}

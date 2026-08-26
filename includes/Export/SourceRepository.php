<?php

declare(strict_types=1);

namespace SlimVolume\Export;

use SlimVolume\Admin\Settings;
use SlimVolume\Artists\ProjectTaxonomy;
use SlimVolume\PostTypes;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Reads only the source records that are eligible for the portable export.
 *
 * This repository deliberately exposes explicit/canonical stored source state.
 * Portable mapping belongs in dedicated mapper classes.
 */
final class SourceRepository
{
    private \wpdb $db;

    public function __construct(?\wpdb $db = null)
    {
        if ($db instanceof \wpdb) {
            $this->db = $db;
            return;
        }

        global $wpdb;

        if (! $wpdb instanceof \wpdb) {
            throw new ExportException(
                'WordPress database access is unavailable for discography export.'
            );
        }

        $this->db = $wpdb;
    }

    /**
     * Return every Slim Volume Artist / Project term, including unused terms.
     *
     * Direct inventory avoids runtime query filters changing export
     * eligibility.
     *
     * @return array<int,int>
     */
    public function get_artist_term_ids(): array
    {
        $query = $this->db->prepare(
            "SELECT t.term_id
            FROM {$this->db->terms} AS t
            INNER JOIN {$this->db->term_taxonomy} AS tt
                ON tt.term_id = t.term_id
            WHERE tt.taxonomy = %s
            ORDER BY t.term_id ASC",
            ProjectTaxonomy::TAXONOMY
        );

        if (! is_string($query) || $query === '') {
            throw new ExportException(
                'Slim Volume could not prepare the Artist / Project export inventory.'
            );
        }

        $term_ids = $this->db->get_col($query);

        if (! is_array($term_ids)) {
            throw new ExportException(
                'Slim Volume could not inventory Artists / Projects for export.'
            );
        }

        return self::normalize_ids($term_ids);
    }

    /**
     * Read only the Slim Volume-owned settings that have portable catalog
     * meaning in schema format version 1.
     *
     * Reading the stored option directly avoids runtime artist fallbacks such
     * as the WordPress site title or home URL.
     *
     * @return array{
     *   fallbackArtistName:string,
     *   fallbackArtistWebsite:string,
     *   fallbackArtistOfficialProfiles:string,
     *   description:string,
     *   defaultArtworkUrl:string
     * }
     */
    public function get_catalog_source(): array
    {
        $query = $this->db->prepare(
            "SELECT option_value
            FROM {$this->db->options}
            WHERE option_name = %s
            LIMIT 1",
            Settings::OPTION_NAME
        );

        if (! is_string($query) || $query === '') {
            throw new ExportException(
                'Slim Volume could not prepare the catalog export source query.'
            );
        }

        $row = $this->db->get_row($query, ARRAY_A);

        $settings = [];

        if (is_array($row) && array_key_exists('option_value', $row)) {
            $stored = maybe_unserialize($row['option_value']);

            if (is_array($stored)) {
                $settings = $stored;
            }
        }

        return [
            'fallbackArtistName' => self::string_value(
                $settings['seo_artist_name'] ?? ''
            ),
            'fallbackArtistWebsite' => self::string_value(
                $settings['seo_artist_url'] ?? ''
            ),
            'fallbackArtistOfficialProfiles' => self::string_value(
                $settings['seo_artist_same_as'] ?? ''
            ),
            'description' => self::string_value(
                $settings['seo_archive_description'] ?? ''
            ),
            'defaultArtworkUrl' => self::string_value(
                $settings['seo_default_image'] ?? ''
            ),
        ];
    }

    /**
     * Return the stored source fields for one Artist / Project.
     *
     * The term row is read directly so the exported description is the stored
     * taxonomy description rather than a presentation-filtered value.
     *
     * @return array{
     *   termId:int,
     *   name:string,
     *   slug:string,
     *   description:string,
     *   entityTypeExists:bool,
     *   entityType:string,
     *   website:string,
     *   officialProfiles:string,
     *   imageId:int
     * }
     */
    public function get_artist_source(int $term_id): array
    {
        if ($term_id <= 0) {
            throw new ExportException(
                'Slim Volume received an invalid Artist / Project source ID.'
            );
        }

        $query = $this->db->prepare(
            "SELECT
                t.term_id,
                t.name,
                t.slug,
                tt.description
            FROM {$this->db->terms} AS t
            INNER JOIN {$this->db->term_taxonomy} AS tt
                ON tt.term_id = t.term_id
            WHERE t.term_id = %d
              AND tt.taxonomy = %s
            ORDER BY tt.term_taxonomy_id ASC
            LIMIT 1",
            $term_id,
            ProjectTaxonomy::TAXONOMY
        );

        if (! is_string($query) || $query === '') {
            throw new ExportException(
                'Slim Volume could not prepare an Artist / Project export source query.'
            );
        }

        $row = $this->db->get_row($query, ARRAY_A);

        if (! is_array($row)) {
            throw new ExportException(
                'An inventoried Slim Volume Artist / Project could not be read for export.'
            );
        }

        $entity_type = $this->get_raw_term_meta(
            $term_id,
            ProjectTaxonomy::META_ENTITY_TYPE
        );
        $website = $this->get_raw_term_meta(
            $term_id,
            ProjectTaxonomy::META_URL
        );
        $official_profiles = $this->get_raw_term_meta(
            $term_id,
            ProjectTaxonomy::META_SAME_AS
        );
        $image_id = $this->get_raw_term_meta(
            $term_id,
            ProjectTaxonomy::META_IMAGE_ID
        );

        return [
            'termId' => absint($row['term_id'] ?? 0),
            'name' => self::string_value($row['name'] ?? ''),
            'slug' => self::string_value($row['slug'] ?? ''),
            'description' => self::string_value(
                $row['description'] ?? ''
            ),
            'entityTypeExists' => $entity_type['exists'],
            'entityType' => self::string_value($entity_type['value']),
            'website' => self::string_value($website['value']),
            'officialProfiles' => self::string_value(
                $official_profiles['value']
            ),
            'imageId' => absint($image_id['value']),
        ];
    }

    /**
     * Return the explicit/core stored source fields for one release.
     *
     * Runtime fallback values are intentionally absent. The legacy release
     * genre source remains unverified, so the v1 core field is currently
     * supplied with its neutral value until that source audit is completed.
     *
     * @return array{
     *   postId:int,
     *   title:string,
     *   slug:string,
     *   status:string,
     *   content:string,
     *   excerpt:string,
     *   releaseDate:string,
     *   releaseType:string,
     *   label:string,
     *   catalogNumber:string,
     *   genre:string,
     *   featuredRaw:mixed,
     *   artworkId:int,
     *   credits:string,
     *   primaryUrl:string,
     *   primaryLabel:string,
     *   spotify:string,
     *   appleMusic:string,
     *   youtube:string,
     *   bandcamp:string,
     *   purchase:string,
     *   artistTermIds:array<int,int>
     * }
     */
    public function get_release_source(int $post_id): array
    {
        if ($post_id <= 0) {
            throw new ExportException(
                'Slim Volume received an invalid release source ID.'
            );
        }

        $query = $this->db->prepare(
            "SELECT
                ID,
                post_title,
                post_name,
                post_status,
                post_content,
                post_excerpt
            FROM {$this->db->posts}
            WHERE ID = %d
              AND post_type = %s
            LIMIT 1",
            $post_id,
            PostTypes::RELEASE
        );

        if (! is_string($query) || $query === '') {
            throw new ExportException(
                'Slim Volume could not prepare a release export source query.'
            );
        }

        $row = $this->db->get_row($query, ARRAY_A);

        if (! is_array($row)) {
            throw new ExportException(
                'An inventoried Slim Volume release could not be read for export.'
            );
        }

        $release_date = $this->get_raw_post_meta(
            $post_id,
            '_sv_release_date'
        );
        $release_type = $this->get_raw_post_meta(
            $post_id,
            '_sv_release_type'
        );
        $label = $this->get_raw_post_meta(
            $post_id,
            '_sv_label'
        );
        $catalog_number = $this->get_raw_post_meta(
            $post_id,
            '_sv_catalog_number'
        );
        $featured = $this->get_raw_post_meta(
            $post_id,
            '_sv_featured_release'
        );
        $artwork_id = $this->get_raw_post_meta(
            $post_id,
            '_thumbnail_id'
        );
        $credits = $this->get_raw_post_meta(
            $post_id,
            '_sv_release_credits'
        );
        $primary_url = $this->get_raw_post_meta(
            $post_id,
            '_sv_external_url'
        );
        $primary_label = $this->get_raw_post_meta(
            $post_id,
            '_sv_external_label'
        );
        $spotify = $this->get_raw_post_meta(
            $post_id,
            '_sv_spotify_url'
        );
        $apple_music = $this->get_raw_post_meta(
            $post_id,
            '_sv_apple_music_url'
        );
        $youtube = $this->get_raw_post_meta(
            $post_id,
            '_sv_youtube_url'
        );
        $bandcamp = $this->get_raw_post_meta(
            $post_id,
            '_sv_bandcamp_url'
        );
        $purchase = $this->get_raw_post_meta(
            $post_id,
            '_sv_purchase_url'
        );

        return [
            'postId' => absint($row['ID'] ?? 0),
            'title' => self::string_value($row['post_title'] ?? ''),
            'slug' => self::string_value($row['post_name'] ?? ''),
            'status' => self::string_value($row['post_status'] ?? ''),
            'content' => self::string_value($row['post_content'] ?? ''),
            'excerpt' => self::string_value($row['post_excerpt'] ?? ''),
            'releaseDate' => self::string_value($release_date['value']),
            'releaseType' => self::string_value($release_type['value']),
            'label' => self::string_value($label['value']),
            'catalogNumber' => self::string_value(
                $catalog_number['value']
            ),
            'genre' => '',
            'featuredRaw' => $featured['value'],
            'artworkId' => absint($artwork_id['value']),
            'credits' => self::string_value($credits['value']),
            'primaryUrl' => self::string_value($primary_url['value']),
            'primaryLabel' => self::string_value(
                $primary_label['value']
            ),
            'spotify' => self::string_value($spotify['value']),
            'appleMusic' => self::string_value($apple_music['value']),
            'youtube' => self::string_value($youtube['value']),
            'bandcamp' => self::string_value($bandcamp['value']),
            'purchase' => self::string_value($purchase['value']),
            'artistTermIds' => $this->get_release_artist_term_ids(
                $post_id
            ),
        ];
    }

    /**
     * Return every stored Artist / Project taxonomy assignment for a release,
     * ordered according to Slim Volume's canonical lowest-term-ID rule.
     *
     * @return array<int,int>
     */
    public function get_release_artist_term_ids(int $post_id): array
    {
        if ($post_id <= 0) {
            return [];
        }

        $query = $this->db->prepare(
            "SELECT tt.term_id
            FROM {$this->db->term_relationships} AS tr
            INNER JOIN {$this->db->term_taxonomy} AS tt
                ON tt.term_taxonomy_id = tr.term_taxonomy_id
            WHERE tr.object_id = %d
              AND tt.taxonomy = %s
            ORDER BY tt.term_id ASC",
            $post_id,
            ProjectTaxonomy::TAXONOMY
        );

        if (! is_string($query) || $query === '') {
            throw new ExportException(
                'Slim Volume could not prepare a release Artist / Project relationship query.'
            );
        }

        $term_ids = $this->db->get_col($query);

        if (! is_array($term_ids)) {
            throw new ExportException(
                'Slim Volume could not read a release Artist / Project relationship for export.'
            );
        }

        return self::normalize_ids($term_ids);
    }

    /**
     * Return the explicit/core stored source fields for one track.
     *
     * Runtime fallback values are intentionally absent. The historical
     * track-level external destination remains unverified, so links.external
     * is supplied with its neutral value until that source audit is complete.
     *
     * @return array{
     *   postId:int,
     *   title:string,
     *   slug:string,
     *   status:string,
     *   content:string,
     *   excerpt:string,
     *   discNumber:int,
     *   trackNumber:int,
     *   durationSeconds:int,
     *   artworkId:int,
     *   audioAttachmentId:int,
     *   audioExternalUrl:string,
     *   downloadEnabledRaw:mixed,
     *   downloadAttachmentId:int,
     *   downloadExternalUrl:string,
     *   externalUrl:string,
     *   spotify:string,
     *   appleMusic:string,
     *   youtube:string,
     *   bandcamp:string,
     *   purchase:string,
     *   lyrics:string,
     *   credits:string
     * }
     */
    public function get_track_source(int $post_id): array
    {
        if ($post_id <= 0) {
            throw new ExportException(
                'Slim Volume received an invalid track source ID.'
            );
        }

        $query = $this->db->prepare(
            "SELECT
                ID,
                post_title,
                post_name,
                post_status,
                post_content,
                post_excerpt
            FROM {$this->db->posts}
            WHERE ID = %d
              AND post_type = %s
            LIMIT 1",
            $post_id,
            PostTypes::TRACK
        );

        if (! is_string($query) || $query === '') {
            throw new ExportException(
                'Slim Volume could not prepare a track export source query.'
            );
        }

        $row = $this->db->get_row($query, ARRAY_A);

        if (! is_array($row)) {
            throw new ExportException(
                'An inventoried Slim Volume track could not be read for export.'
            );
        }

        $disc_number = $this->get_raw_post_meta(
            $post_id,
            '_sv_disc_number'
        );
        $track_number = $this->get_raw_post_meta(
            $post_id,
            '_sv_track_number'
        );
        $duration_seconds = $this->get_raw_post_meta(
            $post_id,
            '_sv_duration_seconds'
        );
        $artwork_id = $this->get_raw_post_meta(
            $post_id,
            '_thumbnail_id'
        );
        $audio_attachment_id = $this->get_raw_post_meta(
            $post_id,
            '_sv_audio_attachment_id'
        );
        $audio_url = $this->get_raw_post_meta(
            $post_id,
            '_sv_audio_url'
        );
        $download_enabled = $this->get_raw_post_meta(
            $post_id,
            '_sv_can_download'
        );
        $download_attachment_id = $this->get_raw_post_meta(
            $post_id,
            '_sv_download_attachment_id'
        );
        $download_url = $this->get_raw_post_meta(
            $post_id,
            '_sv_download_url'
        );
        $spotify = $this->get_raw_post_meta(
            $post_id,
            '_sv_spotify_url'
        );
        $apple_music = $this->get_raw_post_meta(
            $post_id,
            '_sv_apple_music_url'
        );
        $youtube = $this->get_raw_post_meta(
            $post_id,
            '_sv_youtube_url'
        );
        $bandcamp = $this->get_raw_post_meta(
            $post_id,
            '_sv_bandcamp_url'
        );
        $purchase = $this->get_raw_post_meta(
            $post_id,
            '_sv_purchase_url'
        );
        $lyrics = $this->get_raw_post_meta(
            $post_id,
            '_sv_lyrics'
        );
        $credits = $this->get_raw_post_meta(
            $post_id,
            '_sv_track_credits'
        );

        return [
            'postId' => absint($row['ID'] ?? 0),
            'title' => self::string_value($row['post_title'] ?? ''),
            'slug' => self::string_value($row['post_name'] ?? ''),
            'status' => self::string_value($row['post_status'] ?? ''),
            'content' => self::string_value($row['post_content'] ?? ''),
            'excerpt' => self::string_value($row['post_excerpt'] ?? ''),
            'discNumber' => max(
                0,
                (int) $disc_number['value']
            ),
            'trackNumber' => max(
                0,
                (int) $track_number['value']
            ),
            'durationSeconds' => max(
                0,
                (int) $duration_seconds['value']
            ),
            'artworkId' => absint($artwork_id['value']),
            'audioAttachmentId' => absint(
                $audio_attachment_id['value']
            ),
            'audioExternalUrl' => self::string_value(
                $audio_url['value']
            ),
            'downloadEnabledRaw' => $download_enabled['value'],
            'downloadAttachmentId' => absint(
                $download_attachment_id['value']
            ),
            'downloadExternalUrl' => self::string_value(
                $download_url['value']
            ),
            'externalUrl' => '',
            'spotify' => self::string_value($spotify['value']),
            'appleMusic' => self::string_value($apple_music['value']),
            'youtube' => self::string_value($youtube['value']),
            'bandcamp' => self::string_value($bandcamp['value']),
            'purchase' => self::string_value($purchase['value']),
            'lyrics' => self::string_value($lyrics['value']),
            'credits' => self::string_value($credits['value']),
        ];
    }

    /**
     * Return stored descriptive source data for a WordPress attachment.
     *
     * @return array{
     *   title:string,
     *   mimeType:string,
     *   attachedFile:string,
     *   alt:string,
     *   guid:string
     * }|null
     */
    public function get_attachment_source(int $attachment_id): ?array
    {
        if ($attachment_id <= 0) {
            return null;
        }

        $query = $this->db->prepare(
            "SELECT post_title, post_mime_type, guid
            FROM {$this->db->posts}
            WHERE ID = %d
              AND post_type = %s
            LIMIT 1",
            $attachment_id,
            'attachment'
        );

        if (! is_string($query) || $query === '') {
            throw new ExportException(
                'Slim Volume could not prepare a media-reference export source query.'
            );
        }

        $row = $this->db->get_row($query, ARRAY_A);

        if (! is_array($row)) {
            return null;
        }

        $attached_file = $this->get_raw_post_meta(
            $attachment_id,
            '_wp_attached_file'
        );
        $alt = $this->get_raw_post_meta(
            $attachment_id,
            '_wp_attachment_image_alt'
        );

        return [
            'title' => self::string_value($row['post_title'] ?? ''),
            'mimeType' => self::string_value(
                $row['post_mime_type'] ?? ''
            ),
            'attachedFile' => self::string_value(
                $attached_file['value']
            ),
            'alt' => self::string_value($alt['value']),
            'guid' => self::string_value($row['guid'] ?? ''),
        ];
    }

    /**
     * @return array<int,int>
     */
    public function get_release_ids(): array
    {
        return $this->get_exportable_post_ids(PostTypes::RELEASE);
    }

    /**
     * @return array<int,int>
     */
    public function get_track_ids(): array
    {
        return $this->get_exportable_post_ids(PostTypes::TRACK);
    }

    /**
     * Inventory every post belonging to the requested Slim Volume post type,
     * excluding only the lifecycle states explicitly excluded by format v1.
     *
     * Direct inventory is intentional here. WP_Query's "any" status does not
     * guarantee preservation of every custom workflow status.
     *
     * @return array<int,int>
     */
    private function get_exportable_post_ids(string $post_type): array
    {
        $query = $this->db->prepare(
            "SELECT ID
            FROM {$this->db->posts}
            WHERE post_type = %s
              AND post_status NOT IN (%s, %s)
            ORDER BY ID ASC",
            $post_type,
            'trash',
            'auto-draft'
        );

        if (! is_string($query) || $query === '') {
            throw new ExportException(
                'Slim Volume could not prepare the discography export inventory.'
            );
        }

        $post_ids = $this->db->get_col($query);

        if (! is_array($post_ids)) {
            throw new ExportException(
                'Slim Volume could not inventory discography records for export.'
            );
        }

        return self::normalize_ids($post_ids);
    }

    /**
     * @return array{exists:bool,value:mixed}
     */
    private function get_raw_term_meta(
        int $term_id,
        string $meta_key
    ): array {
        return $this->get_raw_meta(
            $this->db->termmeta,
            'term_id',
            'meta_id',
            $term_id,
            $meta_key
        );
    }

    /**
     * @return array{exists:bool,value:mixed}
     */
    private function get_raw_post_meta(
        int $post_id,
        string $meta_key
    ): array {
        return $this->get_raw_meta(
            $this->db->postmeta,
            'post_id',
            'meta_id',
            $post_id,
            $meta_key
        );
    }

    /**
     * Read one stored metadata value without applying registered defaults or
     * runtime metadata filters.
     *
     * @return array{exists:bool,value:mixed}
     */
    private function get_raw_meta(
        string $table,
        string $object_column,
        string $meta_id_column,
        int $object_id,
        string $meta_key
    ): array {
        $allowed_tables = [
            $this->db->termmeta => ['term_id', 'meta_id'],
            $this->db->postmeta => ['post_id', 'meta_id'],
        ];

        if (
            ! isset($allowed_tables[$table])
            || $allowed_tables[$table] !== [$object_column, $meta_id_column]
        ) {
            throw new ExportException(
                'Slim Volume rejected an unsupported export metadata source.'
            );
        }

        $query = $this->db->prepare(
            "SELECT meta_value
            FROM {$table}
            WHERE {$object_column} = %d
              AND meta_key = %s
            ORDER BY {$meta_id_column} ASC
            LIMIT 1",
            $object_id,
            $meta_key
        );

        if (! is_string($query) || $query === '') {
            throw new ExportException(
                'Slim Volume could not prepare an export metadata source query.'
            );
        }

        $row = $this->db->get_row($query, ARRAY_A);

        if (! is_array($row)) {
            return [
                'exists' => false,
                'value' => '',
            ];
        }

        return [
            'exists' => true,
            'value' => maybe_unserialize($row['meta_value'] ?? ''),
        ];
    }

    /**
     * @param array<int,mixed> $ids
     * @return array<int,int>
     */
    private static function normalize_ids(array $ids): array
    {
        $normalized = [];

        foreach ($ids as $id) {
            $id = absint($id);

            if ($id <= 0) {
                continue;
            }

            $normalized[$id] = $id;
        }

        ksort($normalized, SORT_NUMERIC);

        return array_values($normalized);
    }

    /**
     * Convert a stored scalar to the portable string representation without
     * applying display fallbacks or presentation sanitizers.
     *
     * @param mixed $value
     */
    private static function string_value($value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}

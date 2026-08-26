<?php

declare(strict_types=1);

namespace SlimVolume\Export;

use SlimVolume\Artists\ProjectTaxonomy;
use SlimVolume\PostTypes;
use WP_Error;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Reads only the source records that are eligible for the portable export.
 *
 * This class inventories source objects. Portable mapping belongs in the
 * dedicated mapper classes added in later implementation stages.
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
     * @return array<int,int>
     */
    public function get_artist_term_ids(): array
    {
        $term_ids = get_terms(
            [
                'taxonomy'   => ProjectTaxonomy::TAXONOMY,
                'hide_empty' => false,
                'fields'     => 'ids',
                'orderby'    => 'term_id',
                'order'      => 'ASC',
            ]
        );

        if ($term_ids instanceof WP_Error) {
            throw new ExportException(
                'Slim Volume could not inventory Artists / Projects for export.'
            );
        }

        return self::normalize_ids($term_ids);
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
}

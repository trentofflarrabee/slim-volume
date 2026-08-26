<?php

declare(strict_types=1);

namespace SlimVolume\Export;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Maps Slim Volume release source data into the portable v1 release shape.
 */
final class ReleaseMapper
{
    /**
     * @var array<int,string>
     */
    private const RECOGNIZED_RELEASE_TYPES = [
        'LP',
        'EP',
        'Single',
        'Live',
        'Compilation',
        'Demo',
    ];

    private SourceRepository $source;
    private ReferenceIndex $refs;
    private WarningCollector $warnings;
    private MediaReferenceBuilder $media;
    private EditorialLifecycle $lifecycle;

    public function __construct(
        SourceRepository $source,
        ReferenceIndex $refs,
        WarningCollector $warnings,
        MediaReferenceBuilder $media,
        EditorialLifecycle $lifecycle
    ) {
        $this->source = $source;
        $this->refs = $refs;
        $this->warnings = $warnings;
        $this->media = $media;
        $this->lifecycle = $lifecycle;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function map_all(): array
    {
        $releases = [];

        foreach ($this->refs->release_source_ids() as $post_id) {
            $releases[] = $this->map_one($post_id);
        }

        return $releases;
    }

    /**
     * @return array<string,mixed>
     */
    private function map_one(int $post_id): array
    {
        $ref = $this->refs->release_ref($post_id);

        if ($ref === null) {
            throw new ExportException(
                'Slim Volume could not allocate a release export reference.'
            );
        }

        $source = $this->source->get_release_source($post_id);
        $lifecycle = $this->lifecycle->map(
            $post_id,
            $source['status'],
            $ref
        );

        $release_type = $this->map_release_type(
            $source['releaseType'],
            $ref
        );

        return [
            'ref' => $ref,
            'title' => $source['title'],
            'slug' => $source['slug'],
            'status' => $source['status'],
            'publishedAt' => $lifecycle['publishedAt'],
            'scheduledAt' => $lifecycle['scheduledAt'],
            'content' => $source['content'],
            'contentFormat' => 'wordpress-post-content',
            'excerpt' => $source['excerpt'],
            'excerptFormat' => 'wordpress-post-excerpt',
            'artist' => $this->map_artist_relationship(
                $source['artistTermIds'],
                $ref
            ),
            'releaseDate' => $source['releaseDate'],
            'releaseType' => $release_type,
            'label' => $source['label'],
            'catalogNumber' => $source['catalogNumber'],
            'genre' => $source['genre'],
            'featured' => self::bool_value(
                $source['featuredRaw']
            ),
            'artwork' => $this->media->from_attachment(
                $source['artworkId'],
                $ref
            ),
            'credits' => $source['credits'],
            'links' => [
                'primary' => [
                    'url' => $source['primaryUrl'],
                    'label' => $source['primaryLabel'],
                ],
                'spotify' => $source['spotify'],
                'appleMusic' => $source['appleMusic'],
                'youtube' => $source['youtube'],
                'bandcamp' => $source['bandcamp'],
                'purchase' => $source['purchase'],
            ],
        ];
    }

    /**
     * @param array<int,int> $term_ids
     */
    private function map_artist_relationship(
        array $term_ids,
        string $object_ref
    ): ?string {
        if ($term_ids === []) {
            return null;
        }

        if (count($term_ids) > 1) {
            $this->warnings->add(
                'multiple_release_artists',
                $object_ref,
                'The release has multiple Artist / Project assignments; Slim Volume exported the deterministic canonical primary assignment.'
            );
        }

        $primary_term_id = $term_ids[0];
        $artist_ref = $this->refs->artist_ref($primary_term_id);

        if ($artist_ref !== null) {
            return $artist_ref;
        }

        $this->warnings->add(
            'unresolved_release_artist',
            $object_ref,
            'The explicitly stored release Artist / Project relationship could not be resolved to an exported Artist / Project.'
        );

        return null;
    }

    private function map_release_type(
        string $release_type,
        string $object_ref
    ): string {
        if (
            $release_type !== ''
            && ! in_array(
                $release_type,
                self::RECOGNIZED_RELEASE_TYPES,
                true
            )
        ) {
            $this->warnings->add(
                'unsupported_legacy_value',
                $object_ref,
                'The release type is outside Slim Volume\'s currently recognized set and was preserved as an opaque string.'
            );
        }

        return $release_type;
    }

    /**
     * @param mixed $value
     */
    private static function bool_value($value): bool
    {
        return filter_var(
            $value,
            FILTER_VALIDATE_BOOLEAN
        );
    }
}

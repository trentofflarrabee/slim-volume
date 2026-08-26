<?php

declare(strict_types=1);

namespace SlimVolume\Export;

use SlimVolume\Artists\ProjectTaxonomy;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Maps stored Artist / Project taxonomy records into portable v1 objects.
 */
final class ArtistMapper
{
    private SourceRepository $source;
    private ReferenceIndex $refs;
    private WarningCollector $warnings;
    private MediaReferenceBuilder $media;

    public function __construct(
        SourceRepository $source,
        ReferenceIndex $refs,
        WarningCollector $warnings,
        MediaReferenceBuilder $media
    ) {
        $this->source = $source;
        $this->refs = $refs;
        $this->warnings = $warnings;
        $this->media = $media;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function map_all(): array
    {
        $artists = [];

        foreach ($this->refs->artist_source_ids() as $term_id) {
            $artists[] = $this->map_one($term_id);
        }

        return $artists;
    }

    /**
     * @return array<string,mixed>
     */
    private function map_one(int $term_id): array
    {
        $ref = $this->refs->artist_ref($term_id);

        if ($ref === null) {
            throw new ExportException(
                'Slim Volume could not allocate an Artist / Project export reference.'
            );
        }

        $source = $this->source->get_artist_source($term_id);

        $entity_type = $this->map_entity_type(
            $source['entityTypeExists'],
            $source['entityType'],
            $ref
        );

        return [
            'ref' => $ref,
            'name' => $source['name'],
            'slug' => $source['slug'],
            'description' => $source['description'],
            'entityType' => $entity_type,
            'website' => $source['website'],
            'officialProfiles' => OfficialProfiles::from_storage(
                $source['officialProfiles']
            ),
            'image' => $this->media->from_attachment(
                $source['imageId'],
                $ref
            ),
        ];
    }

    private function map_entity_type(
        bool $stored_value_exists,
        string $stored_value,
        string $object_ref
    ): string {
        $stored_value = trim($stored_value);

        if (! $stored_value_exists || $stored_value === '') {
            return ProjectTaxonomy::ENTITY_GROUP;
        }

        $normalized_key = sanitize_key($stored_value);

        if (
            ! in_array(
                $normalized_key,
                [
                    ProjectTaxonomy::ENTITY_GROUP,
                    ProjectTaxonomy::ENTITY_PERSON,
                ],
                true
            )
        ) {
            $this->warnings->add(
                'unsupported_artist_entity_type',
                $object_ref,
                'The stored Artist / Project entity type was unsupported and was normalized to Slim Volume\'s canonical portable value.'
            );
        }

        return ProjectTaxonomy::sanitize_entity_type($stored_value);
    }
}

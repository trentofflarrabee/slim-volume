<?php

declare(strict_types=1);

namespace SlimVolume\Export;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Maps Slim Volume-owned catalog identity settings into the portable v1
 * catalog profile without applying runtime artist/site fallbacks.
 */
final class CatalogMapper
{
    private SourceRepository $source;
    private MediaReferenceBuilder $media;

    public function __construct(
        SourceRepository $source,
        MediaReferenceBuilder $media
    ) {
        $this->source = $source;
        $this->media = $media;
    }

    /**
     * @return array{
     *   fallbackArtist:array{
     *     name:string,
     *     website:string,
     *     officialProfiles:array<int,string>
     *   },
     *   description:string,
     *   defaultArtwork:array<string,string>
     * }
     */
    public function map(): array
    {
        $source = $this->source->get_catalog_source();

        return [
            'fallbackArtist' => [
                'name' => $source['fallbackArtistName'],
                'website' => $source['fallbackArtistWebsite'],
                'officialProfiles' => OfficialProfiles::from_storage(
                    $source['fallbackArtistOfficialProfiles']
                ),
            ],
            'description' => $source['description'],
            'defaultArtwork' => $this->media->from_url(
                $source['defaultArtworkUrl']
            ),
        ];
    }
}

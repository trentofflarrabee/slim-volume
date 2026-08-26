<?php

declare(strict_types=1);

namespace SlimVolume\Export;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Coordinates creation of a Slim Volume portable discography snapshot.
 *
 * Mapping and serialization are added in later implementation stages. This
 * class establishes the format identity and export-local reference inventory.
 */
final class DiscographyExporter
{
    public const FORMAT = 'slim-volume-discography';
    public const FORMAT_VERSION = 1;

    private SourceRepository $source;
    private WarningCollector $warnings;

    public function __construct(
        ?SourceRepository $source = null,
        ?WarningCollector $warnings = null
    ) {
        $this->source = $source ?? new SourceRepository();
        $this->warnings = $warnings ?? new WarningCollector();
    }

    public function build_reference_index(): ReferenceIndex
    {
        return ReferenceIndex::from_source_ids(
            $this->source->get_artist_term_ids(),
            $this->source->get_release_ids(),
            $this->source->get_track_ids()
        );
    }

    public function warnings(): WarningCollector
    {
        return $this->warnings;
    }
}

<?php

declare(strict_types=1);

namespace SlimVolume\Export;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Coordinates creation of a Slim Volume portable discography snapshot.
 */
final class DiscographyExporter
{
    public const FORMAT = 'slim-volume-discography';
    public const FORMAT_VERSION = 1;

    private SourceRepository $source;
    private WarningCollector $warnings;
    private JsonWriter $writer;

    public function __construct(
        ?SourceRepository $source = null,
        ?WarningCollector $warnings = null,
        ?JsonWriter $writer = null
    ) {
        $this->source = $source ?? new SourceRepository();
        $this->warnings = $warnings ?? new WarningCollector();
        $this->writer = $writer ?? new JsonWriter();
    }

    public function build_reference_index(): ReferenceIndex
    {
        return ReferenceIndex::from_source_ids(
            $this->source->get_artist_term_ids(),
            $this->source->get_release_ids(),
            $this->source->get_track_ids()
        );
    }

    /**
     * Build the complete portable v1 document in memory.
     *
     * @return array<string,mixed>
     */
    public function build_document(): array
    {
        $refs = $this->build_reference_index();

        $media = new MediaReferenceBuilder(
            $this->source,
            $this->warnings
        );

        $lifecycle = new EditorialLifecycle(
            $this->warnings
        );

        $timed_lyrics = new TimedLyricsMapper(
            $this->source,
            $this->warnings
        );

        $catalog_mapper = new CatalogMapper(
            $this->source,
            $media
        );

        $artist_mapper = new ArtistMapper(
            $this->source,
            $refs,
            $this->warnings,
            $media
        );

        $release_mapper = new ReleaseMapper(
            $this->source,
            $refs,
            $this->warnings,
            $media,
            $lifecycle
        );

        $track_mapper = new TrackMapper(
            $this->source,
            $refs,
            $this->warnings,
            $media,
            $lifecycle,
            $timed_lyrics
        );

        /*
         * Mapping must complete before warnings are read, because relationship,
         * lifecycle, media, and timed-lyrics warnings are discovered while
         * portable objects are constructed.
         */
        $catalog = $catalog_mapper->map();
        $artists = $artist_mapper->map_all();
        $releases = $release_mapper->map_all();
        $tracks = $track_mapper->map_all();

        return [
            'schema' => [
                'format' => self::FORMAT,
                'formatVersion' => self::FORMAT_VERSION,
            ],
            'generatedBy' => [
                'plugin' => 'slim-volume',
                'pluginVersion' => defined('SLIM_VOLUME_VERSION')
                    ? (string) SLIM_VOLUME_VERSION
                    : '',
            ],
            'exportedAt' => gmdate('Y-m-d\TH:i:s\Z'),
            'source' => [
                'homeUrl' => self::source_home_url(),
            ],
            'counts' => [
                'artists' => $refs->artist_count(),
                'releases' => $refs->release_count(),
                'tracks' => $refs->track_count(),
            ],
            'warnings' => $this->warnings->all(),
            'catalog' => $catalog,
            'artists' => $artists,
            'releases' => $releases,
            'tracks' => $tracks,
        ];
    }

    /**
     * Generate the complete JSON artifact before any response is started.
     */
    public function generate_artifact(): ExportArtifact
    {
        return $this->writer->write(
            $this->build_document()
        );
    }

    public function warnings(): WarningCollector
    {
        return $this->warnings;
    }

    private static function source_home_url(): string
    {
        $home_url = get_option('home', '');

        if (! is_string($home_url) || $home_url === '') {
            return '';
        }

        return trailingslashit($home_url);
    }
}

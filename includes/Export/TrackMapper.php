<?php

declare(strict_types=1);

namespace SlimVolume\Export;

use SlimVolume\Relationships\TrackReleaseRelationship;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Maps Slim Volume track source data into the portable v1 track shape.
 *
 * Timed lyrics are projected from the stored authoring document by the
 * dedicated TimedLyricsMapper; no authoring reconciliation occurs here.
 */
final class TrackMapper
{
    private SourceRepository $source;
    private ReferenceIndex $refs;
    private WarningCollector $warnings;
    private MediaReferenceBuilder $media;
    private EditorialLifecycle $lifecycle;
    private TimedLyricsMapper $timed_lyrics;

    public function __construct(
        SourceRepository $source,
        ReferenceIndex $refs,
        WarningCollector $warnings,
        MediaReferenceBuilder $media,
        EditorialLifecycle $lifecycle,
        TimedLyricsMapper $timed_lyrics
    ) {
        $this->source = $source;
        $this->refs = $refs;
        $this->warnings = $warnings;
        $this->media = $media;
        $this->lifecycle = $lifecycle;
        $this->timed_lyrics = $timed_lyrics;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function map_all(): array
    {
        $tracks = [];

        foreach ($this->refs->track_source_ids() as $post_id) {
            $tracks[] = $this->map_one($post_id);
        }

        return $tracks;
    }

    /**
     * @return array<string,mixed>
     */
    private function map_one(int $post_id): array
    {
        $ref = $this->refs->track_ref($post_id);

        if ($ref === null) {
            throw new ExportException(
                'Slim Volume could not allocate a track export reference.'
            );
        }

        $source = $this->source->get_track_source($post_id);
        $lifecycle = $this->lifecycle->map(
            $post_id,
            $source['status'],
            $ref
        );

        return [
            'ref' => $ref,
            'release' => $this->map_release_relationship(
                $post_id,
                $ref
            ),
            'title' => $source['title'],
            'slug' => $source['slug'],
            'status' => $source['status'],
            'publishedAt' => $lifecycle['publishedAt'],
            'scheduledAt' => $lifecycle['scheduledAt'],
            'content' => $source['content'],
            'contentFormat' => 'wordpress-post-content',
            'excerpt' => $source['excerpt'],
            'excerptFormat' => 'wordpress-post-excerpt',
            'discNumber' => $source['discNumber'],
            'trackNumber' => $source['trackNumber'],
            'durationSeconds' => $source['durationSeconds'],
            'artwork' => $this->media->from_attachment(
                $source['artworkId'],
                $ref
            ),
            'audio' => [
                'attachment' => $this->media->from_attachment(
                    $source['audioAttachmentId'],
                    $ref
                ),
                'externalUrl' => $source['audioExternalUrl'],
            ],
            'download' => [
                'enabled' => self::bool_value(
                    $source['downloadEnabledRaw']
                ),
                'attachment' => $this->media->from_attachment(
                    $source['downloadAttachmentId'],
                    $ref
                ),
                'externalUrl' => $source['downloadExternalUrl'],
            ],
            'links' => [
                'external' => $source['externalUrl'],
                'spotify' => $source['spotify'],
                'appleMusic' => $source['appleMusic'],
                'youtube' => $source['youtube'],
                'bandcamp' => $source['bandcamp'],
                'purchase' => $source['purchase'],
            ],
            'lyrics' => $source['lyrics'],
            'timedLyrics' => $this->timed_lyrics->map(
                $post_id,
                $ref
            ),
            'credits' => $source['credits'],
        ];
    }

    private function map_release_relationship(
        int $post_id,
        string $object_ref
    ): ?string {
        $state = TrackReleaseRelationship::get_state($post_id);

        if ($state['has_conflict']) {
            $this->warnings->add(
                'conflicting_track_release_relationship',
                $object_ref,
                'The track has conflicting stored release relationships; Slim Volume exported its canonical resolved relationship.'
            );
        } elseif (
            $state['resolved_release_id'] > 0
            && $state['needs_repair']
        ) {
            $this->warnings->add(
                'track_release_relationship_needs_repair',
                $object_ref,
                'The track release relationship resolves canonically, but its compatibility storage needs repair.'
            );
        }

        $release_id = (int) $state['resolved_release_id'];

        if ($release_id > 0) {
            $release_ref = $this->refs->release_ref($release_id);

            if ($release_ref !== null) {
                return $release_ref;
            }
        }

        $this->warnings->add(
            'unresolved_track_release',
            $object_ref,
            'Track relationship could not be resolved to an exported release.'
        );

        return null;
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

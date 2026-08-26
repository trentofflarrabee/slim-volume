<?php

declare(strict_types=1);

namespace SlimVolume\Export;

use SlimVolume\TimedLyrics;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Projects Slim Volume's stored timed-lyrics document into the portable v1
 * shape without regenerating authoring state or exporting source-site
 * validation identifiers.
 */
final class TimedLyricsMapper
{
    /**
     * @var array<int,string>
     */
    private const STORED_AUTHORING_STATUSES = [
        TimedLyrics::STATUS_DRAFT,
        TimedLyrics::STATUS_COMPLETE,
    ];

    /**
     * @var array<int,string>
     */
    private const PORTABLE_STATUSES = [
        TimedLyrics::STATUS_DRAFT,
        TimedLyrics::STATUS_COMPLETE,
        TimedLyrics::STATUS_STALE,
    ];

    /**
     * @var array<int,string>
     */
    private const LINE_TYPES = [
        'line',
        'section',
        'spacer',
    ];

    private SourceRepository $source;
    private WarningCollector $warnings;

    public function __construct(
        SourceRepository $source,
        WarningCollector $warnings
    ) {
        $this->source = $source;
        $this->warnings = $warnings;
    }

    /**
     * @return array<string,mixed>|\stdClass
     */
    public function map(
        int $track_id,
        string $object_ref
    ) {
        $stored = $this->source->get_timed_lyrics_source(
            $track_id
        );

        if (! $stored['exists']) {
            return self::empty_document();
        }

        $raw_value = $stored['value'];

        if (
            is_string($raw_value)
            && trim($raw_value) === ''
        ) {
            return self::empty_document();
        }

        $document = $this->decode_document($raw_value);

        if (
            $document === null
            || ! $this->has_usable_document_state($document)
        ) {
            $this->warn_invalid_document(
                $object_ref,
                'The stored timed-lyrics metadata could not be decoded into a usable timing document.'
            );

            return self::empty_document();
        }

        $structural_issue = false;

        $stored_status = self::scalar_string(
            $document['status'] ?? ''
        );

        if (
            $stored_status !== ''
            && ! in_array(
                $stored_status,
                self::STORED_AUTHORING_STATUSES,
                true
            )
        ) {
            $this->warnings->add(
                'unsupported_timed_lyrics_status',
                $object_ref,
                'The stored timed-lyrics authoring status was unsupported; the portable document uses Slim Volume\'s authoritative current status.'
            );
        } elseif ($stored_status === '') {
            $structural_issue = true;
        }

        $status = TimedLyrics::get_status($track_id);

        if (
            ! in_array(
                $status,
                self::PORTABLE_STATUSES,
                true
            )
        ) {
            /*
             * A non-empty usable document must never become portable "none".
             * If the current service cannot assign a normal portable status,
             * stale is the safe representation of existing timing work.
             */
            $status = TimedLyrics::STATUS_STALE;
            $structural_issue = true;
        }

        $version = 0;

        if (
            isset($document['version'])
            && is_numeric($document['version'])
        ) {
            $version = max(
                0,
                (int) $document['version']
            );
        } elseif (array_key_exists('version', $document)) {
            $structural_issue = true;
        }

        $duration_seconds = $this->map_duration(
            $document,
            $structural_issue
        );

        $updated_at = $this->map_updated_at(
            $document['updatedAt'] ?? null,
            $structural_issue
        );

        $lines = $this->map_lines(
            $document,
            $object_ref,
            $structural_issue
        );

        if ($structural_issue) {
            $this->warn_invalid_document(
                $object_ref,
                'The stored timed-lyrics document contained malformed values; valid portable timing data was preserved where possible.'
            );
        }

        return [
            'version' => $version,
            'status' => $status,
            'updatedAt' => $updated_at,
            'durationSeconds' => $duration_seconds,
            'lines' => $lines,
        ];
    }

    /**
     * @param mixed $raw_value
     * @return array<string,mixed>|null
     */
    private function decode_document($raw_value): ?array
    {
        if (is_array($raw_value)) {
            return $raw_value;
        }

        if (! is_string($raw_value)) {
            return null;
        }

        $decoded = json_decode(
            $raw_value,
            true
        );

        return is_array($decoded)
            ? $decoded
            : null;
    }

    /**
     * @param array<string,mixed> $document
     */
    private function has_usable_document_state(
        array $document
    ): bool {
        foreach (
            [
                'version',
                'status',
                'audio',
                'updatedAt',
                'lines',
            ]
            as $key
        ) {
            if (array_key_exists($key, $document)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string,mixed> $document
     */
    private function map_duration(
        array $document,
        bool &$structural_issue
    ): float {
        $audio = $document['audio'] ?? [];

        if ($audio !== [] && ! is_array($audio)) {
            $structural_issue = true;
            return 0.0;
        }

        if (! is_array($audio)) {
            return 0.0;
        }

        $duration = $audio['duration'] ?? null;

        if ($duration === null || $duration === '') {
            return 0.0;
        }

        if (! is_numeric($duration)) {
            $structural_issue = true;
            return 0.0;
        }

        $duration = (float) $duration;

        if (
            ! is_finite($duration)
            || $duration < 0
        ) {
            $structural_issue = true;
            return 0.0;
        }

        return $duration;
    }

    /**
     * @param mixed $value
     */
    private function map_updated_at(
        $value,
        bool &$structural_issue
    ): ?string {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value)) {
            $structural_issue = true;
            return null;
        }

        try {
            $datetime = new \DateTimeImmutable($value);
        } catch (\Exception $exception) {
            $structural_issue = true;
            return null;
        }

        return $datetime
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s\Z');
    }

    /**
     * @param array<string,mixed> $document
     * @return array<int,array{
     *   id:string,
     *   type:string,
     *   text:string,
     *   start:?float
     * }>
     */
    private function map_lines(
        array $document,
        string $object_ref,
        bool &$structural_issue
    ): array {
        if (! array_key_exists('lines', $document)) {
            return [];
        }

        if (! is_array($document['lines'])) {
            $structural_issue = true;
            return [];
        }

        $lines = [];
        $unsupported_type = false;
        $seen_ids = [];

        foreach ($document['lines'] as $raw_line) {
            if (! is_array($raw_line)) {
                $structural_issue = true;
                continue;
            }

            $id = self::scalar_string(
                $raw_line['id'] ?? ''
            );

            if (
                ! array_key_exists('id', $raw_line)
                || $id === ''
            ) {
                $structural_issue = true;
            }

            if ($id !== '') {
                if (isset($seen_ids[$id])) {
                    $structural_issue = true;
                }

                $seen_ids[$id] = true;
            }

            $type = self::scalar_string(
                $raw_line['type'] ?? ''
            );

            if ($type === '') {
                $structural_issue = true;
            } elseif (
                ! in_array(
                    $type,
                    self::LINE_TYPES,
                    true
                )
            ) {
                $unsupported_type = true;
            }

            $text = self::scalar_string(
                $raw_line['text'] ?? ''
            );

            if (
                array_key_exists('text', $raw_line)
                && ! is_scalar($raw_line['text'])
                && $raw_line['text'] !== null
            ) {
                $structural_issue = true;
            }

            $start = $this->map_line_start(
                $raw_line['start'] ?? null,
                $type,
                $structural_issue
            );

            $lines[] = [
                'id' => $id,
                'type' => $type,
                'text' => $text,
                'start' => $start,
            ];
        }

        if ($unsupported_type) {
            $this->warnings->add(
                'unsupported_timed_lyrics_line_type',
                $object_ref,
                'The timed-lyrics document contains an unsupported stored line type; the opaque line type was preserved.'
            );
        }

        return $lines;
    }

    /**
     * @param mixed $value
     */
    private function map_line_start(
        $value,
        string $type,
        bool &$structural_issue
    ): ?float {
        if ($value === null || $value === '') {
            return null;
        }

        /*
         * Current Slim Volume only assigns timing points to semantic lyric
         * lines. Section/spacer timing in corrupted source is not promoted
         * into a new portable semantic.
         */
        if ($type !== 'line') {
            $structural_issue = true;
            return null;
        }

        if (! is_numeric($value)) {
            $structural_issue = true;
            return null;
        }

        $start = (float) $value;

        if (
            ! is_finite($start)
            || $start < 0
        ) {
            $structural_issue = true;
            return null;
        }

        return $start;
    }

    /**
     * @param mixed $value
     */
    private static function scalar_string($value): string
    {
        return is_scalar($value)
            ? (string) $value
            : '';
    }

    private static function empty_document(): \stdClass
    {
        return new \stdClass();
    }

    private function warn_invalid_document(
        string $object_ref,
        string $message
    ): void {
        $this->warnings->add(
            'invalid_timed_lyrics_document',
            $object_ref,
            $message
        );
    }
}

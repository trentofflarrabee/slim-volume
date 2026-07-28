<?php

declare(strict_types=1);

namespace SlimVolume;

use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Domain service for versioned, line-level timed lyrics.
 *
 * Plain lyrics in _sv_lyrics remain the canonical content. Timed lyrics are
 * optional authoring metadata and never replace or mutate the plain lyrics.
 */
final class TimedLyrics
{
    public const META_KEY        = '_sv_timed_lyrics';
    public const STATUS_META_KEY = '_sv_timed_lyrics_status';

    public const SCHEMA_VERSION = 1;

    public const STATUS_NONE     = 'none';
    public const STATUS_DRAFT    = 'draft';
    public const STATUS_COMPLETE = 'complete';
    public const STATUS_STALE    = 'stale';

    private const MAX_JSON_BYTES  = 524288;
    private const MAX_RECORDS     = 2000;
    private const MAX_TEXT_LENGTH = 2000;
    private const MAX_START       = 86400.0;
    private const PRECISION       = 3;

    /**
     * Return the stored document, or an empty array when no valid JSON exists.
     */
    public static function get_document(int $track_id): array
    {
        if (! self::is_track($track_id)) {
            return [];
        }

        $raw = get_post_meta($track_id, self::META_KEY, true);

        if (is_array($raw)) {
            return $raw;
        }

        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Save a draft or complete document.
     *
     * Return shape:
     * [
     *   'saved'    => bool,
     *   'status'   => string,
     *   'document' => array,
     *   'errors'   => array<array{code:string,message:string}>,
     * ]
     */
    public static function save_document(int $track_id, array $payload): array
    {
        if (! self::is_track($track_id)) {
            return self::result_error(
                'invalid_track',
                __('Timed lyrics can only be saved for a Slim Volume track.', 'slim-volume')
            );
        }

        $document  = self::prepare_document($track_id, $payload, true);
        $publishing = ($document['status'] ?? self::STATUS_DRAFT) === self::STATUS_COMPLETE;
        $validation = self::validate_prepared_document($track_id, $document, $publishing);

        if (! $validation['valid']) {
            return [
                'saved'    => false,
                'status'   => self::STATUS_STALE,
                'document' => $document,
                'errors'   => $validation['errors'],
            ];
        }

        $encoded = wp_json_encode(
            $document,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        if (! is_string($encoded) || strlen($encoded) > self::MAX_JSON_BYTES) {
            return self::result_error(
                'document_too_large',
                __('Timed lyrics data is too large to save.', 'slim-volume'),
                $document
            );
        }

        update_post_meta($track_id, self::META_KEY, $encoded);

        $status = $publishing
            ? self::STATUS_COMPLETE
            : self::STATUS_DRAFT;

        update_post_meta($track_id, self::STATUS_META_KEY, $status);

        /**
         * Fires after a timed-lyrics document is saved.
         *
         * @param int    $track_id Track post ID.
         * @param array  $document Canonical saved document.
         * @param string $status   Derived status.
         */
        do_action('slim_volume_timed_lyrics_saved', $track_id, $document, $status);

        return [
            'saved'    => true,
            'status'   => $status,
            'document' => $document,
            'errors'   => [],
        ];
    }

    public static function delete_document(int $track_id): void
    {
        if (! self::is_track($track_id)) {
            return;
        }

        delete_post_meta($track_id, self::META_KEY);
        delete_post_meta($track_id, self::STATUS_META_KEY);

        /**
         * Fires after timed lyrics are deleted.
         *
         * @param int $track_id Track post ID.
         */
        do_action('slim_volume_timed_lyrics_deleted', $track_id);
    }

    /**
     * Compute status from the authoritative document and current track data.
     */
    public static function get_status(int $track_id): string
    {
        $document = self::get_document($track_id);

        if (! $document) {
            return self::STATUS_NONE;
        }

        $stored_status = sanitize_key((string) ($document['status'] ?? ''));

        if (! in_array($stored_status, [self::STATUS_DRAFT, self::STATUS_COMPLETE], true)) {
            return self::STATUS_STALE;
        }

        $prepared   = self::sanitize_document_shape($document);
        $validation = self::validate_prepared_document(
            $track_id,
            $prepared,
            $stored_status === self::STATUS_COMPLETE
        );

        if (! $validation['valid']) {
            return self::STATUS_STALE;
        }

        return $stored_status;
    }

    /**
     * Recompute and cache status after normal Track saves.
     */
    public static function reconcile(int $track_id): string
    {
        if (! self::is_track($track_id) || wp_is_post_revision($track_id)) {
            return self::STATUS_NONE;
        }

        $status = self::get_status($track_id);

        update_post_meta($track_id, self::STATUS_META_KEY, $status);

        /**
         * Fires after the derived timed-lyrics status is reconciled.
         *
         * @param int    $track_id Track post ID.
         * @param string $status   Derived status.
         */
        do_action('slim_volume_timed_lyrics_reconciled', $track_id, $status);

        return $status;
    }

    /**
     * Return public data only when the document is complete and current.
     */
    public static function get_public_payload(int $track_id): array
    {
        if (self::get_status($track_id) !== self::STATUS_COMPLETE) {
            return [];
        }

        $document = self::get_document($track_id);

        if (! $document) {
            return [];
        }

        $document = self::sanitize_document_shape($document);

        return [
            'version'  => self::SCHEMA_VERSION,
            'trackId'  => $track_id,
            'duration' => (float) ($document['audio']['duration'] ?? 0),
            'lines'    => array_values(
                array_map(
                    static function (array $line): array {
                        return [
                            'id'    => (string) ($line['id'] ?? ''),
                            'type'  => (string) ($line['type'] ?? 'line'),
                            'text'  => (string) ($line['text'] ?? ''),
                            'start' => isset($line['start']) && is_numeric($line['start'])
                                ? (float) $line['start']
                                : null,
                        ];
                    },
                    is_array($document['lines'] ?? null) ? $document['lines'] : []
                )
            ),
        ];
    }

    /**
     * Normalize plain lyrics for hashing and line generation.
     */
    public static function normalize_lyrics(string $lyrics): string
    {
        $lyrics = str_replace(["\r\n", "\r"], "\n", $lyrics);
        $lyrics = html_entity_decode(
            $lyrics,
            ENT_QUOTES | ENT_HTML5,
            get_bloginfo('charset') ?: 'UTF-8'
        );
        $lyrics = wp_strip_all_tags($lyrics);

        $lines = explode("\n", $lyrics);

        foreach ($lines as &$line) {
            $line = preg_replace('/[ \t]+$/u', '', $line) ?? rtrim($line);
        }
        unset($line);

        while ($lines && trim((string) reset($lines)) === '') {
            array_shift($lines);
        }

        while ($lines && trim((string) end($lines)) === '') {
            array_pop($lines);
        }

        return implode("\n", $lines);
    }

    public static function lyrics_hash(string $lyrics): string
    {
        return 'sha256:' . hash('sha256', self::normalize_lyrics($lyrics));
    }

    /**
     * Generate the default authoring model from canonical plain lyrics.
     */
    public static function generate_lines(string $lyrics): array
    {
        $normalized = self::normalize_lyrics($lyrics);

        if ($normalized === '') {
            return [];
        }

        $records = [];
        $lines   = explode("\n", $normalized);

        foreach ($lines as $index => $text) {
            $number = $index + 1;
            $spacer = trim($text) === '';

            $records[] = [
                'id'    => sprintf('%s-%04d', $spacer ? 'spacer' : 'line', $number),
                'type'  => $spacer ? 'spacer' : 'line',
                'text'  => $spacer ? '' : $text,
                'start' => null,
            ];
        }

        return $records;
    }

    /**
     * Resolve the current audio source using the same attachment-first order as
     * the frontend player.
     */
    public static function audio_descriptor(int $track_id, float $duration = 0): array
    {
        $attachment_id = (int) get_post_meta($track_id, '_sv_audio_attachment_id', true);
        $resolved_id   = 0;
        $audio_url     = '';

        if ($attachment_id > 0) {
            $attachment_url = wp_get_attachment_url($attachment_id);

            if (is_string($attachment_url) && $attachment_url !== '') {
                $resolved_id = $attachment_id;
                $audio_url   = $attachment_url;
            }
        }

        if ($audio_url === '') {
            $external_url = (string) get_post_meta($track_id, '_sv_audio_url', true);
            $audio_url    = $external_url;
        }

        $audio_url = self::normalize_audio_url($audio_url);
        $duration  = max(0.0, min(self::MAX_START, $duration));

        return [
            'attachmentId' => $resolved_id,
            'urlHash'      => $audio_url !== ''
                ? 'sha256:' . hash('sha256', $audio_url)
                : '',
            'duration'     => round($duration, self::PRECISION),
        ];
    }

    /**
     * Validate a stored or incoming document against the current track.
     *
     * The returned document is canonicalized but not saved.
     */
    public static function validate_document(
        int $track_id,
        array $document,
        bool $for_publish
    ): array {
        $prepared = self::sanitize_document_shape($document);
        $result   = self::validate_prepared_document($track_id, $prepared, $for_publish);
        $result['document'] = $prepared;

        return $result;
    }

    /**
     * Sanitize direct meta writes. Structured writes should use save_document().
     */
    public static function sanitize_json_meta($value): string
    {
        if (is_string($value)) {
            if (strlen($value) > self::MAX_JSON_BYTES) {
                return '';
            }

            $decoded = json_decode($value, true);
        } elseif (is_array($value)) {
            $decoded = $value;
        } else {
            return '';
        }

        if (! is_array($decoded)) {
            return '';
        }

        $document = self::sanitize_document_shape($decoded);
        $encoded  = wp_json_encode(
            $document,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        if (! is_string($encoded) || strlen($encoded) > self::MAX_JSON_BYTES) {
            return '';
        }

        return $encoded;
    }

    public static function sanitize_status($value): string
    {
        $status = sanitize_key((string) $value);

        return in_array(
            $status,
            [
                self::STATUS_NONE,
                self::STATUS_DRAFT,
                self::STATUS_COMPLETE,
                self::STATUS_STALE,
            ],
            true
        )
            ? $status
            : self::STATUS_NONE;
    }

    private static function prepare_document(
        int $track_id,
        array $payload,
        bool $refresh_updated_at
    ): array {
        $document = self::sanitize_document_shape($payload);

        $requested_status = sanitize_key((string) ($document['status'] ?? self::STATUS_DRAFT));

        if (! in_array($requested_status, [self::STATUS_DRAFT, self::STATUS_COMPLETE], true)) {
            $requested_status = self::STATUS_DRAFT;
        }

        $duration = isset($document['audio']['duration']) && is_numeric($document['audio']['duration'])
            ? (float) $document['audio']['duration']
            : 0.0;

        $lyrics = (string) get_post_meta($track_id, '_sv_lyrics', true);

        $document['version']    = self::SCHEMA_VERSION;
        $document['status']     = $requested_status;
        $document['trackId']    = $track_id;
        $document['lyricsHash'] = self::lyrics_hash($lyrics);
        $document['audio']      = self::audio_descriptor($track_id, $duration);

        if ($refresh_updated_at || empty($document['updatedAt'])) {
            $document['updatedAt'] = gmdate('c');
        }

        return $document;
    }

    private static function sanitize_document_shape(array $payload): array
    {
        $status = sanitize_key((string) ($payload['status'] ?? self::STATUS_DRAFT));

        if (! in_array($status, [self::STATUS_DRAFT, self::STATUS_COMPLETE], true)) {
            $status = self::STATUS_DRAFT;
        }

        $raw_lines = is_array($payload['lines'] ?? null)
            ? array_slice($payload['lines'], 0, self::MAX_RECORDS)
            : [];

        $lines    = [];
        $used_ids = [];

        foreach ($raw_lines as $index => $raw_line) {
            if (! is_array($raw_line)) {
                continue;
            }

            $type = sanitize_key((string) ($raw_line['type'] ?? 'line'));

            if (! in_array($type, ['line', 'section', 'spacer'], true)) {
                $type = 'line';
            }

            $text = $type === 'spacer'
                ? ''
                : sanitize_text_field((string) ($raw_line['text'] ?? ''));

            $text = self::truncate($text, self::MAX_TEXT_LENGTH);

            $fallback_id = sprintf('%s-%04d', $type, $index + 1);
            $id          = sanitize_key((string) ($raw_line['id'] ?? $fallback_id));

            if ($id === '' || isset($used_ids[$id])) {
                $id = $fallback_id;
            }

            while (isset($used_ids[$id])) {
                $id .= '-x';
            }

            $used_ids[$id] = true;

            $start = null;

            if ($type === 'line' && isset($raw_line['start']) && $raw_line['start'] !== '') {
                if (is_numeric($raw_line['start'])) {
                    $numeric_start = (float) $raw_line['start'];

                    if (is_finite($numeric_start)) {
                        $start = round($numeric_start, self::PRECISION);
                    }
                }
            }

            $lines[] = [
                'id'    => $id,
                'type'  => $type,
                'text'  => $text,
                'start' => $start,
            ];
        }

        $audio = is_array($payload['audio'] ?? null)
            ? $payload['audio']
            : [];

        $duration = isset($audio['duration']) && is_numeric($audio['duration'])
            ? round((float) $audio['duration'], self::PRECISION)
            : 0.0;

        return [
            'version'    => absint($payload['version'] ?? self::SCHEMA_VERSION),
            'status'     => $status,
            'trackId'    => absint($payload['trackId'] ?? 0),
            'lyricsHash' => sanitize_text_field((string) ($payload['lyricsHash'] ?? '')),
            'audio'      => [
                'attachmentId' => absint($audio['attachmentId'] ?? 0),
                'urlHash'      => sanitize_text_field((string) ($audio['urlHash'] ?? '')),
                'duration'     => max(0.0, min(self::MAX_START, $duration)),
            ],
            'updatedAt'  => sanitize_text_field((string) ($payload['updatedAt'] ?? '')),
            'lines'      => $lines,
        ];
    }

    private static function validate_prepared_document(
        int $track_id,
        array $document,
        bool $for_publish
    ): array {
        $errors = [];

        if (! self::is_track($track_id)) {
            $errors[] = self::error(
                'invalid_track',
                __('The timed-lyrics track is invalid.', 'slim-volume')
            );
        }

        if ((int) ($document['version'] ?? 0) !== self::SCHEMA_VERSION) {
            $errors[] = self::error(
                'unsupported_version',
                __('The timed-lyrics document uses an unsupported schema version.', 'slim-volume')
            );
        }

        if ((int) ($document['trackId'] ?? 0) !== $track_id) {
            $errors[] = self::error(
                'track_mismatch',
                __('The timed-lyrics document belongs to a different track.', 'slim-volume')
            );
        }

        $lyrics = (string) get_post_meta($track_id, '_sv_lyrics', true);

        if (self::normalize_lyrics($lyrics) === '') {
            $errors[] = self::error(
                'missing_lyrics',
                __('Add plain lyrics before synchronizing them.', 'slim-volume')
            );
        }

        $current_lyrics_hash = self::lyrics_hash($lyrics);
        $stored_lyrics_hash  = (string) ($document['lyricsHash'] ?? '');

        if ($stored_lyrics_hash === '' || ! hash_equals($current_lyrics_hash, $stored_lyrics_hash)) {
            $errors[] = self::error(
                'lyrics_changed',
                __('The plain lyrics have changed since this timing document was created.', 'slim-volume')
            );
        }

        $stored_audio = is_array($document['audio'] ?? null)
            ? $document['audio']
            : [];

        $current_audio = self::audio_descriptor(
            $track_id,
            isset($stored_audio['duration']) && is_numeric($stored_audio['duration'])
                ? (float) $stored_audio['duration']
                : 0.0
        );

        if ((string) ($current_audio['urlHash'] ?? '') === '') {
            $errors[] = self::error(
                'missing_audio',
                __('Add a playable audio file before synchronizing lyrics.', 'slim-volume')
            );
        }

        if (
            (int) ($stored_audio['attachmentId'] ?? 0) !== (int) $current_audio['attachmentId']
            || (string) ($stored_audio['urlHash'] ?? '') !== (string) $current_audio['urlHash']
        ) {
            $errors[] = self::error(
                'audio_changed',
                __('The track audio has changed since this timing document was created.', 'slim-volume')
            );
        }

        $lines = is_array($document['lines'] ?? null)
            ? $document['lines']
            : [];

        if (count($lines) > self::MAX_RECORDS) {
            $errors[] = self::error(
                'too_many_lines',
                __('The timed-lyrics document contains too many records.', 'slim-volume')
            );
        }

        $expected_lines = self::generate_lines($lyrics);

        if (! self::line_model_matches($expected_lines, $lines)) {
            $errors[] = self::error(
                'line_model_changed',
                __('The timed-lyrics lines no longer match the current plain lyrics.', 'slim-volume')
            );
        }

        $timed_line_count = 0;
        $previous_start   = null;
        $duration         = isset($stored_audio['duration']) && is_numeric($stored_audio['duration'])
            ? (float) $stored_audio['duration']
            : 0.0;

        foreach ($lines as $line) {
            if (! is_array($line) || ($line['type'] ?? '') !== 'line') {
                continue;
            }

            ++$timed_line_count;

            $start = $line['start'] ?? null;

            if (! $for_publish) {
                continue;
            }

            if (! is_numeric($start) || ! is_finite((float) $start)) {
                $errors[] = self::error(
                    'missing_timestamp',
                    __('Every lyric line needs a timestamp before publishing.', 'slim-volume')
                );
                break;
            }

            $start = (float) $start;

            if ($start < 0 || $start > self::MAX_START) {
                $errors[] = self::error(
                    'invalid_timestamp',
                    __('A lyric timestamp falls outside the supported range.', 'slim-volume')
                );
                break;
            }

            if ($previous_start !== null && $start <= $previous_start) {
                $errors[] = self::error(
                    'timestamps_not_increasing',
                    __('Lyric timestamps must be in strictly increasing order.', 'slim-volume')
                );
                break;
            }

            if ($duration > 0 && $start > ($duration + 0.5)) {
                $errors[] = self::error(
                    'timestamp_after_audio',
                    __('A lyric timestamp occurs after the end of the audio.', 'slim-volume')
                );
                break;
            }

            $previous_start = $start;
        }

        if ($for_publish && $timed_line_count === 0) {
            $errors[] = self::error(
                'no_timed_lines',
                __('At least one lyric line is required.', 'slim-volume')
            );
        }

        $encoded = wp_json_encode(
            $document,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        if (! is_string($encoded) || strlen($encoded) > self::MAX_JSON_BYTES) {
            $errors[] = self::error(
                'document_too_large',
                __('The timed-lyrics document exceeds the storage limit.', 'slim-volume')
            );
        }

        return [
            'valid'  => $errors === [],
            'errors' => $errors,
        ];
    }

    private static function line_model_matches(array $expected, array $actual): bool
    {
        if (count($expected) !== count($actual)) {
            return false;
        }

        foreach ($expected as $index => $expected_line) {
            $actual_line = $actual[$index] ?? null;

            if (! is_array($actual_line)) {
                return false;
            }

            $expected_type = (string) ($expected_line['type'] ?? '');
            $actual_type   = (string) ($actual_line['type'] ?? '');
            $expected_text = (string) ($expected_line['text'] ?? '');
            $actual_text   = (string) ($actual_line['text'] ?? '');

            if ($expected_type === 'spacer') {
                if ($actual_type !== 'spacer' || $actual_text !== '') {
                    return false;
                }

                continue;
            }

            if (! in_array($actual_type, ['line', 'section'], true)) {
                return false;
            }

            if ($expected_text !== $actual_text) {
                return false;
            }
        }

        return true;
    }

    private static function normalize_audio_url(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        $url = preg_replace('/#.*$/', '', $url) ?? $url;

        return esc_url_raw($url);
    }

    private static function is_track(int $track_id): bool
    {
        $post = get_post($track_id);

        return $post instanceof WP_Post && $post->post_type === PostTypes::TRACK;
    }

    private static function truncate(string $value, int $length): string
    {
        if (function_exists('mb_substr')) {
            return (string) mb_substr($value, 0, $length);
        }

        return substr($value, 0, $length);
    }

    private static function error(string $code, string $message): array
    {
        return [
            'code'    => $code,
            'message' => $message,
        ];
    }

    private static function result_error(
        string $code,
        string $message,
        array $document = []
    ): array {
        return [
            'saved'    => false,
            'status'   => self::STATUS_STALE,
            'document' => $document,
            'errors'   => [self::error($code, $message)],
        ];
    }
}

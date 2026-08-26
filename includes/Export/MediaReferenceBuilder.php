<?php

declare(strict_types=1);

namespace SlimVolume\Export;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Builds the descriptive v1 media-reference shape without exposing
 * WordPress attachment IDs or runtime attachment-URL filters.
 */
final class MediaReferenceBuilder
{
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
     * Build a URL-only media reference for explicitly stored catalog media.
     *
     * The exporter intentionally does not guess filename, MIME type, title,
     * or alt text from a URL.
     *
     * @return array<string,string>
     */
    public function from_url(string $url): array
    {
        if ($url === '') {
            return [];
        }

        return self::shape(
            $url,
            '',
            '',
            '',
            ''
        );
    }

    /**
     * Build a persistent descriptive reference for an explicit attachment.
     *
     * @return array<string,string>
     */
    public function from_attachment(
        int $attachment_id,
        ?string $object_ref
    ): array {
        if ($attachment_id <= 0) {
            return [];
        }

        $source = $this->source->get_attachment_source($attachment_id);

        if ($source === null) {
            $this->warn_missing($object_ref);

            return [];
        }

        $url = self::persistent_attachment_url(
            $source['attachedFile'],
            $source['guid']
        );

        $filename = $source['attachedFile'] !== ''
            ? wp_basename($source['attachedFile'])
            : '';

        if ($source['attachedFile'] === '' || $url === '') {
            $this->warn_missing($object_ref);
        }

        $reference = self::shape(
            $url,
            $filename,
            $source['mimeType'],
            $source['title'],
            $source['alt']
        );

        foreach ($reference as $value) {
            if ($value !== '') {
                return $reference;
            }
        }

        return [];
    }

    private static function persistent_attachment_url(
        string $attached_file,
        string $guid
    ): string {
        if ($attached_file !== '') {
            $uploads = wp_get_upload_dir();

            if (
                is_array($uploads)
                && empty($uploads['error'])
                && isset($uploads['basedir'], $uploads['baseurl'])
            ) {
                $basedir = (string) $uploads['basedir'];
                $baseurl = (string) $uploads['baseurl'];

                if (
                    $basedir !== ''
                    && str_starts_with($attached_file, $basedir)
                ) {
                    return str_replace(
                        $basedir,
                        $baseurl,
                        $attached_file
                    );
                }

                if (str_contains($attached_file, 'wp-content/uploads')) {
                    $relative_path = _wp_get_attachment_relative_path(
                        $attached_file
                    );

                    return trailingslashit(
                        $baseurl . '/' . $relative_path
                    ) . wp_basename($attached_file);
                }

                return trailingslashit($baseurl)
                    . ltrim($attached_file, '/');
            }
        }

        return $guid;
    }

    /**
     * @return array{
     *   url:string,
     *   filename:string,
     *   mimeType:string,
     *   title:string,
     *   alt:string
     * }
     */
    private static function shape(
        string $url,
        string $filename,
        string $mime_type,
        string $title,
        string $alt
    ): array {
        return [
            'url'      => $url,
            'filename' => $filename,
            'mimeType' => $mime_type,
            'title'    => $title,
            'alt'      => $alt,
        ];
    }

    private function warn_missing(?string $object_ref): void
    {
        $this->warnings->add(
            'missing_media_reference',
            $object_ref,
            'An explicitly assigned media attachment could not be fully resolved to persistent descriptive media data.'
        );
    }
}

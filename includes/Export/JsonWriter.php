<?php

declare(strict_types=1);

namespace SlimVolume\Export;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Encodes a complete portable document into private temporary storage.
 *
 * A successful artifact is returned only after the entire JSON representation
 * has been encoded and written.
 */
final class JsonWriter
{
    private const ENCODE_FLAGS = JSON_PRETTY_PRINT
        | JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE;

    /**
     * @param array<string,mixed> $document
     */
    public function write(array $document): ExportArtifact
    {
        $json = wp_json_encode(
            $document,
            self::ENCODE_FLAGS
        );

        if (! is_string($json)) {
            throw new ExportException(
                'Slim Volume could not encode the discography export as valid JSON: '
                . json_last_error_msg()
            );
        }

        /*
         * A final newline keeps the downloaded JSON friendly to command-line
         * tooling without changing the JSON document itself.
         */
        $json .= "\n";

        $path = $this->create_private_temp_file();

        try {
            $this->write_complete_file(
                $path,
                $json
            );

            $size = filesize($path);

            if (! is_int($size) || $size !== strlen($json)) {
                throw new ExportException(
                    'Slim Volume could not verify the completed export artifact.'
                );
            }

            return new ExportArtifact(
                $path,
                $size
            );
        } catch (\Throwable $exception) {
            if (is_file($path)) {
                unlink($path);
            }

            if ($exception instanceof ExportException) {
                throw $exception;
            }

            throw new ExportException(
                'Slim Volume could not create the private export artifact.',
                0,
                $exception
            );
        }
    }

    private function create_private_temp_file(): string
    {
        $temp_dir = sys_get_temp_dir();

        if (
            ! is_string($temp_dir)
            || trim($temp_dir) === ''
            || ! is_dir($temp_dir)
            || ! is_writable($temp_dir)
        ) {
            throw new ExportException(
                'A writable private system temporary directory is unavailable for discography export.'
            );
        }

        $temp_dir = realpath($temp_dir);

        if (! is_string($temp_dir) || $temp_dir === '') {
            throw new ExportException(
                'Slim Volume could not resolve the private system temporary directory.'
            );
        }

        $this->assert_non_public_directory($temp_dir);

        $path = tempnam(
            $temp_dir,
            'slim-volume-discography-'
        );

        if (! is_string($path) || $path === '') {
            throw new ExportException(
                'Slim Volume could not allocate private temporary storage for the discography export.'
            );
        }

        /*
         * tempnam() creates the file atomically. Tighten permissions where the
         * platform supports POSIX-style modes.
         */
        chmod($path, 0600);

        return $path;
    }

    private function assert_non_public_directory(
        string $temp_dir
    ): void {
        $unsafe_roots = [];

        if (defined('ABSPATH')) {
            $unsafe_roots[] = ABSPATH;
        }

        if (defined('WP_CONTENT_DIR')) {
            $unsafe_roots[] = WP_CONTENT_DIR;
        }

        $uploads = wp_get_upload_dir();

        if (
            is_array($uploads)
            && isset($uploads['basedir'])
            && is_string($uploads['basedir'])
            && $uploads['basedir'] !== ''
        ) {
            $unsafe_roots[] = $uploads['basedir'];
        }

        foreach ($unsafe_roots as $root) {
            if (! is_string($root) || $root === '') {
                continue;
            }

            if ($this->path_is_within($temp_dir, $root)) {
                throw new ExportException(
                    'Slim Volume refused to place the discography export in a potentially public temporary directory.'
                );
            }
        }
    }

    private function path_is_within(
        string $path,
        string $root
    ): bool {
        $normalized_path = trailingslashit(
            wp_normalize_path($path)
        );
        $normalized_root = trailingslashit(
            wp_normalize_path($root)
        );

        return str_starts_with(
            $normalized_path,
            $normalized_root
        );
    }

    private function write_complete_file(
        string $path,
        string $contents
    ): void {
        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw new ExportException(
                'Slim Volume could not open private temporary storage for the discography export.'
            );
        }

        $length = strlen($contents);
        $offset = 0;

        try {
            while ($offset < $length) {
                $written = fwrite(
                    $handle,
                    substr($contents, $offset)
                );

                if ($written === false || $written === 0) {
                    throw new ExportException(
                        'Slim Volume could not complete the discography export artifact.'
                    );
                }

                $offset += $written;
            }

            if (! fflush($handle)) {
                throw new ExportException(
                    'Slim Volume could not finalize the discography export artifact.'
                );
            }
        } finally {
            fclose($handle);
        }
    }
}

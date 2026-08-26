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

    private \WP_Filesystem_Direct $filesystem;

    public function __construct(
        ?\WP_Filesystem_Direct $filesystem = null
    ) {
        $this->filesystem = $filesystem ?? self::create_filesystem();
    }

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
                'Slim Volume could not encode the discography export as valid JSON.'
            );
        }

        /*
         * A final newline keeps the downloaded JSON friendly to command-line
         * tooling without changing the JSON document itself.
         */
        $json .= "\n";

        $path = $this->create_private_temp_file();

        try {
            if (
                ! $this->filesystem->put_contents(
                    $path,
                    $json,
                    0600
                )
            ) {
                throw new ExportException(
                    'Slim Volume could not complete the discography export artifact.'
                );
            }

            $size = $this->filesystem->size($path);

            if (
                ! is_int($size)
                || $size !== strlen($json)
            ) {
                throw new ExportException(
                    'Slim Volume could not verify the completed export artifact.'
                );
            }

            return new ExportArtifact(
                $path,
                $size,
                $this->filesystem
            );
        } catch (\Throwable $exception) {
            if ($this->filesystem->exists($path)) {
                $this->filesystem->delete($path);
            }

            if ($exception instanceof ExportException) {
                throw $exception;
            }

            throw new ExportException(
                'Slim Volume could not create the private export artifact.'
            );
        }
    }

    private function create_private_temp_file(): string
    {
        require_once ABSPATH . 'wp-admin/includes/file.php';

        $temp_dir = get_temp_dir();

        if (
            ! is_string($temp_dir)
            || trim($temp_dir) === ''
            || ! $this->filesystem->is_dir($temp_dir)
            || ! $this->filesystem->is_writable($temp_dir)
        ) {
            throw new ExportException(
                'A writable private temporary directory is unavailable for discography export.'
            );
        }

        $resolved_temp_dir = realpath($temp_dir);

        if (
            ! is_string($resolved_temp_dir)
            || $resolved_temp_dir === ''
        ) {
            throw new ExportException(
                'Slim Volume could not resolve the private temporary directory.'
            );
        }

        $this->assert_non_public_directory(
            $resolved_temp_dir
        );

        $path = wp_tempnam(
            'slim-volume-discography.json',
            trailingslashit($resolved_temp_dir)
        );

        if (
            ! is_string($path)
            || $path === ''
        ) {
            throw new ExportException(
                'Slim Volume could not allocate private temporary storage for the discography export.'
            );
        }

        /*
         * wp_tempnam() creates the file before we write it. Tighten its
         * permissions where supported; put_contents() below also requests
         * mode 0600 for the completed artifact.
         */
        $this->filesystem->chmod(
            $path,
            0600
        );

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

    private static function create_filesystem(): \WP_Filesystem_Direct
    {
        require_once ABSPATH
            . 'wp-admin/includes/class-wp-filesystem-base.php';
        require_once ABSPATH
            . 'wp-admin/includes/class-wp-filesystem-direct.php';

        return new \WP_Filesystem_Direct(null);
    }
}

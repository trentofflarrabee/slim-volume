<?php

declare(strict_types=1);

namespace SlimVolume\Export;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Represents a fully generated private export artifact.
 *
 * Successful construction means the JSON document was completely encoded and
 * written before any download response needs to begin.
 */
final class ExportArtifact
{
    private string $path;
    private int $size;
    private bool $deleted = false;
    private \WP_Filesystem_Direct $filesystem;

    public function __construct(
        string $path,
        int $size,
        ?\WP_Filesystem_Direct $filesystem = null
    ) {
        if ($path === '' || $size < 0) {
            throw new \InvalidArgumentException(
                'Slim Volume received an invalid export artifact.'
            );
        }

        $this->path = $path;
        $this->size = $size;
        $this->filesystem = $filesystem
            ?? self::create_filesystem();
    }

    public function __destruct()
    {
        $this->delete();
    }

    public function path(): string
    {
        return $this->path;
    }

    public function size(): int
    {
        return $this->size;
    }

    public function exists(): bool
    {
        return ! $this->deleted
            && $this->filesystem->is_file($this->path)
            && $this->filesystem->is_readable($this->path);
    }

    /**
     * Output the already completed artifact.
     *
     * Response authorization and HTTP headers deliberately remain the admin
     * handler's responsibility.
     */
    public function stream(): void
    {
        if (! $this->exists()) {
            throw new ExportException(
                'The completed Slim Volume export artifact is unavailable.'
            );
        }

        $contents = $this->filesystem->get_contents(
            $this->path
        );

        if (! is_string($contents)) {
            throw new ExportException(
                'Slim Volume could not read the completed export artifact for delivery.'
            );
        }

        /*
         * The artifact is already validated JSON generated exclusively by
         * Slim Volume. Escaping here would alter the JSON bytes and corrupt
         * the download response.
         */
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $contents;
    }

    public function delete(): void
    {
        if ($this->deleted) {
            return;
        }

        if ($this->filesystem->exists($this->path)) {
            if (! $this->filesystem->delete($this->path)) {
                return;
            }
        }

        $this->deleted = true;
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

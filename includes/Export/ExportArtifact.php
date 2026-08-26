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

    public function __construct(
        string $path,
        int $size
    ) {
        if ($path === '' || $size < 0) {
            throw new \InvalidArgumentException(
                'Slim Volume received an invalid export artifact.'
            );
        }

        $this->path = $path;
        $this->size = $size;
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
            && is_file($this->path)
            && is_readable($this->path);
    }

    /**
     * Stream the already completed artifact to the current output buffer.
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

        $handle = @fopen($this->path, 'rb');

        if ($handle === false) {
            throw new ExportException(
                'Slim Volume could not open the completed export artifact for delivery.'
            );
        }

        try {
            while (! feof($handle)) {
                $chunk = @fread($handle, 1024 * 1024);

                if ($chunk === false) {
                    throw new ExportException(
                        'Slim Volume could not read the completed export artifact for delivery.'
                    );
                }

                if ($chunk === '') {
                    continue;
                }

                echo $chunk;
            }
        } finally {
            fclose($handle);
        }
    }

    public function delete(): void
    {
        if ($this->deleted) {
            return;
        }

        if (is_file($this->path)) {
            if (! @unlink($this->path)) {
                return;
            }
        }

        $this->deleted = true;
    }
}

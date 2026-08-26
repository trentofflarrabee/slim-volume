<?php

declare(strict_types=1);

namespace SlimVolume\Export;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Maps WordPress-local source IDs to opaque references used only in one export.
 */
final class ReferenceIndex
{
    /**
     * @var array<int,string>
     */
    private array $artist_refs = [];

    /**
     * @var array<int,string>
     */
    private array $release_refs = [];

    /**
     * @var array<int,string>
     */
    private array $track_refs = [];

    /**
     * @param array<int,int|string> $artist_ids
     * @param array<int,int|string> $release_ids
     * @param array<int,int|string> $track_ids
     */
    public static function from_source_ids(
        array $artist_ids,
        array $release_ids,
        array $track_ids
    ): self {
        $index = new self();

        $index->artist_refs = self::build_map($artist_ids, 'artist');
        $index->release_refs = self::build_map($release_ids, 'release');
        $index->track_refs = self::build_map($track_ids, 'track');

        return $index;
    }

    public function artist_ref(int $term_id): ?string
    {
        return $this->artist_refs[$term_id] ?? null;
    }

    public function release_ref(int $post_id): ?string
    {
        return $this->release_refs[$post_id] ?? null;
    }

    public function track_ref(int $post_id): ?string
    {
        return $this->track_refs[$post_id] ?? null;
    }

    /**
     * @return array<int,int>
     */
    public function artist_source_ids(): array
    {
        return array_keys($this->artist_refs);
    }

    /**
     * @return array<int,int>
     */
    public function release_source_ids(): array
    {
        return array_keys($this->release_refs);
    }

    /**
     * @return array<int,int>
     */
    public function track_source_ids(): array
    {
        return array_keys($this->track_refs);
    }

    public function artist_count(): int
    {
        return count($this->artist_refs);
    }

    public function release_count(): int
    {
        return count($this->release_refs);
    }

    public function track_count(): int
    {
        return count($this->track_refs);
    }

    /**
     * @param array<int,int|string> $source_ids
     * @return array<int,string>
     */
    private static function build_map(array $source_ids, string $prefix): array
    {
        $normalized = [];

        foreach ($source_ids as $source_id) {
            $source_id = absint($source_id);

            if ($source_id <= 0) {
                continue;
            }

            $normalized[$source_id] = $source_id;
        }

        ksort($normalized, SORT_NUMERIC);

        $refs = [];
        $position = 1;

        foreach ($normalized as $source_id) {
            $refs[$source_id] = $prefix . '-' . $position;
            $position++;
        }

        return $refs;
    }
}

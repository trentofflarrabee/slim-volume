<?php

declare(strict_types=1);

namespace SlimVolume\Export;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Maps WordPress editorial lifecycle state into the frozen v1 timestamp
 * semantics without assigning meaning to unknown/custom statuses.
 */
final class EditorialLifecycle
{
    /**
     * @var array<int,string>
     */
    private const KNOWN_STATUSES = [
        'publish',
        'draft',
        'private',
        'pending',
        'future',
    ];

    private WarningCollector $warnings;

    public function __construct(WarningCollector $warnings)
    {
        $this->warnings = $warnings;
    }

    /**
     * @return array{
     *   publishedAt:?string,
     *   scheduledAt:?string
     * }
     */
    public function map(
        int $post_id,
        string $status,
        string $object_ref
    ): array {
        if (! in_array($status, self::KNOWN_STATUSES, true)) {
            $this->warnings->add(
                'unsupported_post_status',
                $object_ref,
                'The source object uses a WordPress post status that Slim Volume does not assign portable lifecycle semantics.'
            );

            return [
                'publishedAt' => null,
                'scheduledAt' => null,
            ];
        }

        if ($status === 'draft' || $status === 'pending') {
            return [
                'publishedAt' => null,
                'scheduledAt' => null,
            ];
        }

        $timestamp = $this->get_gmt_timestamp(
            $post_id,
            $object_ref
        );

        if ($status === 'future') {
            return [
                'publishedAt' => null,
                'scheduledAt' => $timestamp,
            ];
        }

        return [
            'publishedAt' => $timestamp,
            'scheduledAt' => null,
        ];
    }

    private function get_gmt_timestamp(
        int $post_id,
        string $object_ref
    ): ?string {
        $datetime = get_post_datetime(
            $post_id,
            'date',
            'gmt'
        );

        if (! $datetime instanceof \DateTimeImmutable) {
            $this->warnings->add(
                'invalid_publication_timestamp',
                $object_ref,
                'The authoritative WordPress GMT publication or scheduling date could not be converted into a valid UTC timestamp.'
            );

            return null;
        }

        return $datetime
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s\Z');
    }
}

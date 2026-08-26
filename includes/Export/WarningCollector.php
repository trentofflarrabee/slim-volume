<?php

declare(strict_types=1);

namespace SlimVolume\Export;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Collects portable export warnings in the stable v1 warning shape.
 */
final class WarningCollector
{
    /**
     * @var array<int,array{code:string,objectRef:?string,message:string}>
     */
    private array $warnings = [];

    public function add(
        string $code,
        ?string $object_ref,
        string $message
    ): void {
        $code = trim($code);
        $message = trim($message);

        if ($code === '') {
            throw new \InvalidArgumentException(
                'Export warning codes must not be empty.'
            );
        }

        if ($message === '') {
            throw new \InvalidArgumentException(
                'Export warning messages must not be empty.'
            );
        }

        if ($object_ref !== null && trim($object_ref) === '') {
            throw new \InvalidArgumentException(
                'Export warning object references must be non-empty strings or null.'
            );
        }

        $this->warnings[] = [
            'code'      => $code,
            'objectRef' => $object_ref,
            'message'   => $message,
        ];
    }

    /**
     * @return array<int,array{code:string,objectRef:?string,message:string}>
     */
    public function all(): array
    {
        return array_values($this->warnings);
    }

    public function count(): int
    {
        return count($this->warnings);
    }

    public function is_empty(): bool
    {
        return $this->warnings === [];
    }
}

<?php

declare(strict_types=1);

namespace SlimVolume\Export;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Converts Slim Volume's canonical newline-delimited official-profile storage
 * into the ordered portable v1 URL-string array.
 */
final class OfficialProfiles
{
    /**
     * @return array<int,string>
     */
    public static function from_storage(string $value): array
    {
        if ($value === '') {
            return [];
        }

        $lines = preg_split('/\R+/', $value);

        if (! is_array($lines)) {
            return [];
        }

        $profiles = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $profiles[] = $line;
        }

        return array_values($profiles);
    }
}

<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

final class ApiTimestamp
{
    /**
     * ISO-8601 UTC (`2026-09-13T06:15:00Z`) per docs/08.
     */
    public static function utc(?DateTimeInterface $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return CarbonImmutable::instance($value)
            ->utc()
            ->format('Y-m-d\TH:i:s\Z');
    }
}

<?php

namespace App\Support\Reporting;

use Carbon\CarbonImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Inclusive local calendar range converted to UTC query bounds (docs/06 DP-07).
 */
final readonly class ReportWindow
{
    public const DEFAULT_TIMEZONE = 'Asia/Jakarta';

    public const MAX_INCLUSIVE_DAYS = 93;

    public function __construct(
        public CarbonImmutable $startUtc,
        public CarbonImmutable $endUtc,
        public string $timezone,
        public string $fromLocal,
        public string $toLocal,
    ) {}

    public static function forDate(string $date, string $timezone): self
    {
        return self::forRange($date, $date, $timezone);
    }

    public static function forRange(string $from, string $to, string $timezone): self
    {
        if (! self::isDate($from) || ! self::isDate($to)) {
            throw new InvalidArgumentException('Report dates must use Y-m-d.');
        }

        if (! self::isTimezone($timezone)) {
            throw new InvalidArgumentException('Invalid reporting timezone.');
        }

        $tz = new DateTimeZone($timezone);
        $startLocal = CarbonImmutable::createFromFormat('Y-m-d', $from, $tz);
        $endLocal = CarbonImmutable::createFromFormat('Y-m-d', $to, $tz);

        if ($startLocal === false || $endLocal === false) {
            throw new InvalidArgumentException('Report dates must use Y-m-d.');
        }

        $startLocal = $startLocal->startOfDay();
        $endLocal = $endLocal->endOfDay();

        if ($endLocal->lt($startLocal)) {
            throw new InvalidArgumentException('Report end date must be on or after start date.');
        }

        $inclusiveDays = (int) $startLocal->diffInDays($endLocal->startOfDay()) + 1;
        if ($inclusiveDays > self::MAX_INCLUSIVE_DAYS) {
            throw new InvalidArgumentException('Report range cannot exceed '.self::MAX_INCLUSIVE_DAYS.' days.');
        }

        return new self(
            startUtc: $startLocal->utc(),
            endUtc: $endLocal->utc(),
            timezone: $timezone,
            fromLocal: $from,
            toLocal: $to,
        );
    }

    public function isSingleDay(): bool
    {
        return $this->fromLocal === $this->toLocal;
    }

    private static function isDate(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
    }

    private static function isTimezone(string $timezone): bool
    {
        try {
            new DateTimeZone($timezone);
        } catch (\Exception) {
            return false;
        }

        return true;
    }
}

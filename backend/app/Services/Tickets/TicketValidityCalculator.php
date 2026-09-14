<?php

namespace App\Services\Tickets;

use App\Enums\ValidityType;
use App\Models\Destination;
use App\Models\TicketType;
use Carbon\CarbonImmutable;
use DateTimeInterface;

final class TicketValidityCalculator
{
    /**
     * @return array{start: CarbonImmutable, end: CarbonImmutable}
     */
    public function window(
        TicketType $type,
        Destination $destination,
        DateTimeInterface $issuedAt,
    ): array {
        $tz = is_string($destination->timezone) && $destination->timezone !== ''
            ? $destination->timezone
            : 'Asia/Jakarta';

        $issuedLocal = CarbonImmutable::instance($issuedAt)->timezone($tz);
        $from = $this->parseTime($type->valid_from_time);
        $until = $this->parseTime($type->valid_until_time);

        return match ($type->validity_type) {
            ValidityType::DaysFromIssue => $this->daysFromIssue(
                $issuedLocal,
                $from,
                $until,
                $type->validity_days,
            ),
            ValidityType::DatetimeWindow, ValidityType::SameDay => $this->sameDay(
                $issuedLocal,
                $from,
                $until,
            ),
        };
    }

    /**
     * @return array{start: CarbonImmutable, end: CarbonImmutable}
     */
    private function sameDay(CarbonImmutable $issuedLocal, ?string $from, ?string $until): array
    {
        $start = $this->atTime($issuedLocal, $from, startOfDay: true);
        $end = $this->atTime($issuedLocal, $until, startOfDay: false);

        if ($end->lte($start)) {
            $end = $end->addDay();
        }

        return [
            'start' => $start->utc(),
            'end' => $end->utc(),
        ];
    }

    /**
     * @return array{start: CarbonImmutable, end: CarbonImmutable}
     */
    private function daysFromIssue(
        CarbonImmutable $issuedLocal,
        ?string $from,
        ?string $until,
        ?int $validityDays,
    ): array {
        $days = max(1, $validityDays ?? 1);
        $start = $this->atTime($issuedLocal, $from, startOfDay: true);
        $end = $this->atTime($issuedLocal->addDays($days - 1), $until, startOfDay: false);

        if ($end->lte($start)) {
            $end = $end->addDay();
        }

        return [
            'start' => $start->utc(),
            'end' => $end->utc(),
        ];
    }

    private function atTime(CarbonImmutable $day, ?string $time, bool $startOfDay): CarbonImmutable
    {
        if ($time === null) {
            return $startOfDay ? $day->startOfDay() : $day->endOfDay()->microsecond(0);
        }

        $parts = array_map('intval', explode(':', $time));

        return $day->setTime($parts[0] ?? 0, $parts[1] ?? 0, $parts[2] ?? 0);
    }

    private function parseTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('H:i:s');
        }

        if (is_string($value) && preg_match('/^\d{2}:\d{2}/', $value) === 1) {
            return strlen($value) >= 8 ? substr($value, 0, 8) : $value.':00';
        }

        return null;
    }
}

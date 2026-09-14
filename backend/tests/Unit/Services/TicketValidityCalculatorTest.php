<?php

namespace Tests\Unit\Services;

use App\Enums\ValidityType;
use App\Models\Destination;
use App\Models\TicketType;
use App\Services\Tickets\TicketValidityCalculator;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class TicketValidityCalculatorTest extends TestCase
{
    public function test_same_day_uses_destination_timezone(): void
    {
        $destination = new Destination(['timezone' => 'Asia/Jakarta']);
        $type = new TicketType(['validity_type' => ValidityType::SameDay]);
        $issuedAt = CarbonImmutable::parse('2026-09-13T03:00:00Z');

        $window = (new TicketValidityCalculator)->window($type, $destination, $issuedAt);

        $this->assertSame('2026-09-12T17:00:00Z', $window['start']->format('Y-m-d\TH:i:s\Z'));
        $this->assertSame('2026-09-13T16:59:59Z', $window['end']->format('Y-m-d\TH:i:s\Z'));
    }

    public function test_days_from_issue_spans_configured_days(): void
    {
        $destination = new Destination(['timezone' => 'Asia/Jakarta']);
        $type = new TicketType([
            'validity_type' => ValidityType::DaysFromIssue,
            'validity_days' => 3,
        ]);
        $issuedAt = CarbonImmutable::parse('2026-09-13T03:00:00Z');

        $window = (new TicketValidityCalculator)->window($type, $destination, $issuedAt);

        $this->assertSame('2026-09-12T17:00:00Z', $window['start']->format('Y-m-d\TH:i:s\Z'));
        $this->assertSame('2026-09-15T16:59:59Z', $window['end']->format('Y-m-d\TH:i:s\Z'));
    }

    public function test_datetime_window_uses_daily_times(): void
    {
        $destination = new Destination(['timezone' => 'Asia/Jakarta']);
        $type = new TicketType([
            'validity_type' => ValidityType::DatetimeWindow,
            'valid_from_time' => '08:00:00',
            'valid_until_time' => '17:00:00',
        ]);
        $issuedAt = CarbonImmutable::parse('2026-09-13T03:00:00Z');

        $window = (new TicketValidityCalculator)->window($type, $destination, $issuedAt);

        $this->assertSame('2026-09-13T01:00:00Z', $window['start']->format('Y-m-d\TH:i:s\Z'));
        $this->assertSame('2026-09-13T10:00:00Z', $window['end']->format('Y-m-d\TH:i:s\Z'));
    }
}

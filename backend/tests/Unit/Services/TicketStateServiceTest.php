<?php

namespace Tests\Unit\Services;

use App\Enums\TicketStatus;
use App\Exceptions\DomainException;
use App\Models\Ticket;
use App\Services\Tickets\TicketStateService;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class TicketStateServiceTest extends TestCase
{
    public function test_now_inside_window_is_active(): void
    {
        $service = new TicketStateService;
        $now = CarbonImmutable::parse('2026-09-13T10:00:00Z');

        $status = $service->initialStatus(
            CarbonImmutable::parse('2026-09-13T00:00:00Z'),
            CarbonImmutable::parse('2026-09-13T16:59:59Z'),
            $now,
        );

        $this->assertSame(TicketStatus::Active, $status);
    }

    public function test_now_before_window_is_issued(): void
    {
        $service = new TicketStateService;

        $status = $service->initialStatus(
            CarbonImmutable::parse('2026-09-14T00:00:00Z'),
            CarbonImmutable::parse('2026-09-14T16:59:59Z'),
            CarbonImmutable::parse('2026-09-13T10:00:00Z'),
        );

        $this->assertSame(TicketStatus::Issued, $status);
    }

    public function test_now_after_window_is_expired(): void
    {
        $service = new TicketStateService;

        $status = $service->initialStatus(
            CarbonImmutable::parse('2026-09-12T00:00:00Z'),
            CarbonImmutable::parse('2026-09-12T16:59:59Z'),
            CarbonImmutable::parse('2026-09-13T10:00:00Z'),
        );

        $this->assertSame(TicketStatus::Expired, $status);
    }

    public function test_active_can_move_to_used(): void
    {
        $ticket = new Ticket(['status' => TicketStatus::Active]);
        $service = new TicketStateService;

        $service->transition($ticket, TicketStatus::Used);

        $this->assertSame(TicketStatus::Used, $ticket->status);
    }

    public function test_used_cannot_move_to_active(): void
    {
        $ticket = new Ticket(['status' => TicketStatus::Used]);
        $service = new TicketStateService;

        $this->expectException(DomainException::class);
        $service->transition($ticket, TicketStatus::Active);
    }

    public function test_pending_and_active_can_move_to_refunded(): void
    {
        $service = new TicketStateService;

        $pending = new Ticket(['status' => TicketStatus::Pending]);
        $service->transition($pending, TicketStatus::Refunded);
        $this->assertSame(TicketStatus::Refunded, $pending->status);

        $active = new Ticket(['status' => TicketStatus::Active]);
        $service->transition($active, TicketStatus::Refunded);
        $this->assertSame(TicketStatus::Refunded, $active->status);
    }

    public function test_used_cannot_move_to_refunded(): void
    {
        $ticket = new Ticket(['status' => TicketStatus::Used]);
        $service = new TicketStateService;

        $this->assertFalse($service->canTransition($ticket, TicketStatus::Refunded));
    }
}

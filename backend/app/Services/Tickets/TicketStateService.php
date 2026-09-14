<?php

namespace App\Services\Tickets;

use App\Enums\TicketStatus;
use App\Exceptions\DomainException;
use App\Models\Ticket;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Authoritative ticket status transitions for kiosk and assisted channels.
 */
final class TicketStateService
{
    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED = [
        TicketStatus::Pending->value => [
            TicketStatus::Issued->value,
            TicketStatus::Active->value,
            TicketStatus::Cancelled->value,
            TicketStatus::Expired->value,
            TicketStatus::Refunded->value,
        ],
        TicketStatus::Issued->value => [
            TicketStatus::Active->value,
            TicketStatus::Cancelled->value,
            TicketStatus::Refunded->value,
            TicketStatus::Expired->value,
        ],
        TicketStatus::Active->value => [
            TicketStatus::Used->value,
            TicketStatus::Expired->value,
            TicketStatus::Cancelled->value,
            TicketStatus::Refunded->value,
        ],
    ];

    public function initialStatus(
        DateTimeInterface $validStart,
        DateTimeInterface $validEnd,
        ?DateTimeInterface $now = null,
    ): TicketStatus {
        $now = CarbonImmutable::instance($now ?? now());
        $start = CarbonImmutable::instance($validStart);
        $end = CarbonImmutable::instance($validEnd);

        if ($now->gt($end)) {
            return TicketStatus::Expired;
        }

        if ($now->lt($start)) {
            return TicketStatus::Issued;
        }

        return TicketStatus::Active;
    }

    public function canTransition(Ticket $ticket, TicketStatus $to): bool
    {
        $from = $ticket->status instanceof TicketStatus
            ? $ticket->status->value
            : (string) $ticket->status;

        if ($from === $to->value) {
            return true;
        }

        return in_array($to->value, self::ALLOWED[$from] ?? [], true);
    }

    public function transition(Ticket $ticket, TicketStatus $to): Ticket
    {
        if ($ticket->status === $to) {
            return $ticket;
        }

        if (! $this->canTransition($ticket, $to)) {
            $from = $ticket->status instanceof TicketStatus
                ? $ticket->status->value
                : (string) $ticket->status;

            throw new DomainException(
                'order.state_conflict',
                "Ticket cannot move from {$from} to {$to->value}.",
                409,
            );
        }

        $ticket->status = $to;

        return $ticket;
    }
}

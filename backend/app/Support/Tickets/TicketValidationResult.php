<?php

namespace App\Support\Tickets;

use App\Enums\TicketDenyCode;
use App\Enums\TicketStatus;
use App\Enums\TicketValidateResult;
use App\Models\Ticket;
use App\Support\ApiTimestamp;

final class TicketValidationResult
{
    public function __construct(
        public readonly TicketValidateResult $result,
        public readonly ?TicketDenyCode $reasonCode = null,
        public readonly ?Ticket $ticket = null,
    ) {}

    public static function allow(Ticket $ticket): self
    {
        return new self(TicketValidateResult::Allow, null, $ticket);
    }

    public static function deny(TicketDenyCode $reason, ?Ticket $ticket = null): self
    {
        return new self(TicketValidateResult::Deny, $reason, $ticket);
    }

    public function allowed(): bool
    {
        return $this->result === TicketValidateResult::Allow;
    }

    /**
     * Limited ticket fields for gate clients — never includes QR material.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $ticket = $this->ticket;

        return [
            'result' => $this->result->value,
            'reason_code' => $this->reasonCode?->value,
            'ticket' => $ticket === null ? null : [
                'ticket_code' => $ticket->ticket_code,
                'status' => $ticket->status instanceof TicketStatus
                    ? $ticket->status->value
                    : $ticket->status,
                'destination_id' => $ticket->destination_id,
                'valid_start_at' => ApiTimestamp::utc($ticket->valid_start_at),
                'valid_end_at' => ApiTimestamp::utc($ticket->valid_end_at),
            ],
        ];
    }
}

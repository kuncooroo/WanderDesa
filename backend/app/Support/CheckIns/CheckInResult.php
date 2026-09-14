<?php

namespace App\Support\CheckIns;

use App\Enums\TicketDenyCode;
use App\Enums\TicketStatus;
use App\Enums\TicketValidateResult;
use App\Models\CheckIn;
use App\Models\Ticket;
use App\Support\ApiTimestamp;

final class CheckInResult
{
    public function __construct(
        public readonly TicketValidateResult $result,
        public readonly ?TicketDenyCode $reasonCode = null,
        public readonly ?Ticket $ticket = null,
        public readonly ?CheckIn $checkIn = null,
    ) {}

    public static function allow(Ticket $ticket, CheckIn $checkIn): self
    {
        return new self(TicketValidateResult::Allow, null, $ticket, $checkIn);
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
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $ticket = $this->ticket;
        $checkIn = $this->checkIn;

        return [
            'result' => $this->result->value,
            'reason_code' => $this->reasonCode?->value,
            'check_in' => $checkIn === null ? null : [
                'id' => $checkIn->id,
                'checked_in_at' => ApiTimestamp::utc($checkIn->checked_in_at),
            ],
            'ticket' => $ticket === null ? null : [
                'code' => $ticket->ticket_code,
                'status' => $ticket->status instanceof TicketStatus
                    ? $ticket->status->value
                    : $ticket->status,
            ],
        ];
    }
}

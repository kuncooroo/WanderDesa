<?php

namespace App\Support\Tickets;

use App\Enums\Channel;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Support\ApiTimestamp;

final class TicketPrintPayload
{
    /**
     * @return array<string, mixed>
     */
    public static function fromTicket(Ticket $ticket): array
    {
        $ticket->loadMissing(['destination', 'ticketType']);

        $status = $ticket->status;
        $channel = $ticket->channel;

        return [
            'destination' => [
                'id' => $ticket->destination_id,
                'code' => $ticket->destination?->code,
                'name' => $ticket->destination?->name,
            ],
            'ticket_type' => [
                'id' => $ticket->ticket_type_id,
                'code' => $ticket->ticketType?->code ?? $ticket->orderItem?->ticket_type_code,
                'name' => $ticket->ticketType?->name ?? $ticket->orderItem?->ticket_type_name,
            ],
            'ticket_code' => $ticket->ticket_code,
            'qr_payload' => $ticket->qr_payload,
            'status' => $status instanceof TicketStatus ? $status->value : $status,
            'channel' => $channel instanceof Channel ? $channel->value : $channel,
            'valid_start_at' => ApiTimestamp::utc($ticket->valid_start_at),
            'valid_end_at' => ApiTimestamp::utc($ticket->valid_end_at),
            'issued_at' => ApiTimestamp::utc($ticket->issued_at),
        ];
    }
}

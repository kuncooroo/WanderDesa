<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\Channel;
use App\Enums\TicketStatus;
use App\Models\Device;
use App\Models\Ticket;
use App\Models\User;
use App\Support\ApiTimestamp;
use App\Support\Tickets\TicketAccess;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Ticket
 */
class TicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->resource->status;
        $channel = $this->resource->channel;
        $principal = $request->user();
        $includeQr = $principal instanceof Device || $principal instanceof User
            ? TicketAccess::includeQrPayload($principal, $this->resource)
            : false;

        return [
            'id' => $this->resource->id,
            'ticket_code' => $this->resource->ticket_code,
            'order_id' => $this->resource->order_id,
            'destination_id' => $this->resource->destination_id,
            'ticket_type_id' => $this->resource->ticket_type_id,
            'channel' => $channel instanceof Channel ? $channel->value : $channel,
            'status' => $status instanceof TicketStatus ? $status->value : $status,
            'valid_start_at' => ApiTimestamp::utc($this->resource->valid_start_at),
            'valid_end_at' => ApiTimestamp::utc($this->resource->valid_end_at),
            'issued_at' => ApiTimestamp::utc($this->resource->issued_at),
            'qr_payload' => $this->when($includeQr, $this->resource->qr_payload),
        ];
    }
}

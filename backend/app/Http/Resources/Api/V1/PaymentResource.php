<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Support\ApiTimestamp;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->resource->status;
        $method = $this->resource->method;
        $order = $this->resource->relationLoaded('order') ? $this->resource->order : null;
        $orderStatus = $order?->status;

        $ticketsIssued = false;
        if ($this->resource->relationLoaded('tickets')) {
            $ticketsIssued = $this->resource->tickets->isNotEmpty();
        }

        return [
            'id' => $this->resource->id,
            'payment_number' => $this->resource->payment_number,
            'order_id' => $this->resource->order_id,
            'status' => $status instanceof PaymentStatus ? $status->value : $status,
            'method' => $method instanceof PaymentMethod ? $method->value : $method,
            'provider' => $this->resource->provider,
            'amount' => $this->resource->amount,
            'currency' => $this->resource->currency,
            'refund_amount' => $this->refundAmount(),
            'paid_at' => ApiTimestamp::utc($this->resource->paid_at),
            'failed_at' => ApiTimestamp::utc($this->resource->failed_at),
            'refunded_at' => ApiTimestamp::utc($this->resource->refunded_at),
            'order_status' => $orderStatus instanceof OrderStatus
                ? $orderStatus->value
                : $orderStatus,
            'tickets_issued' => $ticketsIssued,
            'tickets' => $this->whenLoaded('tickets', function () {
                return $this->resource->tickets->map(static function ($ticket): array {
                    return [
                        'id' => $ticket->id,
                        'ticket_code' => $ticket->ticket_code,
                        'status' => $ticket->status?->value ?? $ticket->status,
                    ];
                })->values()->all();
            }, []),
        ];
    }

    private function refundAmount(): ?int
    {
        $status = $this->resource->status;
        $isRefunded = $status instanceof PaymentStatus
            ? $status === PaymentStatus::Refunded
            : $status === PaymentStatus::Refunded->value;

        if (! $isRefunded) {
            return null;
        }

        $meta = $this->resource->metadata_json ?? [];
        if (isset($meta['refund_amount']) && is_numeric($meta['refund_amount'])) {
            return (int) $meta['refund_amount'];
        }

        return (int) $this->resource->amount;
    }
}

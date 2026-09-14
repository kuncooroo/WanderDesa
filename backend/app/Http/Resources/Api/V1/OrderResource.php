<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\Channel;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Support\ApiTimestamp;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $channel = $this->resource->channel;
        $status = $this->resource->status;

        return [
            'id' => $this->resource->id,
            'order_number' => $this->resource->order_number,
            'destination_id' => $this->resource->destination_id,
            'channel' => $channel instanceof Channel ? $channel->value : $channel,
            'status' => $status instanceof OrderStatus ? $status->value : $status,
            'visitor_id' => $this->resource->visitor_id,
            'device_id' => $this->resource->device_id,
            'created_by_user_id' => $this->resource->created_by_user_id,
            'currency' => $this->resource->currency,
            'subtotal' => $this->resource->subtotal,
            'discount_total' => $this->resource->discount_total,
            'tax_total' => $this->resource->tax_total,
            'service_fee_total' => $this->resource->service_fee_total,
            'grand_total' => $this->resource->grand_total,
            'customer_note' => $this->resource->customer_note,
            'expires_at' => ApiTimestamp::utc($this->resource->expires_at),
            'paid_at' => ApiTimestamp::utc($this->resource->paid_at),
            'cancelled_at' => ApiTimestamp::utc($this->resource->cancelled_at),
            'expired_at' => ApiTimestamp::utc($this->resource->expired_at),
            'refunded_at' => ApiTimestamp::utc($this->resource->refunded_at),
            'created_at' => ApiTimestamp::utc($this->resource->created_at),
            'items' => $this->whenLoaded('items', function () {
                return $this->resource->items->map(static function ($item): array {
                    return [
                        'id' => $item->id,
                        'ticket_type_id' => $item->ticket_type_id,
                        'ticket_type_code' => $item->ticket_type_code,
                        'ticket_type_name' => $item->ticket_type_name,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'tax_amount' => $item->tax_amount,
                        'service_fee_amount' => $item->service_fee_amount,
                        'line_subtotal' => $item->line_subtotal,
                        'line_tax_total' => $item->line_tax_total,
                        'line_service_fee_total' => $item->line_service_fee_total,
                        'line_grand_total' => $item->line_grand_total,
                    ];
                })->values()->all();
            }, []),
            'payments' => $this->whenLoaded('payments', function () {
                return $this->resource->payments->map(static function ($payment): array {
                    $paymentStatus = $payment->status;
                    $method = $payment->method;

                    return [
                        'id' => $payment->id,
                        'payment_number' => $payment->payment_number,
                        'status' => $paymentStatus instanceof PaymentStatus ? $paymentStatus->value : $paymentStatus,
                        'method' => $method instanceof PaymentMethod ? $method->value : $method,
                        'amount' => $payment->amount,
                        'currency' => $payment->currency,
                        'paid_at' => ApiTimestamp::utc($payment->paid_at),
                    ];
                })->values()->all();
            }, []),
            'tickets' => $this->whenLoaded('tickets', function () use ($request) {
                return TicketResource::collection($this->resource->tickets)->resolve($request);
            }, []),
        ];
    }
}

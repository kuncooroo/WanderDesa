<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read array{
 *     currency: string,
 *     items: list<array<string, mixed>>,
 *     subtotal: int,
 *     discount_total: int,
 *     tax_total: int,
 *     service_fee_total: int,
 *     grand_total: int
 * } $resource
 */
class QuoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $quote */
        $quote = $this->resource;

        return [
            'currency' => $quote['currency'],
            'items' => array_map(
                static fn (array $item): array => [
                    'ticket_type_id' => $item['ticket_type_id'],
                    'ticket_type_code' => $item['ticket_type_code'],
                    'ticket_type_name' => $item['ticket_type_name'],
                    'quantity' => $item['quantity'],
                    'visit_date' => $item['visit_date'],
                    'unit_price' => $item['unit_price'],
                    'tax_amount' => $item['tax_amount'],
                    'service_fee_amount' => $item['service_fee_amount'],
                    'line_subtotal' => $item['line_subtotal'],
                    'line_tax_total' => $item['line_tax_total'],
                    'line_service_fee_total' => $item['line_service_fee_total'],
                    'line_grand_total' => $item['line_grand_total'],
                ],
                $quote['items'],
            ),
            'subtotal' => $quote['subtotal'],
            'discount_total' => $quote['discount_total'],
            'tax_total' => $quote['tax_total'],
            'service_fee_total' => $quote['service_fee_total'],
            'grand_total' => $quote['grand_total'],
        ];
    }
}

<?php

namespace App\Services\Pricing;

use App\Models\TicketType;
use InvalidArgumentException;

/**
 * Authoritative pricing engine for kiosk and assisted channels.
 * Always recalculates from DB ticket type rows — never trusts client money.
 */
final class PricingService
{
    /**
     * @param  list<array{ticket_type: TicketType, quantity: int, visit_date?: string|null}>  $lines
     * @return array{
     *     currency: string,
     *     items: list<array{
     *         ticket_type_id: int,
     *         ticket_type_code: string,
     *         ticket_type_name: string,
     *         quantity: int,
     *         visit_date: string|null,
     *         unit_price: int,
     *         tax_amount: int,
     *         service_fee_amount: int,
     *         line_subtotal: int,
     *         line_tax_total: int,
     *         line_service_fee_total: int,
     *         line_grand_total: int
     *     }>,
     *     subtotal: int,
     *     discount_total: int,
     *     tax_total: int,
     *     service_fee_total: int,
     *     grand_total: int
     * }
     */
    public function quote(array $lines): array
    {
        if ($lines === []) {
            throw new InvalidArgumentException('Quote requires at least one line.');
        }

        $items = [];
        $subtotal = 0;
        $taxTotal = 0;
        $serviceFeeTotal = 0;

        foreach ($lines as $line) {
            $ticketType = $line['ticket_type'];
            $quantity = (int) $line['quantity'];

            if ($quantity < 1) {
                throw new InvalidArgumentException('Quantity must be at least 1.');
            }

            $unitPrice = (int) $ticketType->unit_price;
            $taxAmount = (int) $ticketType->tax_amount;
            $serviceFeeAmount = (int) $ticketType->service_fee_amount;

            $lineSubtotal = $quantity * $unitPrice;
            $lineTaxTotal = $quantity * $taxAmount;
            $lineServiceFeeTotal = $quantity * $serviceFeeAmount;
            $lineGrandTotal = $lineSubtotal + $lineTaxTotal + $lineServiceFeeTotal;

            $items[] = [
                'ticket_type_id' => (int) $ticketType->id,
                'ticket_type_code' => (string) $ticketType->code,
                'ticket_type_name' => (string) $ticketType->name,
                'quantity' => $quantity,
                'visit_date' => $line['visit_date'] ?? null,
                'unit_price' => $unitPrice,
                'tax_amount' => $taxAmount,
                'service_fee_amount' => $serviceFeeAmount,
                'line_subtotal' => $lineSubtotal,
                'line_tax_total' => $lineTaxTotal,
                'line_service_fee_total' => $lineServiceFeeTotal,
                'line_grand_total' => $lineGrandTotal,
            ];

            $subtotal += $lineSubtotal;
            $taxTotal += $lineTaxTotal;
            $serviceFeeTotal += $lineServiceFeeTotal;
        }

        $discountTotal = 0;
        $grandTotal = $subtotal - $discountTotal + $taxTotal + $serviceFeeTotal;

        return [
            'currency' => 'IDR',
            'items' => $items,
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'tax_total' => $taxTotal,
            'service_fee_total' => $serviceFeeTotal,
            'grand_total' => $grandTotal,
        ];
    }
}

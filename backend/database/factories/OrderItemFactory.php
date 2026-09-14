<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $unit = 50000;
        $qty = 1;

        return [
            'order_id' => Order::factory(),
            'ticket_type_id' => TicketType::factory(),
            'ticket_type_code' => 'TT-001',
            'ticket_type_name' => 'Adult',
            'quantity' => $qty,
            'unit_price' => $unit,
            'tax_amount' => 0,
            'service_fee_amount' => 0,
            'line_subtotal' => $unit * $qty,
            'line_tax_total' => 0,
            'line_service_fee_total' => 0,
            'line_grand_total' => $unit * $qty,
        ];
    }
}

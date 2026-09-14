<?php

namespace Database\Factories;

use App\Enums\Channel;
use App\Enums\TicketStatus;
use App\Models\Destination;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $payload = fake()->unique()->uuid();

        return [
            'ticket_code' => strtoupper(fake()->unique()->bothify('TCK########')),
            'order_id' => Order::factory(),
            'order_item_id' => OrderItem::factory(),
            'destination_id' => Destination::factory(),
            'ticket_type_id' => TicketType::factory(),
            'payment_id' => Payment::factory(),
            'channel' => Channel::Kiosk,
            'status' => TicketStatus::Issued,
            'currency' => 'IDR',
            'unit_price_snapshot' => 50000,
            'tax_snapshot' => 0,
            'service_fee_snapshot' => 0,
            'valid_start_at' => now()->startOfDay(),
            'valid_end_at' => now()->endOfDay(),
            'issued_at' => now(),
            'activated_at' => now(),
            'used_at' => null,
            'expired_at' => null,
            'cancelled_at' => null,
            'refunded_at' => null,
            'issued_by_user_id' => null,
            'issued_by_device_id' => null,
            'qr_payload' => $payload,
            'qr_payload_hash' => hash('sha256', $payload),
            'qr_version' => 1,
            'qr_secret_hint' => null,
        ];
    }

    public function used(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => TicketStatus::Used,
            'used_at' => $attributes['used_at'] ?? now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => TicketStatus::Cancelled,
            'cancelled_at' => $attributes['cancelled_at'] ?? now(),
        ]);
    }

    public function refunded(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => TicketStatus::Refunded,
            'refunded_at' => $attributes['refunded_at'] ?? now(),
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Enums\Channel;
use App\Enums\OrderStatus;
use App\Models\Destination;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $unit = 50000;

        return [
            'order_number' => strtoupper(fake()->unique()->bothify('ORD########')),
            'destination_id' => Destination::factory(),
            'channel' => Channel::Kiosk,
            'status' => OrderStatus::PendingPayment,
            'visitor_id' => null,
            'device_id' => null,
            'created_by_user_id' => null,
            'currency' => 'IDR',
            'subtotal' => $unit,
            'discount_total' => 0,
            'tax_total' => 0,
            'service_fee_total' => 0,
            'grand_total' => $unit,
            'discount_code' => null,
            'customer_note' => null,
            'expires_at' => now()->addMinutes(15),
            'paid_at' => null,
            'cancelled_at' => null,
            'expired_at' => null,
            'refunded_at' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrderStatus::Paid,
            'paid_at' => $attributes['paid_at'] ?? now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrderStatus::Cancelled,
            'cancelled_at' => $attributes['cancelled_at'] ?? now(),
        ]);
    }

    public function refunded(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrderStatus::Refunded,
            'paid_at' => $attributes['paid_at'] ?? now()->subHour(),
            'refunded_at' => $attributes['refunded_at'] ?? now(),
        ]);
    }
}

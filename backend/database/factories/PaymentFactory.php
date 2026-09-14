<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_number' => strtoupper(fake()->unique()->bothify('PAY########')),
            'order_id' => Order::factory(),
            'status' => PaymentStatus::Pending,
            'method' => PaymentMethod::EWallet,
            'provider' => null,
            'amount' => 50000,
            'currency' => 'IDR',
            'provider_payment_id' => null,
            'provider_reference' => null,
            'paid_at' => null,
            'failed_at' => null,
            'expired_at' => null,
            'cancelled_at' => null,
            'refunded_at' => null,
            'failure_code' => null,
            'failure_message' => null,
            'collected_by_user_id' => null,
            'device_id' => null,
            'metadata_json' => null,
        ];
    }

    public function processing(): static
    {
        return $this->state(fn (): array => [
            'status' => PaymentStatus::Processing,
        ]);
    }

    public function cash(): static
    {
        return $this->state(fn (): array => [
            'method' => PaymentMethod::Cash,
            'provider' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PaymentStatus::Failed,
            'failed_at' => $attributes['failed_at'] ?? now(),
            'failure_code' => 'upstream.payment_provider_unavailable',
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PaymentStatus::Paid,
            'paid_at' => $attributes['paid_at'] ?? now(),
        ]);
    }

    public function refunded(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PaymentStatus::Refunded,
            'paid_at' => $attributes['paid_at'] ?? now()->subHour(),
            'refunded_at' => $attributes['refunded_at'] ?? now(),
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Enums\WebhookProcessStatus;
use App\Models\PaymentWebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentWebhookEvent>
 */
class PaymentWebhookEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => 'tbd',
            'event_id' => (string) Str::uuid(),
            'payment_id' => null,
            'payload_hash' => hash('sha256', fake()->unique()->sha256()),
            'processed_at' => null,
            'process_status' => WebhookProcessStatus::Received,
        ];
    }
}

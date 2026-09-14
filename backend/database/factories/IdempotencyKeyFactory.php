<?php

namespace Database\Factories;

use App\Models\IdempotencyKey;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IdempotencyKey>
 */
class IdempotencyKeyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key_hash' => hash('sha256', fake()->unique()->uuid()),
            'scope' => 'order.create',
            'actor_type' => 'device',
            'actor_id' => fake()->unique()->numberBetween(1, 1_000_000),
            'request_hash' => hash('sha256', fake()->unique()->sha256()),
            'response_code' => null,
            'resource_type' => null,
            'resource_id' => null,
            'locked_at' => null,
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn (): array => [
            'response_code' => null,
            'resource_type' => null,
            'resource_id' => null,
            'locked_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'response_code' => 201,
            'resource_type' => 'order',
            'resource_id' => fake()->numberBetween(1, 999_999),
            'locked_at' => null,
        ]);
    }
}

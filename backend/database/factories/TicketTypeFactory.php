<?php

namespace Database\Factories;

use App\Enums\ValidityType;
use App\Models\Destination;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketType>
 */
class TicketTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'destination_id' => Destination::factory(),
            'code' => strtoupper(fake()->unique()->bothify('TT-###')),
            'name' => fake()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'currency' => 'IDR',
            'unit_price' => 50000,
            'tax_amount' => 0,
            'service_fee_amount' => 0,
            'validity_type' => ValidityType::SameDay,
            'validity_days' => null,
            'valid_from_time' => null,
            'valid_until_time' => null,
            'max_per_order' => 20,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Models\Destination;
use App\Models\Gate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Gate>
 */
class GateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'destination_id' => Destination::factory(),
            'code' => strtoupper(fake()->unique()->bothify('GATE-##')),
            'name' => fake()->words(2, true),
            'is_active' => true,
            'controller_type' => null,
        ];
    }
}

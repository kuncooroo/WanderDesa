<?php

namespace Database\Factories;

use App\Models\Visitor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Visitor>
 */
class VisitorFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'external_ref' => fake()->optional()->uuid(),
            'display_name' => fake()->optional()->name(),
            'phone' => fake()->optional()->numerify('08##########'),
            'email' => fake()->optional()->safeEmail(),
        ];
    }
}

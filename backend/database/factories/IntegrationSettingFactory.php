<?php

namespace Database\Factories;

use App\Models\IntegrationSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IntegrationSetting>
 */
class IntegrationSettingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'integration' => 'payment',
            'provider' => fake()->unique()->slug(1),
            'is_active' => false,
            'config_json' => ['mode' => 'sandbox'],
            'secrets_encrypted' => null,
        ];
    }
}

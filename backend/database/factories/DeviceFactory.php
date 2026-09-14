<?php

namespace Database\Factories;

use App\Enums\DeviceStatus;
use App\Models\Destination;
use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'device_id' => fake()->unique()->bothify('kiosk-####-????'),
            'terminal_id' => null,
            'destination_id' => Destination::factory(),
            'name' => fake()->words(2, true),
            'status' => DeviceStatus::Registered,
            'is_active' => false,
            'maintenance_mode' => false,
            'software_version' => null,
            'hardware_version' => null,
            'last_heartbeat_at' => null,
            'last_ip' => null,
            'activation_secret_hash' => null,
            'activation_expires_at' => null,
            'registered_at' => now(),
            'activated_at' => null,
            'deactivated_at' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DeviceStatus::Active,
            'is_active' => true,
            'maintenance_mode' => false,
            'activated_at' => $attributes['activated_at'] ?? now(),
        ]);
    }

    public function maintenance(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DeviceStatus::Maintenance,
            'is_active' => true,
            'maintenance_mode' => true,
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DeviceStatus::Disabled,
            'is_active' => false,
            'maintenance_mode' => false,
            'deactivated_at' => $attributes['deactivated_at'] ?? now(),
        ]);
    }
}

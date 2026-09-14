<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\DeviceHeartbeat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeviceHeartbeat>
 */
class DeviceHeartbeatFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'device_id' => Device::factory(),
            'software_version' => '0.0.1',
            'printer_ok' => true,
            'payload_json' => ['ok' => true],
            'created_at' => now(),
        ];
    }
}

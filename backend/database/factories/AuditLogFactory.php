<?php

namespace Database\Factories;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'actor_type' => 'system',
            'actor_id' => null,
            'action' => 'order.created',
            'entity_type' => 'order',
            'entity_id' => fake()->numberBetween(1, 1000),
            'destination_id' => null,
            'ip_address' => null,
            'user_agent' => null,
            'before_json' => null,
            'after_json' => ['status' => 'pending_payment'],
            'meta_json' => null,
        ];
    }
}

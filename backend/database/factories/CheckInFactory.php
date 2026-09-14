<?php

namespace Database\Factories;

use App\Models\CheckIn;
use App\Models\Destination;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CheckIn>
 */
class CheckInFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'destination_id' => Destination::factory(),
            'gate_id' => null,
            'result' => 'allow',
            'checked_in_at' => now(),
            'checked_in_by_user_id' => null,
            'device_id' => null,
            'client_context' => null,
        ];
    }
}

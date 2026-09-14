<?php

namespace Database\Factories;

use App\Enums\PrintAttemptResult;
use App\Models\Ticket;
use App\Models\TicketPrintLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketPrintLog>
 */
class TicketPrintLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'result' => PrintAttemptResult::Success,
            'actor_type' => 'user',
            'actor_id' => 1,
            'message' => null,
            'created_at' => now(),
        ];
    }
}

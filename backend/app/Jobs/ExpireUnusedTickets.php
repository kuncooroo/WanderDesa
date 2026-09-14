<?php

namespace App\Jobs;

use App\Actions\Tickets\ExpireTicket;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ExpireUnusedTickets implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 3500;

    public function __construct(
        public int $limit = 200,
    ) {}

    public function handle(ExpireTicket $expire): void
    {
        $due = Ticket::query()
            ->whereIn('status', [
                TicketStatus::Pending->value,
                TicketStatus::Issued->value,
                TicketStatus::Active->value,
            ])
            ->whereNotNull('valid_end_at')
            ->where('valid_end_at', '<=', now())
            ->orderBy('id')
            ->limit($this->limit)
            ->get();

        foreach ($due as $ticket) {
            try {
                $expire->handle($ticket, 'ttl');
            } catch (Throwable $e) {
                Log::warning('ticket.expire.failed', [
                    'ticket_id' => $ticket->id,
                    'exception' => $e::class,
                ]);
            }
        }
    }
}

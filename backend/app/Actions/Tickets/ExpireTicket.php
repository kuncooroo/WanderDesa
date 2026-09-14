<?php

namespace App\Actions\Tickets;

use App\Enums\TicketStatus;
use App\Exceptions\DomainException;
use App\Models\Ticket;
use App\Services\Tickets\TicketStateService;
use App\Support\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Unused tickets past valid_end_at become EXPIRED (job or on-validate lag).
 */
final class ExpireTicket
{
    public function __construct(
        private readonly TicketStateService $states,
        private readonly AuditWriter $audit,
    ) {}

    public function handle(Ticket $ticket, string $source = 'ttl'): Ticket
    {
        return DB::transaction(function () use ($ticket, $source): Ticket {
            /** @var Ticket $locked */
            $locked = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();

            $status = $locked->status instanceof TicketStatus
                ? $locked->status
                : TicketStatus::from((string) $locked->status);

            if ($status === TicketStatus::Expired) {
                return $locked;
            }

            if (in_array($status, [TicketStatus::Used, TicketStatus::Cancelled, TicketStatus::Refunded], true)) {
                return $locked;
            }

            if ($locked->valid_end_at === null || $locked->valid_end_at->gt(now())) {
                return $locked;
            }

            $before = ['status' => $status->value];

            try {
                $this->states->transition($locked, TicketStatus::Expired);
            } catch (DomainException) {
                return $locked;
            }

            $locked->forceFill([
                'expired_at' => $locked->expired_at ?? now(),
            ])->save();

            $this->audit->writeCritical(
                action: 'ticket.expired',
                actorType: 'system',
                actorId: null,
                entityType: 'ticket',
                entityId: (int) $locked->id,
                before: $before,
                after: ['status' => TicketStatus::Expired->value],
                meta: [
                    'source' => $source,
                    'ticket_code' => $locked->ticket_code,
                ],
                destinationId: $locked->destination_id,
            );

            Log::info('ticket.expired', [
                'ticket_id' => $locked->id,
                'source' => $source,
            ]);

            return $locked;
        });
    }
}

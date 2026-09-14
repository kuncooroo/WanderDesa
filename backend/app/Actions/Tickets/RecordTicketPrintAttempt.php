<?php

namespace App\Actions\Tickets;

use App\Enums\PrintAttemptResult;
use App\Exceptions\DomainException;
use App\Models\Device;
use App\Models\Ticket;
use App\Models\TicketPrintLog;
use App\Models\User;
use App\Support\AuditWriter;
use App\Support\DeviceAbilities;
use App\Support\Tickets\PrintAttemptRecord;
use App\Support\Tickets\TicketAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Records a print/reprint attempt. Never mints tickets or changes payment/ticket status.
 */
final class RecordTicketPrintAttempt
{
    public function __construct(
        private readonly AuditWriter $audit,
    ) {}

    public function handle(
        Device|User $principal,
        Ticket $ticket,
        PrintAttemptResult $result,
        ?string $message = null,
        ?Request $request = null,
    ): PrintAttemptRecord {
        $ticket->loadMissing('order');

        $alreadyPrinted = $this->hasSuccessfulPrint($ticket);
        $this->authorizeAttempt($principal, $ticket, $result, $alreadyPrinted);

        $auditAction = $this->auditAction($result, $alreadyPrinted);
        $actorType = $principal instanceof Device ? 'device' : 'user';
        $actorId = (int) $principal->getKey();
        $status = $ticket->status;
        $ticketStatus = $status instanceof \BackedEnum ? $status->value : (string) $status;

        $log = DB::transaction(function () use (
            $ticket,
            $result,
            $message,
            $request,
            $auditAction,
            $actorType,
            $actorId,
            $ticketStatus,
        ): TicketPrintLog {
            $log = TicketPrintLog::query()->create([
                'ticket_id' => $ticket->id,
                'result' => $result,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'message' => $this->truncateMessage($message),
                'created_at' => now(),
            ]);

            $this->audit->write(
                action: $auditAction,
                actorType: $actorType,
                actorId: $actorId,
                entityType: 'ticket',
                entityId: (int) $ticket->id,
                after: [
                    'ticket_code' => $ticket->ticket_code,
                    'result' => $result->value,
                    'ticket_status' => $ticketStatus,
                ],
                meta: [
                    'print_log_id' => $log->id,
                    'order_id' => $ticket->order_id,
                    'destination_id' => $ticket->destination_id,
                    'reprint' => $auditAction === 'ticket.reprinted',
                ],
                request: $request,
                destinationId: (int) $ticket->destination_id,
            );

            Log::info($auditAction, [
                'ticket_id' => $ticket->id,
                'ticket_code' => $ticket->ticket_code,
                'result' => $result->value,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
            ]);

            return $log;
        });

        return new PrintAttemptRecord($log, $auditAction);
    }

    public function hasSuccessfulPrint(Ticket $ticket): bool
    {
        return TicketPrintLog::query()
            ->where('ticket_id', $ticket->id)
            ->where('result', PrintAttemptResult::Success)
            ->exists();
    }

    private function authorizeAttempt(
        Device|User $principal,
        Ticket $ticket,
        PrintAttemptResult $result,
        bool $alreadyPrinted,
    ): void {
        if ($principal instanceof Device) {
            if (
                ! $principal->tokenCan(DeviceAbilities::TICKETS_PRINT_OWN)
                && ! $principal->tokenCan(DeviceAbilities::TICKETS_READ_OWN)
            ) {
                throw new AuthorizationException('Missing device ability: tickets:print_own');
            }

            if (! TicketAccess::deviceOwns($principal, $ticket)) {
                throw new DomainException(
                    'resource.not_found',
                    'Ticket not found.',
                    404,
                );
            }

            return;
        }

        $needsReprint = $alreadyPrinted && $result === PrintAttemptResult::Success;

        if ($needsReprint) {
            if (! $principal->can('reprint', $ticket)) {
                throw new AuthorizationException('This action is unauthorized.');
            }

            return;
        }

        if (! $principal->can('print', $ticket) && ! $principal->can('reprint', $ticket)) {
            throw new AuthorizationException('This action is unauthorized.');
        }
    }

    private function auditAction(PrintAttemptResult $result, bool $alreadyPrinted): string
    {
        if ($result === PrintAttemptResult::Failed) {
            return 'ticket.print_failed';
        }

        return $alreadyPrinted ? 'ticket.reprinted' : 'ticket.printed';
    }

    private function truncateMessage(?string $message): ?string
    {
        if ($message === null) {
            return null;
        }

        $trimmed = trim($message);

        if ($trimmed === '') {
            return null;
        }

        return mb_substr($trimmed, 0, 255);
    }
}

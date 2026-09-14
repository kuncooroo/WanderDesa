<?php

namespace App\Actions\Tickets;

use App\Enums\PermissionName;
use App\Enums\TicketDenyCode;
use App\Enums\TicketStatus;
use App\Models\Device;
use App\Models\Gate;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Qr\QrPayloadGenerator;
use App\Support\AuditWriter;
use App\Support\Authorization\Authorizer;
use App\Support\Tickets\TicketValidationResult;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * QR decision. Does not consume the ticket (TASK-014 CheckIn).
 * Past valid_end_at is persisted as EXPIRED when this handle() path runs.
 */
final class ValidateTicket
{
    public function __construct(
        private readonly QrPayloadGenerator $qr,
        private readonly AuditWriter $audit,
        private readonly ExpireTicket $expireTicket,
    ) {}

    public function handle(
        Device|User $principal,
        string $qrPayload,
        int $destinationId,
        ?int $gateId = null,
        ?Request $request = null,
    ): TicketValidationResult {
        $this->assertCanValidate($principal);

        $result = $this->evaluate($qrPayload, $destinationId, $gateId);

        if (
            $result->reasonCode === TicketDenyCode::Expired
            && $result->ticket !== null
        ) {
            $this->expireTicket->handle($result->ticket, 'validate');
            $result->ticket->refresh();
        }

        $this->audit->write(
            action: 'ticket.validated',
            actorType: 'user',
            actorId: (int) $principal->getKey(),
            entityType: $result->ticket !== null ? 'ticket' : null,
            entityId: $result->ticket?->id,
            before: null,
            after: [
                'result' => $result->result->value,
                'reason_code' => $result->reasonCode?->value,
            ],
            meta: [
                'destination_id' => $destinationId,
                'gate_id' => $gateId,
                'payload_hash' => $this->qr->hash($qrPayload),
            ],
            request: $request,
        );

        Log::info('ticket.validated', [
            'result' => $result->result->value,
            'reason_code' => $result->reasonCode?->value,
            'ticket_id' => $result->ticket?->id,
            'destination_id' => $destinationId,
        ]);

        return $result;
    }

    private function assertCanValidate(Device|User $principal): void
    {
        if ($principal instanceof Device) {
            throw new AuthorizationException('Devices cannot validate tickets.');
        }

        $allowed = Authorizer::check($principal, PermissionName::TicketsValidate)
            || Authorizer::check($principal, PermissionName::CheckinsCreate);

        if (! $allowed) {
            throw new AuthorizationException('Missing permission: tickets.validate');
        }
    }

    /**
     * Authenticity + business eligibility without audit or status mutation.
     * Safe to call again after locking a ticket row (CheckIn).
     */
    public function evaluate(string $qrPayload, int $destinationId, ?int $gateId = null): TicketValidationResult
    {
        if ($this->qr->parse($qrPayload) === null) {
            return TicketValidationResult::deny(TicketDenyCode::InvalidAuth);
        }

        $ticket = Ticket::query()
            ->with('payment')
            ->where('qr_payload_hash', $this->qr->hash($qrPayload))
            ->first();

        if ($ticket === null) {
            return TicketValidationResult::deny(TicketDenyCode::NotFound);
        }

        if (! $this->qr->verifyMac($qrPayload, $ticket->ticket_code)) {
            return TicketValidationResult::deny(TicketDenyCode::InvalidAuth);
        }

        return $this->evaluateTicket($ticket, $destinationId, $gateId);
    }

    /**
     * Re-validate a locked ticket row (MAC already verified by evaluate / caller).
     */
    public function evaluateTicket(Ticket $ticket, int $destinationId, ?int $gateId = null): TicketValidationResult
    {
        $ticket->loadMissing('payment');

        if ($gateId !== null) {
            $gate = Gate::query()->find($gateId);

            if ($gate === null || ! $gate->is_active) {
                return TicketValidationResult::deny(TicketDenyCode::Unauthorized, $ticket);
            }

            if ((int) $gate->destination_id !== $destinationId) {
                return TicketValidationResult::deny(TicketDenyCode::WrongDestination, $ticket);
            }
        }

        if ((int) $ticket->destination_id !== $destinationId) {
            return TicketValidationResult::deny(TicketDenyCode::WrongDestination, $ticket);
        }

        return $this->evaluateEligibility($ticket);
    }

    private function evaluateEligibility(Ticket $ticket): TicketValidationResult
    {
        $status = $ticket->status instanceof TicketStatus
            ? $ticket->status
            : TicketStatus::from((string) $ticket->status);

        if ($status === TicketStatus::Cancelled) {
            return TicketValidationResult::deny(TicketDenyCode::Cancelled, $ticket);
        }

        if ($status === TicketStatus::Refunded) {
            return TicketValidationResult::deny(TicketDenyCode::Refunded, $ticket);
        }

        if ($status === TicketStatus::Used) {
            return TicketValidationResult::deny(TicketDenyCode::AlreadyUsed, $ticket);
        }

        $payment = $ticket->payment;

        if ($payment === null || ! $payment->isPaid()) {
            return TicketValidationResult::deny(TicketDenyCode::NotPaid, $ticket);
        }

        $now = now();

        if ($status === TicketStatus::Expired || $ticket->valid_end_at === null || $now->gt($ticket->valid_end_at)) {
            return TicketValidationResult::deny(TicketDenyCode::Expired, $ticket);
        }

        if ($status !== TicketStatus::Active || $now->lt($ticket->valid_start_at)) {
            return TicketValidationResult::deny(TicketDenyCode::NotActive, $ticket);
        }

        return TicketValidationResult::allow($ticket);
    }
}

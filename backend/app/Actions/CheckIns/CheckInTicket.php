<?php

namespace App\Actions\CheckIns;

use App\Actions\Tickets\ExpireTicket;
use App\Actions\Tickets\ValidateTicket;
use App\Enums\IdempotencyScope;
use App\Enums\PermissionName;
use App\Enums\TicketDenyCode;
use App\Enums\TicketStatus;
use App\Integrations\Gate\GateController;
use App\Models\CheckIn;
use App\Models\Device;
use App\Models\Gate;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Qr\QrPayloadGenerator;
use App\Services\Tickets\TicketStateService;
use App\Support\AuditWriter;
use App\Support\Authorization\Authorizer;
use App\Support\CheckIns\CheckInResult;
use App\Support\Idempotency\IdempotencyActor;
use App\Support\Idempotency\IdempotencyManager;
use App\Support\Idempotency\IdempotencyOutcome;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Transactional single-entry consumption: lock → re-validate → check_in → USED.
 */
final class CheckInTicket
{
    public function __construct(
        private readonly ValidateTicket $validate,
        private readonly ExpireTicket $expireTicket,
        private readonly QrPayloadGenerator $qr,
        private readonly TicketStateService $ticketState,
        private readonly IdempotencyManager $idempotency,
        private readonly GateController $gateController,
        private readonly AuditWriter $audit,
    ) {}

    public function handle(
        Device|User $principal,
        string $qrPayload,
        int $destinationId,
        string $idempotencyKey,
        ?int $gateId = null,
        ?Request $request = null,
    ): CheckInResult {
        $this->assertCanCheckIn($principal);

        $canonical = [
            'qr_payload' => $qrPayload,
            'destination_id' => $destinationId,
            'gate_id' => $gateId,
        ];

        $reservation = $this->idempotency->begin(
            $idempotencyKey,
            IdempotencyScope::CheckInCreate,
            IdempotencyActor::fromPrincipal($principal),
            $canonical,
        );

        if ($reservation->isReplay()) {
            return $this->replayAllow($reservation->record->resource_id);
        }

        try {
            $result = $this->consume($principal, $qrPayload, $destinationId, $gateId, $request);

            if ($result->allowed() && $result->checkIn !== null) {
                $this->idempotency->commit(
                    $reservation->record,
                    new IdempotencyOutcome('check_in', (int) $result->checkIn->id, 200, $result),
                );

                $this->openGateAfterCommit($gateId, $result->checkIn);
            } else {
                $this->idempotency->release($reservation->record);
            }

            return $result;
        } catch (\Throwable $e) {
            $this->idempotency->release($reservation->record);

            throw $e;
        }
    }

    private function assertCanCheckIn(Device|User $principal): void
    {
        if ($principal instanceof Device) {
            throw new AuthorizationException('Devices cannot perform check-in.');
        }

        Authorizer::authorize($principal, PermissionName::CheckinsCreate);
    }

    private function consume(
        User $principal,
        string $qrPayload,
        int $destinationId,
        ?int $gateId,
        ?Request $request,
    ): CheckInResult {
        if ($this->qr->parse($qrPayload) === null) {
            return CheckInResult::deny(TicketDenyCode::InvalidAuth);
        }

        $payloadHash = $this->qr->hash($qrPayload);

        try {
            return DB::transaction(function () use ($principal, $qrPayload, $payloadHash, $destinationId, $gateId, $request): CheckInResult {
                $ticket = Ticket::query()
                    ->with('payment')
                    ->where('qr_payload_hash', $payloadHash)
                    ->lockForUpdate()
                    ->first();

                if ($ticket === null) {
                    return CheckInResult::deny(TicketDenyCode::NotFound);
                }

                if (! $this->qr->verifyMac($qrPayload, $ticket->ticket_code)) {
                    return CheckInResult::deny(TicketDenyCode::InvalidAuth);
                }

                if ($ticket->valid_end_at !== null && now()->gte($ticket->valid_end_at)) {
                    $ticket = $this->expireTicket->handle($ticket, 'check_in');
                    $ticket->loadMissing('payment');
                }

                $decision = $this->validate->evaluateTicket($ticket, $destinationId, $gateId);

                if (! $decision->allowed()) {
                    return CheckInResult::deny(
                        $decision->reasonCode ?? TicketDenyCode::Unauthorized,
                        $decision->ticket,
                    );
                }

                $checkedInAt = now();

                $checkIn = CheckIn::query()->create([
                    'ticket_id' => $ticket->id,
                    'destination_id' => $destinationId,
                    'gate_id' => $gateId,
                    'result' => 'allow',
                    'checked_in_at' => $checkedInAt,
                    'checked_in_by_user_id' => $principal->id,
                    'device_id' => null,
                    'client_context' => $gateId !== null ? 'gate:'.$gateId : null,
                ]);

                $this->ticketState->transition($ticket, TicketStatus::Used);
                $ticket->used_at = $checkedInAt;
                $ticket->save();

                $this->audit->write(
                    action: 'ticket.checked_in',
                    actorType: 'user',
                    actorId: (int) $principal->getKey(),
                    entityType: 'ticket',
                    entityId: (int) $ticket->id,
                    before: ['status' => TicketStatus::Active->value],
                    after: [
                        'status' => TicketStatus::Used->value,
                        'check_in_id' => $checkIn->id,
                        'checked_in_at' => $checkedInAt->toIso8601String(),
                    ],
                    meta: [
                        'destination_id' => $destinationId,
                        'gate_id' => $gateId,
                        'payload_hash' => $payloadHash,
                    ],
                    request: $request,
                );

                Log::info('ticket.checked_in', [
                    'ticket_id' => $ticket->id,
                    'check_in_id' => $checkIn->id,
                    'destination_id' => $destinationId,
                    'gate_id' => $gateId,
                ]);

                return CheckInResult::allow($ticket->fresh(), $checkIn);
            });
        } catch (UniqueConstraintViolationException) {
            return $this->denyAlreadyUsed($payloadHash);
        } catch (QueryException $e) {
            if (! $this->isUniqueConstraint($e)) {
                throw $e;
            }

            return $this->denyAlreadyUsed($payloadHash);
        }
    }

    private function denyAlreadyUsed(string $payloadHash): CheckInResult
    {
        $ticket = Ticket::query()
            ->where('qr_payload_hash', $payloadHash)
            ->first();

        return CheckInResult::deny(TicketDenyCode::AlreadyUsed, $ticket);
    }

    private function isUniqueConstraint(QueryException $e): bool
    {
        if ($e instanceof UniqueConstraintViolationException) {
            return true;
        }

        $sqlState = (string) ($e->errorInfo[0] ?? '');
        $driverCode = (int) ($e->errorInfo[1] ?? 0);

        return $sqlState === '23000' || $driverCode === 1062 || $driverCode === 19
            || str_contains(strtolower($e->getMessage()), 'unique');
    }

    private function replayAllow(int $checkInId): CheckInResult
    {
        $checkIn = CheckIn::query()->with('ticket')->findOrFail($checkInId);

        /** @var Ticket $ticket */
        $ticket = $checkIn->ticket;

        return CheckInResult::allow($ticket, $checkIn);
    }

    private function openGateAfterCommit(?int $gateId, CheckIn $checkIn): void
    {
        $gate = $gateId !== null ? Gate::query()->find($gateId) : null;

        $this->gateController->open($gate, $checkIn);
    }
}

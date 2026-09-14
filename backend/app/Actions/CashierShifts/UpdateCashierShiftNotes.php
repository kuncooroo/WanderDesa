<?php

namespace App\Actions\CashierShifts;

use App\Enums\PermissionName;
use App\Models\CashierShift;
use App\Models\User;
use App\Support\AuditWriter;
use App\Support\Authorization\Authorizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;

/**
 * Notes-only update (no money fields from client).
 */
final class UpdateCashierShiftNotes
{
    public function __construct(
        private readonly AuditWriter $audit,
    ) {}

    public function handle(
        User $actor,
        CashierShift $shift,
        ?string $notes,
        ?Request $request = null,
    ): CashierShift {
        $isOwner = (int) $shift->user_id === (int) $actor->id;

        if ($isOwner) {
            Authorizer::authorize($actor, PermissionName::PaymentsCreate);
        } elseif (! Authorizer::check($actor, PermissionName::PaymentsView)) {
            throw new AuthorizationException('You may not edit notes on this shift.');
        }

        $before = ['notes' => $shift->notes];

        $shift->forceFill([
            'notes' => $notes,
        ])->save();

        $this->audit->write(
            action: 'cashier_shift.notes_updated',
            actorType: 'user',
            actorId: (int) $actor->id,
            entityType: 'cashier_shift',
            entityId: (int) $shift->id,
            before: $before,
            after: ['notes' => $shift->notes],
            meta: [],
            request: $request,
        );

        return $shift->fresh(['user']);
    }
}

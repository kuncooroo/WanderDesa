<?php

namespace App\Actions\CashierShifts;

use App\Enums\PermissionName;
use App\Exceptions\DomainException;
use App\Models\CashierShift;
use App\Models\Payment;
use App\Models\User;
use App\Support\AuditWriter;
use App\Support\Authorization\Authorizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DiscardOpenCashierShift
{
    public function __construct(
        private readonly AuditWriter $audit,
    ) {}

    public function handle(User $actor, CashierShift $shift, ?Request $request = null): void
    {
        Authorizer::authorize($actor, PermissionName::PaymentsCreate);

        if ((int) $shift->user_id !== (int) $actor->id) {
            throw new AuthorizationException('You may only discard your own open cashier shift.');
        }

        DB::transaction(function () use ($actor, $shift, $request): void {
            /** @var CashierShift $locked */
            $locked = CashierShift::query()->whereKey($shift->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isOpen()) {
                throw new DomainException(
                    'cashier_shift.state_conflict',
                    'Hanya shift terbuka yang dapat dibatalkan.',
                    409,
                );
            }

            $linked = Payment::query()
                ->where(function ($query) use ($locked): void {
                    $query->where('cashier_shift_id', $locked->id)
                        ->orWhere('refund_cashier_shift_id', $locked->id);
                })
                ->exists();

            if ($linked) {
                throw new DomainException(
                    'cashier_shift.has_payments',
                    'Shift yang sudah memiliki transaksi tunai tidak dapat dihapus. Tutup shift saja.',
                    422,
                );
            }

            $snapshot = [
                'status' => $locked->status->value,
                'initial_cash' => (int) $locked->initial_cash,
            ];

            $id = (int) $locked->id;
            $locked->delete();

            $this->audit->write(
                action: 'cashier_shift.discarded',
                actorType: 'user',
                actorId: (int) $actor->id,
                entityType: 'cashier_shift',
                entityId: $id,
                before: $snapshot,
                after: null,
                meta: [],
                request: $request,
            );
        });
    }
}

<?php

namespace App\Actions\CashierShifts;

use App\Enums\CashierShiftStatus;
use App\Enums\PermissionName;
use App\Exceptions\DomainException;
use App\Models\CashierShift;
use App\Models\User;
use App\Services\CashierShifts\CashierShiftLedger;
use App\Support\AuditWriter;
use App\Support\Authorization\Authorizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CloseCashierShift
{
    public function __construct(
        private readonly CashierShiftLedger $ledger,
        private readonly AuditWriter $audit,
    ) {}

    /**
     * @param  array{actual_cash: int, notes?: string|null}  $data
     */
    public function handle(
        User $actor,
        CashierShift $shift,
        array $data,
        ?Request $request = null,
    ): CashierShift {
        Authorizer::authorize($actor, PermissionName::PaymentsCreate);

        if ((int) $shift->user_id !== (int) $actor->id) {
            throw new AuthorizationException('You may only close your own cashier shift.');
        }

        $actualCash = (int) $data['actual_cash'];

        if ($actualCash < 0) {
            throw new DomainException(
                'validation.failed',
                'Kas aktual tidak boleh negatif.',
                422,
            );
        }

        return DB::transaction(function () use ($actor, $shift, $data, $actualCash, $request): CashierShift {
            /** @var CashierShift $locked */
            $locked = CashierShift::query()->whereKey($shift->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isOpen()) {
                throw new DomainException(
                    'cashier_shift.state_conflict',
                    'Shift ini sudah ditutup.',
                    409,
                );
            }

            $totals = $this->ledger->recalculate($locked);
            $expected = $totals['expected_cash'];
            $difference = $actualCash - $expected;

            $before = [
                'status' => $locked->status->value,
                'expected_cash' => (int) $locked->expected_cash,
            ];

            $notes = array_key_exists('notes', $data)
                ? $data['notes']
                : $locked->notes;

            $locked->forceFill([
                'total_cash_sales' => $totals['total_cash_sales'],
                'total_cash_refund' => $totals['total_cash_refund'],
                'expected_cash' => $expected,
                'actual_cash' => $actualCash,
                'difference' => $difference,
                'status' => CashierShiftStatus::Closed,
                'closed_at' => now(),
                'notes' => $notes,
            ])->save();

            $this->audit->writeCritical(
                action: 'cashier_shift.closed',
                actorType: 'user',
                actorId: (int) $actor->id,
                entityType: 'cashier_shift',
                entityId: (int) $locked->id,
                before: $before,
                after: [
                    'status' => CashierShiftStatus::Closed->value,
                    'expected_cash' => $expected,
                    'actual_cash' => $actualCash,
                    'difference' => $difference,
                ],
                meta: [
                    'total_cash_sales' => $totals['total_cash_sales'],
                    'total_cash_refund' => $totals['total_cash_refund'],
                ],
                request: $request,
            );

            return $locked->fresh(['user']);
        });
    }
}

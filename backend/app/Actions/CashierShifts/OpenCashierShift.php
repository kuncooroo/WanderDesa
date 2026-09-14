<?php

namespace App\Actions\CashierShifts;

use App\Enums\CashierShiftStatus;
use App\Enums\PermissionName;
use App\Exceptions\DomainException;
use App\Models\CashierShift;
use App\Models\User;
use App\Support\AuditWriter;
use App\Support\Authorization\Authorizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class OpenCashierShift
{
    public function __construct(
        private readonly AuditWriter $audit,
    ) {}

    /**
     * @param  array{initial_cash: int, notes?: string|null}  $data
     */
    public function handle(User $actor, array $data, ?Request $request = null): CashierShift
    {
        Authorizer::authorize($actor, PermissionName::PaymentsCreate);

        $initialCash = (int) $data['initial_cash'];

        if ($initialCash < 0) {
            throw new DomainException(
                'validation.failed',
                'Modal awal tidak boleh negatif.',
                422,
            );
        }

        return DB::transaction(function () use ($actor, $data, $initialCash, $request): CashierShift {
            $existing = CashierShift::query()
                ->where('user_id', $actor->id)
                ->where('status', CashierShiftStatus::Open)
                ->lockForUpdate()
                ->exists();

            if ($existing) {
                throw new DomainException(
                    'cashier_shift.already_open',
                    'Anda masih memiliki shift yang terbuka. Tutup shift tersebut terlebih dahulu.',
                    409,
                );
            }

            $shift = CashierShift::query()->create([
                'user_id' => $actor->id,
                'opened_at' => now(),
                'closed_at' => null,
                'initial_cash' => $initialCash,
                'total_cash_sales' => 0,
                'total_cash_refund' => 0,
                'expected_cash' => $initialCash,
                'actual_cash' => null,
                'difference' => null,
                'status' => CashierShiftStatus::Open,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->audit->write(
                action: 'cashier_shift.opened',
                actorType: 'user',
                actorId: (int) $actor->id,
                entityType: 'cashier_shift',
                entityId: (int) $shift->id,
                before: null,
                after: [
                    'status' => CashierShiftStatus::Open->value,
                    'initial_cash' => $initialCash,
                ],
                meta: [],
                request: $request,
            );

            return $shift->fresh(['user']);
        });
    }
}

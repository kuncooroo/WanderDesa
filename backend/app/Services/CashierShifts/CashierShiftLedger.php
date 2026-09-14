<?php

namespace App\Services\CashierShifts;

use App\Enums\CashierShiftStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\DomainException;
use App\Models\CashierShift;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Server-owned cashier drawer math. Clients never set expected/difference.
 */
final class CashierShiftLedger
{
    public function openShiftFor(User $user): ?CashierShift
    {
        return CashierShift::query()
            ->where('user_id', $user->id)
            ->where('status', CashierShiftStatus::Open)
            ->first();
    }

    public function requireOpenShiftFor(User $user): CashierShift
    {
        $shift = $this->openShiftFor($user);

        if ($shift === null) {
            throw new DomainException(
                'cashier_shift.required',
                'Buka shift kasir terlebih dahulu sebelum menerima pembayaran tunai.',
                422,
            );
        }

        return $shift;
    }

    public function attachCashSale(User $actor, Payment $payment): void
    {
        if (! $payment->isCash()) {
            return;
        }

        if ($payment->cashier_shift_id !== null) {
            return;
        }

        DB::transaction(function () use ($actor, $payment): void {
            /** @var CashierShift $shift */
            $shift = CashierShift::query()
                ->where('user_id', $actor->id)
                ->where('status', CashierShiftStatus::Open)
                ->lockForUpdate()
                ->first();

            if ($shift === null) {
                throw new DomainException(
                    'cashier_shift.required',
                    'Buka shift kasir terlebih dahulu sebelum menerima pembayaran tunai.',
                    422,
                );
            }

            /** @var Payment $locked */
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->cashier_shift_id !== null) {
                return;
            }

            $amount = (int) $locked->amount;
            $newSales = (int) $shift->total_cash_sales + $amount;

            $locked->forceFill([
                'cashier_shift_id' => $shift->id,
            ])->save();

            $shift->forceFill([
                'total_cash_sales' => $newSales,
                'expected_cash' => (int) $shift->initial_cash + $newSales - (int) $shift->total_cash_refund,
            ])->save();
        });
    }

    public function attachCashRefund(User $actor, Payment $payment, int $refundAmount): void
    {
        if (! $payment->isCash()) {
            return;
        }

        if ($payment->refund_cashier_shift_id !== null) {
            return;
        }

        DB::transaction(function () use ($actor, $payment, $refundAmount): void {
            $shift = CashierShift::query()
                ->where('user_id', $actor->id)
                ->where('status', CashierShiftStatus::Open)
                ->lockForUpdate()
                ->first();

            if ($shift === null && $payment->cashier_shift_id !== null) {
                $shift = CashierShift::query()
                    ->whereKey($payment->cashier_shift_id)
                    ->where('status', CashierShiftStatus::Open)
                    ->lockForUpdate()
                    ->first();
            }

            if ($shift === null) {
                return;
            }

            /** @var Payment $locked */
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->refund_cashier_shift_id !== null) {
                return;
            }

            $newRefund = (int) $shift->total_cash_refund + $refundAmount;

            $locked->forceFill([
                'refund_cashier_shift_id' => $shift->id,
            ])->save();

            $shift->forceFill([
                'total_cash_refund' => $newRefund,
                'expected_cash' => (int) $shift->initial_cash + (int) $shift->total_cash_sales - $newRefund,
            ])->save();
        });
    }

    /**
     * Recalculate drawer totals from linked payments (authoritative on close).
     *
     * @return array{total_cash_sales: int, total_cash_refund: int, expected_cash: int}
     */
    public function recalculate(CashierShift $shift): array
    {
        $sales = (int) Payment::query()
            ->where('cashier_shift_id', $shift->id)
            ->where('method', PaymentMethod::Cash)
            ->whereIn('status', [
                PaymentStatus::Paid->value,
                PaymentStatus::Refunded->value,
            ])
            ->sum('amount');

        $refunds = (int) Payment::query()
            ->where('refund_cashier_shift_id', $shift->id)
            ->where('method', PaymentMethod::Cash)
            ->where('status', PaymentStatus::Refunded->value)
            ->sum('amount');

        $expected = (int) $shift->initial_cash + $sales - $refunds;

        return [
            'total_cash_sales' => $sales,
            'total_cash_refund' => $refunds,
            'expected_cash' => $expected,
        ];
    }
}

<?php

namespace App\Actions\Payments;

use App\Actions\Tickets\IssueTickets;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\DomainException;
use App\Models\Payment;
use App\Models\User;
use App\Services\CashierShifts\CashierShiftLedger;
use App\Services\Payments\PaymentStateService;
use App\Support\AuditWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Shared PAID transition for cash confirm, webhooks, and reconcile.
 * Tickets mint in the same transaction via IssueTickets.
 */
final class MarkPaymentPaid
{
    public function __construct(
        private readonly PaymentStateService $states,
        private readonly IssueTickets $issueTickets,
        private readonly AuditWriter $audit,
        private readonly CashierShiftLedger $cashierShiftLedger,
    ) {}

    public function handle(
        Payment $payment,
        ?User $collectedBy = null,
        ?Request $request = null,
        string $source = 'system',
    ): Payment {
        return DB::transaction(function () use ($payment, $collectedBy, $request, $source): Payment {
            /** @var Payment $locked */
            $locked = Payment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $order = $locked->order()->lockForUpdate()->firstOrFail();

            if ($locked->isPaid() && $order->status === OrderStatus::Paid) {
                $this->issueTickets->handle($locked);

                return $locked->load(['order', 'tickets']);
            }

            $alreadyPaid = $order->payments()
                ->where('status', PaymentStatus::Paid->value)
                ->where('id', '!=', $locked->id)
                ->lockForUpdate()
                ->exists();

            if ($alreadyPaid) {
                throw new DomainException(
                    'order.state_conflict',
                    'This order already has a paid payment.',
                    409,
                );
            }

            if (! $order->isPendingPayment() && $order->status !== OrderStatus::Expired) {
                throw new DomainException(
                    'order.state_conflict',
                    'Only pending or TTL-expired orders can be marked paid.',
                    409,
                );
            }

            $before = [
                'payment_status' => $locked->status instanceof PaymentStatus
                    ? $locked->status->value
                    : $locked->status,
                'order_status' => $order->status instanceof OrderStatus
                    ? $order->status->value
                    : $order->status,
            ];

            $this->states->transition($locked, PaymentStatus::Paid);

            $now = now();
            $locked->forceFill([
                'paid_at' => $locked->paid_at ?? $now,
                'collected_by_user_id' => $collectedBy?->id ?? $locked->collected_by_user_id,
            ])->save();

            if ($source === 'cash' && $collectedBy !== null && $locked->isCash()) {
                $this->cashierShiftLedger->attachCashSale($collectedBy, $locked);
                $locked->refresh();
            }

            $order->forceFill([
                'status' => OrderStatus::Paid,
                'paid_at' => $order->paid_at ?? $now,
            ])->save();

            $locked->load(['order', 'tickets']);

            $this->audit->writeCritical(
                action: 'payment.paid',
                actorType: $collectedBy !== null ? 'user' : 'system',
                actorId: $collectedBy?->id,
                entityType: 'payment',
                entityId: (int) $locked->id,
                before: $before,
                after: [
                    'payment_status' => PaymentStatus::Paid->value,
                    'order_status' => OrderStatus::Paid->value,
                    'amount' => $locked->amount,
                ],
                meta: [
                    'source' => $source,
                    'order_id' => $order->id,
                    'payment_number' => $locked->payment_number,
                ],
                request: $request,
            );

            Log::info('payment.paid', [
                'payment_id' => $locked->id,
                'order_id' => $order->id,
                'source' => $source,
                'amount' => $locked->amount,
            ]);

            $this->issueTickets->handle($locked);

            return $locked->load(['order', 'tickets']);
        });
    }
}

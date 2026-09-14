<?php

namespace App\Actions\Orders;

use App\Actions\Payments\MarkPaymentExpired;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\DomainException;
use App\Models\Order;
use App\Models\Payment;
use App\Support\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * TTL expiry for unpaid pending orders. Never issues tickets.
 */
final class ExpireUnpaidOrder
{
    public function __construct(
        private readonly MarkPaymentExpired $markPaymentExpired,
        private readonly AuditWriter $audit,
    ) {}

    public function handle(Order $order, string $source = 'ttl'): Order
    {
        $current = Order::query()->with('payments')->find($order->id);
        if ($current === null) {
            return $order;
        }

        if (
            $current->status === OrderStatus::Expired
            || ! $current->isPendingPayment()
            || $current->expires_at === null
            || $current->expires_at->gt(now())
            || $current->payments->contains(fn (Payment $payment): bool => $payment->isPaid())
        ) {
            return $current;
        }

        foreach ($current->payments as $payment) {
            if (! in_array($payment->status, [PaymentStatus::Pending, PaymentStatus::Processing], true)) {
                continue;
            }

            try {
                $this->markPaymentExpired->handle($payment, $source);
            } catch (DomainException) {
                // Paid/conflict: outer lock below re-reads authority.
            }
        }

        return DB::transaction(function () use ($order, $source): Order {
            /** @var Order $locked */
            $locked = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $locked->load('payments');

            if ($locked->status === OrderStatus::Expired) {
                return $locked;
            }

            if ($locked->payments->contains(fn (Payment $payment): bool => $payment->isPaid())) {
                return $locked;
            }

            if (! $locked->isPendingPayment()) {
                return $locked;
            }

            if ($locked->expires_at === null || $locked->expires_at->gt(now())) {
                return $locked;
            }

            $before = [
                'status' => $locked->status->value,
            ];

            $now = now();
            $locked->forceFill([
                'status' => OrderStatus::Expired,
                'expired_at' => $locked->expired_at ?? $now,
            ])->save();

            $this->audit->writeCritical(
                action: 'order.expired',
                actorType: 'system',
                actorId: null,
                entityType: 'order',
                entityId: (int) $locked->id,
                before: $before,
                after: ['status' => OrderStatus::Expired->value],
                meta: [
                    'source' => $source,
                    'order_number' => $locked->order_number,
                ],
            );

            Log::info('order.expired', [
                'order_id' => $locked->id,
                'source' => $source,
            ]);

            return $locked->load('payments');
        });
    }
}

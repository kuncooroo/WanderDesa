<?php

namespace App\Actions\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Payments\PaymentStateService;
use App\Support\AuditWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class MarkPaymentExpired
{
    public function __construct(
        private readonly PaymentStateService $states,
        private readonly AuditWriter $audit,
    ) {}

    public function handle(Payment $payment, string $source = 'webhook'): Payment
    {
        return DB::transaction(function () use ($payment, $source): Payment {
            /** @var Payment $locked */
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === PaymentStatus::Expired) {
                return $locked->load('order');
            }

            if ($locked->isPaid()) {
                return $locked->load('order');
            }

            $before = [
                'status' => $locked->status instanceof PaymentStatus
                    ? $locked->status->value
                    : $locked->status,
            ];

            $this->states->transition($locked, PaymentStatus::Expired);
            $locked->forceFill([
                'expired_at' => $locked->expired_at ?? now(),
            ])->save();

            $this->audit->writeCritical(
                action: 'payment.expired',
                actorType: 'system',
                actorId: null,
                entityType: 'payment',
                entityId: (int) $locked->id,
                before: $before,
                after: ['status' => PaymentStatus::Expired->value],
                meta: [
                    'source' => $source,
                    'order_id' => $locked->order_id,
                ],
            );

            Log::info('payment.expired', [
                'payment_id' => $locked->id,
                'order_id' => $locked->order_id,
                'source' => $source,
            ]);

            return $locked->load('order');
        });
    }
}

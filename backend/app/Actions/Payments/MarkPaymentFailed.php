<?php

namespace App\Actions\Payments;

use App\Enums\PaymentStatus;
use App\Models\Device;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\PaymentStateService;
use App\Support\AuditWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class MarkPaymentFailed
{
    public function __construct(
        private readonly PaymentStateService $states,
        private readonly AuditWriter $audit,
    ) {}

    public function handle(
        Payment $payment,
        string $failureCode = 'provider.failed',
        string $failureMessage = 'Payment failed at the provider.',
        Device|User|null $actor = null,
        ?Request $request = null,
        string $source = 'webhook',
    ): Payment {
        return DB::transaction(function () use ($payment, $failureCode, $failureMessage, $actor, $request, $source): Payment {
            /** @var Payment $locked */
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === PaymentStatus::Failed) {
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

            $this->states->transition($locked, PaymentStatus::Failed);
            $locked->forceFill([
                'failed_at' => $locked->failed_at ?? now(),
                'failure_code' => $failureCode,
                'failure_message' => $failureMessage,
            ])->save();

            $this->audit->writeCritical(
                action: 'payment.failed',
                actorType: $actor instanceof Device ? 'device' : ($actor instanceof User ? 'user' : 'system'),
                actorId: $actor?->getKey(),
                entityType: 'payment',
                entityId: (int) $locked->id,
                before: $before,
                after: ['status' => PaymentStatus::Failed->value],
                meta: [
                    'source' => $source,
                    'failure_code' => $failureCode,
                    'order_id' => $locked->order_id,
                ],
                request: $request,
            );

            Log::info('payment.failed', [
                'payment_id' => $locked->id,
                'order_id' => $locked->order_id,
                'source' => $source,
                'failure_code' => $failureCode,
            ]);

            return $locked->load('order');
        });
    }
}

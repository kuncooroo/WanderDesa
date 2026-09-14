<?php

namespace App\Jobs;

use App\Actions\Payments\ApplyProviderPaymentEvent;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Integrations\Payments\PaymentGateway;
use App\Integrations\Payments\QueriesRemotePaymentStatus;
use App\Integrations\Payments\RemotePaymentStatus;
use App\Models\Payment;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ReconcileOpenPayments implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 240;

    public function __construct(
        public int $limit = 100,
    ) {}

    public function handle(PaymentGateway $gateway, ApplyProviderPaymentEvent $apply): void
    {
        if (! $gateway instanceof QueriesRemotePaymentStatus) {
            return;
        }

        $open = Payment::query()
            ->whereIn('method', [
                PaymentMethod::Qris->value,
                PaymentMethod::Debit->value,
                PaymentMethod::EWallet->value,
            ])
            ->whereIn('status', [
                PaymentStatus::Pending->value,
                PaymentStatus::Processing->value,
            ])
            ->orderBy('id')
            ->limit($this->limit)
            ->get();

        foreach ($open as $payment) {
            try {
                $remote = $gateway->fetchRemoteStatus($payment);

                if (in_array($remote, [
                    RemotePaymentStatus::Pending,
                    RemotePaymentStatus::Unknown,
                ], true)) {
                    continue;
                }

                $apply->handle($payment, $remote, source: 'reconcile');
            } catch (Throwable $e) {
                Log::warning('payment.reconcile.failed', [
                    'payment_id' => $payment->id,
                    'exception' => $e::class,
                ]);
            }
        }
    }
}

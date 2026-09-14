<?php

namespace App\Jobs;

use App\Actions\Orders\ExpireUnpaidOrder;
use App\Actions\Payments\ApplyProviderPaymentEvent;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Integrations\Payments\PaymentGateway;
use App\Integrations\Payments\QueriesRemotePaymentStatus;
use App\Integrations\Payments\RemotePaymentStatus;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ExpireUnpaidOrders implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 55;

    public function __construct(
        public int $limit = 100,
    ) {}

    public function handle(
        PaymentGateway $gateway,
        ApplyProviderPaymentEvent $apply,
        ExpireUnpaidOrder $expire,
    ): void {
        $due = Order::query()
            ->where('status', OrderStatus::PendingPayment->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->limit($this->limit)
            ->get();

        foreach ($due as $order) {
            $this->reconcileOpenDigital($order, $gateway, $apply);
            $order->refresh();

            if ($order->status !== OrderStatus::PendingPayment) {
                continue;
            }

            try {
                $expire->handle($order, 'ttl');
            } catch (Throwable $e) {
                Log::warning('order.expire.failed', [
                    'order_id' => $order->id,
                    'exception' => $e::class,
                ]);
            }
        }
    }

    private function reconcileOpenDigital(
        Order $order,
        PaymentGateway $gateway,
        ApplyProviderPaymentEvent $apply,
    ): void {
        if (! $gateway instanceof QueriesRemotePaymentStatus) {
            return;
        }

        $open = $order->payments()
            ->whereIn('method', [
                PaymentMethod::Qris->value,
                PaymentMethod::Debit->value,
                PaymentMethod::EWallet->value,
            ])
            ->whereIn('status', [
                PaymentStatus::Pending->value,
                PaymentStatus::Processing->value,
            ])
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

                $apply->handle($payment, $remote, source: 'ttl-reconcile');
            } catch (Throwable $e) {
                Log::warning('payment.ttl_reconcile.failed', [
                    'payment_id' => $payment->id,
                    'order_id' => $order->id,
                    'exception' => $e::class,
                ]);
            }
        }
    }
}

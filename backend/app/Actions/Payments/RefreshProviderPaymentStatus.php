<?php

namespace App\Actions\Payments;

use App\Integrations\Payments\PaymentGateway;
use App\Integrations\Payments\QueriesRemotePaymentStatus;
use App\Integrations\Payments\RemotePaymentStatus;
use App\Models\Payment;

/**
 * Ask the configured provider for the current status, then apply it.
 * Reading the local row alone is not a provider check.
 */
final class RefreshProviderPaymentStatus
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly ApplyProviderPaymentEvent $apply,
    ) {}

    public function handle(Payment $payment): Payment
    {
        $payment->loadMissing('order');

        if (! $payment->isOpen() || ! $payment->isProviderBacked()) {
            return $payment->fresh(['order', 'tickets']) ?? $payment;
        }

        if (! $this->gateway instanceof QueriesRemotePaymentStatus) {
            return $payment->fresh(['order', 'tickets']) ?? $payment;
        }

        $remote = $this->gateway->fetchRemoteStatus($payment);

        if (! in_array($remote, [RemotePaymentStatus::Pending, RemotePaymentStatus::Unknown], true)) {
            $this->apply->handle($payment, $remote, source: 'reconcile');
        }

        return $payment->fresh(['order', 'tickets']) ?? $payment;
    }
}

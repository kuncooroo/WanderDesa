<?php

namespace App\Integrations\Payments;

use App\Models\Order;
use App\Models\Payment;

final class NullPaymentGateway implements PaymentGateway, QueriesRemotePaymentStatus
{
    public function initiate(Payment $payment, Order $order): PaymentInitiation
    {
        return new PaymentInitiation(
            provider: 'null',
            providerPaymentId: null,
            providerReference: null,
            nextAction: null,
        );
    }

    public function refund(Payment $payment): void
    {
        // Null gateway records local refunds only (no remote provider).
    }

    public function fetchRemoteStatus(Payment $payment): RemotePaymentStatus
    {
        return RemotePaymentStatus::Unknown;
    }
}

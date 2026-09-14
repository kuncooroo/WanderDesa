<?php

namespace App\Integrations\Payments;

use App\Models\Order;
use App\Models\Payment;

interface PaymentGateway
{
    /**
     * Start a digital provider session. Must not mark the payment PAID.
     *
     * @throws PaymentGatewayException
     */
    public function initiate(Payment $payment, Order $order): PaymentInitiation;

    /**
     * Request a full refund from the digital provider when configured.
     * Must not mutate local payment/order/ticket state — Laravel applies REFUNDED after success.
     *
     * @throws PaymentGatewayException
     */
    public function refund(Payment $payment): void;
}

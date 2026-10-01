<?php

namespace App\Integrations\Payments;

use App\Models\Payment;

interface CancelsRemotePayments
{
    /**
     * Void an unpaid provider charge before the local order is cancelled.
     *
     * Returns Paid when the provider has already settled and the charge cannot be voided.
     * Returns Failed or Expired when the charge is no longer payable.
     *
     * @throws PaymentGatewayException when the provider cannot confirm the charge is closed
     */
    public function cancelRemote(Payment $payment): RemotePaymentStatus;
}

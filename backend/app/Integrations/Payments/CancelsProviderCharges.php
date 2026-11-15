<?php

namespace App\Integrations\Payments;

use App\Models\Payment;

interface CancelsProviderCharges
{
    /**
     * Stop an unpaid provider charge so it can no longer capture funds.
     *
     * @throws PaymentGatewayException when the provider cannot confirm the charge is void
     */
    public function cancelOpenCharge(Payment $payment): ProviderCancelOutcome;
}

<?php

namespace App\Integrations\Payments;

use App\Models\Payment;

interface QueriesRemotePaymentStatus
{
    public function fetchRemoteStatus(Payment $payment): RemotePaymentStatus;
}

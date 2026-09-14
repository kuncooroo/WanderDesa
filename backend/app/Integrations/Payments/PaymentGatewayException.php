<?php

namespace App\Integrations\Payments;

use RuntimeException;

final class PaymentGatewayException extends RuntimeException
{
    public function __construct(
        string $message = 'Payment provider is unavailable.',
        public readonly string $failureCode = 'upstream.payment_provider_unavailable',
    ) {
        parent::__construct($message);
    }
}

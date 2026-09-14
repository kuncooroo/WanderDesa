<?php

namespace App\Integrations\Payments;

final readonly class PaymentInitiation
{
    /**
     * @param  array{type: string, qr_content?: string, expires_at?: string|null}|null  $nextAction
     */
    public function __construct(
        public string $provider,
        public ?string $providerPaymentId,
        public ?string $providerReference,
        public ?array $nextAction,
    ) {}
}

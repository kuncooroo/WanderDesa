<?php

namespace App\Integrations\Payments;

final readonly class DecodedWebhook
{
    public function __construct(
        public string $eventId,
        public RemotePaymentStatus $status,
        public ?string $paymentNumber = null,
        public ?string $providerPaymentId = null,
        public ?int $reportedAmount = null,
    ) {}
}

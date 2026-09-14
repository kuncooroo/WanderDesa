<?php

namespace App\Integrations\Payments;

interface VerifiesPaymentWebhooks
{
    public function verifyWebhookSignature(string $rawBody, ?string $signatureHeader): void;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function decodeWebhookPayload(array $payload): DecodedWebhook;
}

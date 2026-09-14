<?php

namespace Tests\Concerns;

use Illuminate\Testing\TestResponse;

trait InteractsWithPaymentWebhooks
{
    protected function webhookSecret(): string
    {
        return 'test-webhook-secret';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function postSandboxWebhook(array $payload, ?string $signature = null): TestResponse
    {
        $raw = json_encode($payload, JSON_THROW_ON_ERROR);
        $secret = (string) config('payments.webhook_secret');
        $header = (string) config('payments.signature_header');
        $sig = $signature ?? hash_hmac('sha256', $raw, $secret);

        return $this->withHeaders([
            $header => $sig,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->json('POST', '/api/v1/webhooks/payments/sandbox', $payload);
    }
}

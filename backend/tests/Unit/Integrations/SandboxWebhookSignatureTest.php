<?php

namespace Tests\Unit\Integrations;

use App\Exceptions\AuthException;
use App\Integrations\Payments\SandboxPaymentGateway;
use Tests\TestCase;

class SandboxWebhookSignatureTest extends TestCase
{
    public function test_valid_hmac_is_accepted(): void
    {
        config(['payments.webhook_secret' => 'unit-secret']);
        $body = '{"event_id":"evt"}';
        $sig = hash_hmac('sha256', $body, 'unit-secret');

        $gateway = new SandboxPaymentGateway;
        $gateway->verifyWebhookSignature($body, $sig);
        $gateway->verifyWebhookSignature($body, 'sha256='.$sig);

        $this->assertTrue(true);
    }

    public function test_invalid_hmac_is_rejected(): void
    {
        config(['payments.webhook_secret' => 'unit-secret']);
        $gateway = new SandboxPaymentGateway;

        $this->expectException(AuthException::class);
        $gateway->verifyWebhookSignature('{"event_id":"evt"}', 'nope');
    }

    public function test_empty_secret_fails_closed(): void
    {
        config(['payments.webhook_secret' => '']);
        $gateway = new SandboxPaymentGateway;

        $this->expectException(AuthException::class);
        $gateway->verifyWebhookSignature('{"event_id":"evt"}', hash_hmac('sha256', '{"event_id":"evt"}', 'guess'));
    }
}

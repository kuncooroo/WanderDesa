<?php

namespace Tests\Feature\Kiosks;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\AssertsApiEnvelope;
use Tests\Concerns\InteractsWithOrders;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

/**
 * TASK-020 — money rejection suite on kiosk-critical write endpoints.
 */
#[Group('critical')]
class KioskApiMoneyRejectionTest extends TestCase
{
    use AssertsApiEnvelope;
    use InteractsWithOrders;
    use InteractsWithRbac;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_quote_rejects_client_grand_total(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        [, $token] = $this->activeDeviceToken($destination);

        $payload = $this->twoAdultPayload($destination, $adult);
        $payload['grand_total'] = 1;

        $response = $this->withToken($token)->postJson('/api/v1/pricing/quote', $payload);
        $this->assertErrorEnvelope($response, 422, 'money.client_values_forbidden');
    }

    public function test_create_order_rejects_nested_unit_price(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        [, $token] = $this->activeDeviceToken($destination);

        $payload = $this->twoAdultPayload($destination, $adult);
        $payload['items'][0]['unit_price'] = 1;

        $response = $this->withIdempotency($token, 'money-order')
            ->postJson('/api/v1/orders', $payload);
        $this->assertErrorEnvelope($response, 422, 'money.client_values_forbidden');
    }

    public function test_initiate_payment_rejects_client_amount(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        [, $token] = $this->activeDeviceToken($destination);
        $order = $this->createPendingOrder($token, $destination, $adult, 'money-pay-order');

        $response = $this->withIdempotency($token, 'money-pay')
            ->postJson('/api/v1/orders/'.$order['id'].'/payments', [
                'method' => PaymentMethod::EWallet->value,
                'amount' => 1,
            ]);
        $this->assertErrorEnvelope($response, 422, 'money.client_values_forbidden');
    }

    public function test_cancel_order_rejects_client_money_fields(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        [, $token] = $this->activeDeviceToken($destination);
        $order = $this->createPendingOrder($token, $destination, $adult, 'money-cancel-order');

        $response = $this->withToken($token)
            ->postJson('/api/v1/orders/'.$order['id'].'/cancel', [
                'reason' => 'visitor aborted',
                'refund_amount' => 1000,
            ]);
        $this->assertErrorEnvelope($response, 422, 'money.client_values_forbidden');
    }

    public function test_heartbeat_rejects_client_money_fields(): void
    {
        [, $token] = $this->activeDeviceToken();

        $response = $this->withToken($token)->postJson('/api/v1/kiosks/heartbeat', [
            'software_version' => '1.0.0',
            'amount' => 5000,
        ]);
        $this->assertErrorEnvelope($response, 422, 'money.client_values_forbidden');
    }

    public function test_activation_exchange_rejects_client_money_fields(): void
    {
        $response = $this->postJson('/api/v1/kiosks/activate', [
            'device_id' => 'any-device',
            'activation_code' => 'ABCD-EFGH',
            'grand_total' => 1,
        ]);
        $this->assertErrorEnvelope($response, 422, 'money.client_values_forbidden');
    }
}

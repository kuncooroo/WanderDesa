<?php

namespace Tests\Feature\Payments;

use App\Enums\DeviceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Integrations\Payments\PaymentGateway;
use App\Integrations\Payments\PaymentGatewayException;
use App\Integrations\Payments\PaymentInitiation;
use App\Integrations\Payments\SandboxPaymentGateway;
use App\Models\Device;
use App\Models\Order;
use App\Models\Payment;
use App\Support\DeviceAbilities;
use App\Support\Idempotency\IdempotencyManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithOrders;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class InitiatePaymentTest extends TestCase
{
    use InteractsWithOrders;
    use InteractsWithRbac;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_device_digital_initiate_uses_order_grand_total_and_sandbox_next_action(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        [$device, $token] = $this->activeDeviceToken($destination);
        $order = $this->createPendingOrder($token, $destination, $adult);

        $response = $this->withIdempotency($token, 'pay-digital-1')
            ->postJson('/api/v1/orders/'.$order['id'].'/payments', [
                'method' => PaymentMethod::EWallet->value,
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payment.status', PaymentStatus::Processing->value)
            ->assertJsonPath('data.payment.method', PaymentMethod::EWallet->value)
            ->assertJsonPath('data.payment.amount', $order['grand_total'])
            ->assertJsonPath('data.payment.order_id', $order['id'])
            ->assertJsonPath('data.payment.order_status', OrderStatus::PendingPayment->value)
            ->assertJsonPath('data.payment.tickets_issued', false)
            ->assertJsonPath('data.next_action.type', 'display_qr');

        $this->assertNotNull($response->json('data.next_action.qr_content'));
        $this->assertSame(103000, $response->json('data.payment.amount'));
        $this->assertStringStartsWith('PAY-', $response->json('data.payment.payment_number'));
        $this->assertDatabaseHas('payments', [
            'order_id' => $order['id'],
            'device_id' => $device->id,
            'amount' => 103000,
            'status' => PaymentStatus::Processing->value,
            'provider' => 'sandbox',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'payment.initiated',
            'actor_type' => 'device',
            'actor_id' => $device->id,
        ]);
        $this->assertSame(OrderStatus::PendingPayment, Order::query()->find($order['id'])?->status);
    }

    public function test_client_amount_is_rejected(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $token = $officer->createToken('phpunit')->plainTextToken;
        $order = $this->createPendingOrder($token, $destination, $adult, 'order-amount');

        $this->withIdempotency($token, 'pay-amount')
            ->postJson('/api/v1/orders/'.$order['id'].'/payments', [
                'method' => PaymentMethod::Cash->value,
                'amount' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'money.client_values_forbidden');

        $this->assertSame(0, Payment::query()->count());
    }

    public function test_idempotent_initiate_returns_same_payment(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        [, $token] = $this->activeDeviceToken($destination);
        $order = $this->createPendingOrder($token, $destination, $adult);

        $first = $this->withIdempotency($token, 'same-pay')
            ->postJson('/api/v1/orders/'.$order['id'].'/payments', [
                'method' => PaymentMethod::EWallet->value,
            ]);
        $first->assertCreated();

        $second = $this->withIdempotency($token, 'same-pay')
            ->postJson('/api/v1/orders/'.$order['id'].'/payments', [
                'method' => PaymentMethod::EWallet->value,
            ]);
        $second->assertCreated()
            ->assertJsonPath('data.payment.id', $first->json('data.payment.id'))
            ->assertJsonPath('data.next_action.qr_content', $first->json('data.next_action.qr_content'));

        $this->assertSame(1, Payment::query()->count());
    }

    public function test_second_initiate_while_open_is_conflict(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        [, $token] = $this->activeDeviceToken($destination);
        $order = $this->createPendingOrder($token, $destination, $adult);

        $this->withIdempotency($token, 'pay-1')
            ->postJson('/api/v1/orders/'.$order['id'].'/payments', [
                'method' => PaymentMethod::EWallet->value,
            ])
            ->assertCreated();

        $this->withIdempotency($token, 'pay-2')
            ->postJson('/api/v1/orders/'.$order['id'].'/payments', [
                'method' => PaymentMethod::EWallet->value,
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'order.state_conflict');
    }

    public function test_device_cannot_initiate_cash(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        [, $token] = $this->activeDeviceToken($destination);
        $order = $this->createPendingOrder($token, $destination, $adult);

        $this->withIdempotency($token, 'pay-cash')
            ->postJson('/api/v1/orders/'.$order['id'].'/payments', [
                'method' => PaymentMethod::Cash->value,
            ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'auth.forbidden');
    }

    public function test_gate_officer_cannot_initiate(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        $creator = $this->userWithRole(RoleName::TicketOfficer);
        $creatorToken = $creator->createToken('phpunit')->plainTextToken;
        $order = $this->createPendingOrder($creatorToken, $destination, $adult, 'gate-order');

        $gate = $this->userWithRole(RoleName::GateOfficer);
        $token = $gate->createToken('phpunit')->plainTextToken;

        $this->withIdempotency($token, 'gate-pay')
            ->postJson('/api/v1/orders/'.$order['id'].'/payments', [
                'method' => PaymentMethod::Cash->value,
            ])
            ->assertForbidden();
    }

    public function test_device_without_initiate_ability_is_forbidden(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        [$device, $createToken] = $this->activeDeviceToken($destination);
        $order = $this->createPendingOrder($createToken, $destination, $adult);

        $token = $device->createToken('kiosk', [DeviceAbilities::CATALOG_READ])->plainTextToken;

        $this->withIdempotency($token, 'no-pay')
            ->postJson('/api/v1/orders/'.$order['id'].'/payments', [
                'method' => PaymentMethod::EWallet->value,
            ])
            ->assertForbidden();
    }

    public function test_initiate_on_expired_order_is_conflict(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        [, $token] = $this->activeDeviceToken($destination);
        $order = $this->createPendingOrder($token, $destination, $adult);

        Order::query()->whereKey($order['id'])->update([
            'expires_at' => now()->subMinute(),
        ]);

        $this->withIdempotency($token, 'expired-pay')
            ->postJson('/api/v1/orders/'.$order['id'].'/payments', [
                'method' => PaymentMethod::EWallet->value,
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'order.state_conflict');
    }

    public function test_missing_idempotency_key_is_rejected(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        [, $token] = $this->activeDeviceToken($destination);
        $order = $this->createPendingOrder($token, $destination, $adult);

        $this->flushHeaders();

        $this->withToken($token)
            ->postJson('/api/v1/orders/'.$order['id'].'/payments', [
                'method' => PaymentMethod::EWallet->value,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.details.0.field', IdempotencyManager::HEADER);
    }

    public function test_gateway_failure_marks_payment_failed_and_allows_retry_with_new_key(): void
    {
        $this->app->bind(PaymentGateway::class, fn () => new class implements PaymentGateway
        {
            public function initiate(Payment $payment, Order $order): PaymentInitiation
            {
                throw new PaymentGatewayException;
            }

            public function refund(Payment $payment): void
            {
                throw new PaymentGatewayException;
            }
        });

        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        [, $token] = $this->activeDeviceToken($destination);
        $order = $this->createPendingOrder($token, $destination, $adult);

        $this->withIdempotency($token, 'pay-fail')
            ->postJson('/api/v1/orders/'.$order['id'].'/payments', [
                'method' => PaymentMethod::EWallet->value,
            ])
            ->assertStatus(502)
            ->assertJsonPath('error.code', 'upstream.payment_provider_unavailable');

        $this->assertSame(PaymentStatus::Failed, Payment::query()->firstOrFail()->status);
        $this->assertSame(OrderStatus::PendingPayment, Order::query()->find($order['id'])?->status);

        $this->app->bind(PaymentGateway::class, fn () => new SandboxPaymentGateway);

        $this->withIdempotency($token, 'pay-retry')
            ->postJson('/api/v1/orders/'.$order['id'].'/payments', [
                'method' => PaymentMethod::EWallet->value,
            ])
            ->assertCreated()
            ->assertJsonPath('data.payment.status', PaymentStatus::Processing->value);

        $this->assertSame(2, Payment::query()->count());
    }

    public function test_maintenance_device_cannot_initiate(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        $device = Device::factory()->active()->create(['destination_id' => $destination->id]);
        $token = $device->createToken('kiosk', DeviceAbilities::defaultKiosk())->plainTextToken;
        $order = $this->createPendingOrder($token, $destination, $adult);

        $device->forceFill([
            'status' => DeviceStatus::Maintenance,
            'maintenance_mode' => true,
        ])->save();

        $this->withIdempotency($token, 'maint-pay')
            ->postJson('/api/v1/orders/'.$order['id'].'/payments', [
                'method' => PaymentMethod::EWallet->value,
            ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'device.maintenance');
    }
}

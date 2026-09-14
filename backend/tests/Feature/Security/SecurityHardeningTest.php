<?php

namespace Tests\Feature\Security;

use App\Actions\Rbac\AssignRole;
use App\Enums\Channel;
use App\Enums\DeviceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\RoleName;
use App\Models\Device;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Support\DeviceAbilities;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\AssertsApiEnvelope;
use Tests\Concerns\InteractsWithOrders;
use Tests\Concerns\InteractsWithPaymentWebhooks;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

/**
 * TASK-028 — focused IDOR, privilege, webhook, rate-limit, and header checks.
 */
#[Group('critical')]
class SecurityHardeningTest extends TestCase
{
    use AssertsApiEnvelope;
    use InteractsWithOrders;
    use InteractsWithPaymentWebhooks;
    use InteractsWithRbac;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        config(['payments.webhook_secret' => $this->webhookSecret()]);
    }

    public function test_responses_include_baseline_security_headers(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_device_cannot_pay_or_cancel_another_devices_order(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        [, $ownerToken] = $this->activeDeviceToken($destination);
        $order = $this->createPendingOrder($ownerToken, $destination, $adult, 'idor-pay-order');
        [, $otherToken] = $this->activeDeviceToken($destination);

        $this->assertErrorEnvelope(
            $this->withIdempotency($otherToken, 'idor-foreign-pay')
                ->postJson('/api/v1/orders/'.$order['id'].'/payments', [
                    'method' => PaymentMethod::EWallet->value,
                ]),
            404,
            'resource.not_found',
        );

        $this->assertErrorEnvelope(
            $this->withToken($otherToken)
                ->postJson('/api/v1/orders/'.$order['id'].'/cancel', [
                    'reason' => 'foreign cancel',
                ]),
            404,
            'resource.not_found',
        );

        $this->assertSame(OrderStatus::PendingPayment, Order::query()->find($order['id'])?->status);
        $this->assertSame(0, Payment::query()->where('order_id', $order['id'])->count());
    }

    public function test_device_cannot_refund_admin_or_reports(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        [$device, $token] = $this->activeDeviceToken($destination);
        $order = $this->createPendingOrder($token, $destination, $adult, 'device-admin-order');
        $payment = Payment::factory()->paid()->create([
            'order_id' => $order['id'],
            'device_id' => $device->id,
            'amount' => $order['grand_total'],
            'method' => PaymentMethod::EWallet,
            'provider' => 'sandbox',
        ]);

        $this->assertErrorEnvelope(
            $this->withIdempotency($token, 'device-refund-hardening')
                ->postJson("/api/v1/payments/{$payment->id}/refund", ['reason' => 'no']),
            403,
            'auth.forbidden',
        );
        $this->assertErrorEnvelope(
            $this->withToken($token)->getJson('/api/v1/reports/sales/daily?date=2026-09-14'),
            403,
            'auth.forbidden',
        );
        $this->assertErrorEnvelope(
            $this->withToken($token)->getJson('/api/v1/kiosks/health'),
            403,
            'auth.forbidden',
        );
        $this->assertErrorEnvelope(
            $this->withToken($token)->getJson('/api/v1/notifications'),
            403,
            'auth.forbidden',
        );
        $this->withToken($token)
            ->postJson('/api/v1/users/1/roles', ['role' => 'super_admin'])
            ->assertNotFound();
    }

    public function test_disabled_device_status_is_blocked_even_if_flag_is_wrong(): void
    {
        $device = Device::factory()->create([
            'status' => DeviceStatus::Disabled,
            'is_active' => true,
            'deactivated_at' => now(),
        ]);
        $token = $device->createToken('kiosk', DeviceAbilities::defaultKiosk())->plainTextToken;

        $this->assertErrorEnvelope(
            $this->withToken($token)->getJson('/api/v1/kiosks/me'),
            403,
            'device.inactive',
        );
    }

    public function test_admin_cannot_assign_roles(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $target = User::factory()->create();

        $this->assertFalse($admin->can('assignRoles', $target));

        $this->expectException(AuthorizationException::class);
        app(AssignRole::class)->handle($admin, $target, RoleName::SuperAdmin);
    }

    public function test_empty_webhook_secret_rejects_signed_request(): void
    {
        config(['payments.webhook_secret' => '']);

        $this->postSandboxWebhook([
            'event_id' => 'evt_hardening_nosecret',
            'type' => 'payment.paid',
        ], signature: hash_hmac('sha256', '{}', 'guess'))
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'webhook.signature_invalid');
    }

    public function test_commerce_and_validate_routes_return_429_when_limited(): void
    {
        RateLimiter::for('commerce', fn () => Limit::perMinute(1)->by('hardening-commerce'));
        RateLimiter::for('access', fn () => Limit::perMinute(1)->by('hardening-access'));

        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        [, $deviceToken] = $this->activeDeviceToken($destination);

        $this->withIdempotency($deviceToken, 'rl-order-1')
            ->postJson('/api/v1/orders', $this->twoAdultPayload($destination, $adult))
            ->assertCreated();

        $this->assertErrorEnvelope(
            $this->withIdempotency($deviceToken, 'rl-order-2')
                ->postJson('/api/v1/orders', $this->twoAdultPayload($destination, $adult)),
            429,
            'rate_limit.exceeded',
        );

        $gate = $this->userWithRole(RoleName::GateOfficer)->createToken('phpunit')->plainTextToken;
        $validate = [
            'qr_payload' => 'WD1.v1.'.str_repeat('a', 32).'.'.str_repeat('b', 32),
            'destination_id' => $destination->id,
        ];
        $this->withToken($gate)
            ->postJson('/api/v1/tickets/validate', $validate)
            ->assertOk();

        $this->assertErrorEnvelope(
            $this->withToken($gate)
                ->postJson('/api/v1/tickets/validate', [
                    'qr_payload' => 'WD1.v1.'.str_repeat('c', 32).'.'.str_repeat('d', 32),
                    'destination_id' => $destination->id,
                ]),
            429,
            'rate_limit.exceeded',
        );
    }

    public function test_staff_without_view_cannot_read_foreign_payment(): void
    {
        ['destination' => $destination] = $this->sellableCatalog();
        $order = Order::factory()->create([
            'destination_id' => $destination->id,
            'channel' => Channel::Assisted,
        ]);
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => $order->grand_total,
        ]);

        $officer = $this->userWithRole(RoleName::GateOfficer);
        $token = $officer->createToken('phpunit')->plainTextToken;

        $this->assertErrorEnvelope(
            $this->withToken($token)->getJson("/api/v1/payments/{$payment->id}"),
            403,
            'auth.forbidden',
        );
        $this->assertErrorEnvelope(
            $this->withToken($token)->getJson("/api/v1/orders/{$order->id}"),
            403,
            'auth.forbidden',
        );
    }

    public function test_dashboard_denies_privileged_routes_without_permission(): void
    {
        $officer = $this->userWithRole(RoleName::TicketOfficer);

        $this->actingAs($officer)
            ->get(route('dashboard.audit-logs'))
            ->assertForbidden()
            ->assertSee('Tidak berwenang');
    }
}

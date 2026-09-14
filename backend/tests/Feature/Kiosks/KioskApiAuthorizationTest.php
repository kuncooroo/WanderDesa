<?php

namespace Tests\Feature\Kiosks;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Models\Device;
use App\Models\Order;
use App\Models\Payment;
use App\Support\DeviceAbilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AssertsApiEnvelope;
use Tests\Concerns\InteractsWithOrders;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

/**
 * TASK-020 — device least privilege + IDOR scoping for kiosk API.
 */
class KioskApiAuthorizationTest extends TestCase
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

    public function test_device_cannot_refund(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        [$device, $token] = $this->activeDeviceToken($destination);
        $orderData = $this->createPendingOrder($token, $destination, $adult, 'refund-block-order');

        $payment = Payment::factory()->paid()->create([
            'order_id' => $orderData['id'],
            'device_id' => $device->id,
            'amount' => $orderData['grand_total'],
            'method' => PaymentMethod::EWallet,
            'provider' => 'sandbox',
        ]);
        Order::query()->whereKey($orderData['id'])->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $response = $this->withIdempotency($token, 'device-refund')
            ->postJson("/api/v1/payments/{$payment->id}/refund", [
                'reason' => 'kiosk must not refund',
            ]);

        $this->assertErrorEnvelope($response, 403, 'auth.forbidden');
        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
    }

    public function test_device_cannot_confirm_cash(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        [$device, $token] = $this->activeDeviceToken($destination);
        $orderData = $this->createPendingOrder($token, $destination, $adult, 'cash-block-order');

        $payment = Payment::factory()->cash()->create([
            'order_id' => $orderData['id'],
            'device_id' => $device->id,
            'amount' => $orderData['grand_total'],
            'status' => PaymentStatus::Pending,
            'provider' => 'cash',
        ]);

        $response = $this->withIdempotency($token, 'device-cash')
            ->postJson("/api/v1/payments/{$payment->id}/confirm-cash", [
                'received' => true,
            ]);

        $this->assertErrorEnvelope($response, 403, 'auth.forbidden');
    }

    public function test_device_cannot_manage_destinations_or_register_kiosks(): void
    {
        [, $token] = $this->activeDeviceToken();

        $this->assertErrorEnvelope(
            $this->withToken($token)->postJson('/api/v1/destinations', [
                'code' => 'HACK',
                'name' => 'Hack',
                'timezone' => 'Asia/Jakarta',
            ]),
            403,
            'auth.forbidden',
        );

        $this->assertErrorEnvelope(
            $this->withToken($token)->postJson('/api/v1/kiosks', [
                'device_id' => 'evil-device',
                'destination_id' => 1,
                'name' => 'Evil',
            ]),
            403,
            'auth.forbidden',
        );

        $this->assertErrorEnvelope(
            $this->withToken($token)->getJson('/api/v1/kiosks/health'),
            403,
            'auth.forbidden',
        );
    }

    public function test_device_cannot_read_another_devices_order_or_payment(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        [, $ownerToken] = $this->activeDeviceToken($destination);
        $order = $this->createPendingOrder($ownerToken, $destination, $adult, 'idor-order');

        $pay = $this->withIdempotency($ownerToken, 'idor-pay')
            ->postJson('/api/v1/orders/'.$order['id'].'/payments', [
                'method' => PaymentMethod::EWallet->value,
            ]);
        $pay->assertCreated();
        $paymentId = (int) $pay->json('data.payment.id');

        [, $otherToken] = $this->activeDeviceToken($destination);

        $this->assertErrorEnvelope(
            $this->withToken($otherToken)->getJson('/api/v1/orders/'.$order['id']),
            404,
            'resource.not_found',
        );

        $this->assertErrorEnvelope(
            $this->withToken($otherToken)->getJson("/api/v1/payments/{$paymentId}"),
            404,
            'resource.not_found',
        );
    }

    public function test_device_missing_orders_create_ability_is_forbidden(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        [, $token] = $this->activeDeviceToken($destination, [
            DeviceAbilities::CATALOG_READ,
            DeviceAbilities::HEARTBEAT,
        ]);

        $this->assertErrorEnvelope(
            $this->withIdempotency($token, 'no-create')
                ->postJson('/api/v1/orders', $this->twoAdultPayload($destination, $adult)),
            403,
            'auth.forbidden',
        );
    }

    public function test_maintenance_device_blocked_on_commerce_with_stable_error(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        $device = Device::factory()->maintenance()->create([
            'destination_id' => $destination->id,
        ]);
        $token = $device->createToken('kiosk', DeviceAbilities::defaultKiosk())->plainTextToken;

        $this->assertErrorEnvelope(
            $this->withIdempotency($token, 'maint-commerce')
                ->postJson('/api/v1/orders', $this->twoAdultPayload($destination, $adult)),
            403,
            'device.maintenance',
        );
    }

    public function test_staff_without_kiosk_permission_cannot_use_device_admin_routes(): void
    {
        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $token = $officer->createToken('phpunit')->plainTextToken;

        $this->assertErrorEnvelope(
            $this->withToken($token)->getJson('/api/v1/kiosks/health'),
            403,
            'auth.forbidden',
        );
    }
}

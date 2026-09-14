<?php

namespace Tests\Feature\Payments;

use App\Enums\Channel;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Models\Device;
use App\Models\Order;
use App\Models\Payment;
use App\Support\DeviceAbilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithOrders;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class ShowPaymentTest extends TestCase
{
    use InteractsWithOrders;
    use InteractsWithRbac;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_device_can_poll_own_payment(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        [, $token] = $this->activeDeviceToken($destination);
        $order = $this->createPendingOrder($token, $destination, $adult);
        $paymentId = $this->withIdempotency($token, 'poll-init')
            ->postJson('/api/v1/orders/'.$order['id'].'/payments', [
                'method' => PaymentMethod::EWallet->value,
            ])
            ->json('data.payment.id');

        $this->withToken($token)
            ->getJson("/api/v1/payments/{$paymentId}")
            ->assertOk()
            ->assertJsonPath('data.id', $paymentId)
            ->assertJsonPath('data.status', PaymentStatus::Processing->value)
            ->assertJsonPath('data.tickets_issued', false);
    }

    public function test_device_cannot_view_another_device_payment(): void
    {
        ['destination' => $destination] = $this->sellableCatalog();
        $owner = Device::factory()->active()->create(['destination_id' => $destination->id]);
        $order = Order::factory()->create([
            'destination_id' => $destination->id,
            'device_id' => $owner->id,
            'channel' => Channel::Kiosk,
        ]);
        $payment = Payment::factory()->processing()->create([
            'order_id' => $order->id,
            'device_id' => $owner->id,
            'amount' => $order->grand_total,
        ]);

        $other = Device::factory()->active()->create(['destination_id' => $destination->id]);
        $token = $other->createToken('kiosk', DeviceAbilities::defaultKiosk())->plainTextToken;

        $this->withToken($token)
            ->getJson("/api/v1/payments/{$payment->id}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'resource.not_found');
    }

    public function test_auditor_can_view_payment(): void
    {
        ['destination' => $destination] = $this->sellableCatalog();
        $order = Order::factory()->create(['destination_id' => $destination->id]);
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => $order->grand_total,
        ]);

        $auditor = $this->userWithRole(RoleName::Auditor);
        $token = $auditor->createToken('phpunit')->plainTextToken;

        $this->withToken($token)
            ->getJson("/api/v1/payments/{$payment->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $payment->id);
    }

    public function test_gate_officer_cannot_view_payment(): void
    {
        ['destination' => $destination] = $this->sellableCatalog();
        $order = Order::factory()->create(['destination_id' => $destination->id]);
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => $order->grand_total,
        ]);

        $officer = $this->userWithRole(RoleName::GateOfficer);
        $token = $officer->createToken('phpunit')->plainTextToken;

        $this->withToken($token)
            ->getJson("/api/v1/payments/{$payment->id}")
            ->assertForbidden();
    }
}

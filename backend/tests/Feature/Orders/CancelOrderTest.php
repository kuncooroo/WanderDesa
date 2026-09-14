<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderStatus;
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

class CancelOrderTest extends TestCase
{
    use InteractsWithOrders;
    use InteractsWithRbac;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_device_can_cancel_own_pending_order(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        [$device, $token] = $this->activeDeviceToken($destination);

        $create = $this->withIdempotency($token, 'cancel-own')
            ->postJson('/api/v1/orders', $this->twoAdultPayload($destination, $adult));
        $create->assertCreated();
        $orderId = $create->json('data.id');

        Payment::factory()->create([
            'order_id' => $orderId,
            'device_id' => $device->id,
            'status' => PaymentStatus::Pending,
            'amount' => $create->json('data.grand_total'),
        ]);

        $this->withToken($token)
            ->postJson("/api/v1/orders/{$orderId}/cancel", ['reason' => 'visitor abandoned'])
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::Cancelled->value)
            ->assertJsonPath('data.tickets', []);

        $this->assertNotNull(Order::query()->find($orderId)?->cancelled_at);
        $this->assertSame(PaymentStatus::Cancelled, Payment::query()->firstOrFail()->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'order.cancelled',
            'actor_type' => 'device',
            'actor_id' => $device->id,
            'entity_id' => $orderId,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'payment.cancelled',
            'entity_type' => 'payment',
        ]);
    }

    public function test_staff_can_cancel_pending_order(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $token = $officer->createToken('phpunit')->plainTextToken;

        $create = $this->withIdempotency($token, 'staff-cancel')
            ->postJson('/api/v1/orders', $this->twoAdultPayload($destination, $adult));
        $create->assertCreated();

        $this->withToken($token)
            ->postJson('/api/v1/orders/'.$create->json('data.id').'/cancel')
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::Cancelled->value);
    }

    public function test_paid_order_cannot_be_cancelled(): void
    {
        ['destination' => $destination] = $this->sellableCatalog();
        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $token = $officer->createToken('phpunit')->plainTextToken;

        $order = Order::factory()->paid()->create([
            'destination_id' => $destination->id,
            'created_by_user_id' => $officer->id,
        ]);
        Payment::factory()->paid()->create([
            'order_id' => $order->id,
            'amount' => $order->grand_total,
        ]);

        $this->withToken($token)
            ->postJson("/api/v1/orders/{$order->id}/cancel")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'order.state_conflict');

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    public function test_already_cancelled_order_is_rejected(): void
    {
        ['destination' => $destination] = $this->sellableCatalog();
        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $token = $officer->createToken('phpunit')->plainTextToken;

        $order = Order::factory()->cancelled()->create([
            'destination_id' => $destination->id,
            'created_by_user_id' => $officer->id,
        ]);

        $this->withToken($token)
            ->postJson("/api/v1/orders/{$order->id}/cancel")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'order.state_conflict');
    }

    public function test_auditor_cannot_cancel(): void
    {
        ['destination' => $destination] = $this->sellableCatalog();
        $creator = $this->userWithRole(RoleName::TicketOfficer);
        $order = Order::factory()->create([
            'destination_id' => $destination->id,
            'created_by_user_id' => $creator->id,
        ]);

        $auditor = $this->userWithRole(RoleName::Auditor);
        $token = $auditor->createToken('phpunit')->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/v1/orders/{$order->id}/cancel")
            ->assertForbidden()
            ->assertJsonPath('error.code', 'auth.forbidden');
    }

    public function test_device_cannot_cancel_another_device_order(): void
    {
        ['destination' => $destination] = $this->sellableCatalog();
        $owner = Device::factory()->active()->create(['destination_id' => $destination->id]);
        $order = Order::factory()->create([
            'destination_id' => $destination->id,
            'device_id' => $owner->id,
        ]);

        $other = Device::factory()->active()->create(['destination_id' => $destination->id]);
        $token = $other->createToken('kiosk', DeviceAbilities::defaultKiosk())->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/v1/orders/{$order->id}/cancel")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'resource.not_found');

        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
    }
}

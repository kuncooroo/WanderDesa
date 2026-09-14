<?php

namespace Tests\Feature\Orders;

use App\Enums\Channel;
use App\Enums\RoleName;
use App\Models\Device;
use App\Models\Order;
use App\Support\DeviceAbilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithOrders;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class ShowOrderTest extends TestCase
{
    use InteractsWithOrders;
    use InteractsWithRbac;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_device_can_view_own_order(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        [, $token] = $this->activeDeviceToken($destination);

        $create = $this->withIdempotency($token, 'show-own')
            ->postJson('/api/v1/orders', $this->twoAdultPayload($destination, $adult));
        $create->assertCreated();
        $id = $create->json('data.id');

        $this->withToken($token)
            ->getJson("/api/v1/orders/{$id}")
            ->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.channel', Channel::Kiosk->value)
            ->assertJsonPath('data.items.0.ticket_type_id', $adult->id)
            ->assertJsonPath('data.payments', [])
            ->assertJsonPath('data.tickets', []);
    }

    public function test_device_cannot_view_another_device_order(): void
    {
        ['destination' => $destination] = $this->sellableCatalog();
        $owner = Device::factory()->active()->create(['destination_id' => $destination->id]);
        $order = Order::factory()->create([
            'destination_id' => $destination->id,
            'device_id' => $owner->id,
            'channel' => Channel::Kiosk,
        ]);

        $other = Device::factory()->active()->create(['destination_id' => $destination->id]);
        $token = $other->createToken('kiosk', DeviceAbilities::defaultKiosk())->plainTextToken;

        $this->withToken($token)
            ->getJson("/api/v1/orders/{$order->id}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'resource.not_found');
    }

    public function test_staff_with_orders_view_can_read_any_order(): void
    {
        ['destination' => $destination] = $this->sellableCatalog();
        $order = Order::factory()->create([
            'destination_id' => $destination->id,
        ]);

        $auditor = $this->userWithRole(RoleName::Auditor);
        $token = $auditor->createToken('phpunit')->plainTextToken;

        $this->withToken($token)
            ->getJson("/api/v1/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $order->id);
    }

    public function test_gate_officer_cannot_view_order(): void
    {
        ['destination' => $destination] = $this->sellableCatalog();
        $order = Order::factory()->create([
            'destination_id' => $destination->id,
        ]);

        $officer = $this->userWithRole(RoleName::GateOfficer);
        $token = $officer->createToken('phpunit')->plainTextToken;

        $this->withToken($token)
            ->getJson("/api/v1/orders/{$order->id}")
            ->assertForbidden()
            ->assertJsonPath('error.code', 'auth.forbidden');
    }
}

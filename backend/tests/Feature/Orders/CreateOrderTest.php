<?php

namespace Tests\Feature\Orders;

use App\Enums\Channel;
use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\Destination;
use App\Models\Device;
use App\Models\Order;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Support\DeviceAbilities;
use App\Support\Idempotency\IdempotencyManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithOrders;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class CreateOrderTest extends TestCase
{
    use InteractsWithOrders;
    use InteractsWithRbac;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_device_create_matches_quote_and_persists_snapshots(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        [$device, $token] = $this->activeDeviceToken($destination);
        $payload = $this->twoAdultPayload($destination, $adult);

        $quote = $this->withToken($token)->postJson('/api/v1/pricing/quote', $payload);
        $quote->assertOk();

        $response = $this->withIdempotency($token, 'kiosk-order-1')
            ->postJson('/api/v1/orders', $payload);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', OrderStatus::PendingPayment->value)
            ->assertJsonPath('data.channel', Channel::Kiosk->value)
            ->assertJsonPath('data.device_id', $device->id)
            ->assertJsonPath('data.created_by_user_id', null)
            ->assertJsonPath('data.currency', 'IDR')
            ->assertJsonPath('data.subtotal', 100000)
            ->assertJsonPath('data.discount_total', 0)
            ->assertJsonPath('data.tax_total', 2000)
            ->assertJsonPath('data.service_fee_total', 1000)
            ->assertJsonPath('data.grand_total', $quote->json('data.grand_total'))
            ->assertJsonPath('data.items.0.ticket_type_code', 'ADULT')
            ->assertJsonPath('data.items.0.unit_price', 50000)
            ->assertJsonPath('data.items.0.line_grand_total', 103000)
            ->assertJsonPath('data.tickets', []);

        $this->assertNotNull($response->json('data.order_number'));
        $this->assertStringStartsWith('WD-', $response->json('data.order_number'));
        $this->assertNotNull($response->json('data.expires_at'));
        $this->assertSame(0, Ticket::query()->count());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'order.created',
            'actor_type' => 'device',
            'actor_id' => $device->id,
            'entity_type' => 'order',
        ]);

        $adult->update(['unit_price' => 1]);
        $order = Order::query()->firstOrFail();
        $this->assertSame(50000, $order->items()->firstOrFail()->unit_price);
        $this->assertSame(103000, $order->grand_total);
    }

    public function test_staff_create_uses_assisted_channel(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $token = $officer->createToken('phpunit')->plainTextToken;

        $response = $this->withIdempotency($token, 'assisted-order-1')
            ->postJson('/api/v1/orders', [
                ...$this->twoAdultPayload($destination, $adult),
                'channel' => Channel::Kiosk->value,
                'customer_note' => 'Walk-in',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.channel', Channel::Assisted->value)
            ->assertJsonPath('data.created_by_user_id', $officer->id)
            ->assertJsonPath('data.device_id', null)
            ->assertJsonPath('data.customer_note', 'Walk-in')
            ->assertJsonPath('data.status', OrderStatus::PendingPayment->value);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'order.created',
            'actor_type' => 'user',
            'actor_id' => $officer->id,
        ]);
    }

    public function test_expires_at_uses_settings_ttl(): void
    {
        $this->freezeTime();
        Setting::factory()->create([
            'key' => Setting::ORDER_PAYMENT_TTL_MINUTES,
            'value' => '20',
            'type' => 'int',
        ]);

        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $token = $officer->createToken('phpunit')->plainTextToken;

        $response = $this->withIdempotency($token, 'ttl-order-1')
            ->postJson('/api/v1/orders', $this->twoAdultPayload($destination, $adult));

        $response->assertCreated();
        $this->assertSame(
            now()->addMinutes(20)->utc()->format('Y-m-d\TH:i:s\Z'),
            $response->json('data.expires_at'),
        );
    }

    public function test_idempotent_replay_returns_same_order(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        [, $token] = $this->activeDeviceToken($destination);
        $payload = $this->twoAdultPayload($destination, $adult);

        $first = $this->withIdempotency($token, 'same-key')
            ->postJson('/api/v1/orders', $payload);
        $first->assertCreated();

        $second = $this->withIdempotency($token, 'same-key')
            ->postJson('/api/v1/orders', $payload);
        $second->assertCreated()
            ->assertJsonPath('data.id', $first->json('data.id'))
            ->assertJsonPath('data.order_number', $first->json('data.order_number'))
            ->assertJsonPath('data.grand_total', $first->json('data.grand_total'));

        $this->assertSame(1, Order::query()->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'order.created')->count());
    }

    public function test_idempotency_payload_conflict_is_rejected(): void
    {
        ['destination' => $destination, 'adult' => $adult, 'child' => $child] = $this->sellableCatalog();
        [, $token] = $this->activeDeviceToken($destination);

        $this->withIdempotency($token, 'conflict-key')
            ->postJson('/api/v1/orders', $this->twoAdultPayload($destination, $adult))
            ->assertCreated();

        $this->withIdempotency($token, 'conflict-key')
            ->postJson('/api/v1/orders', [
                'destination_id' => $destination->id,
                'items' => [
                    ['ticket_type_id' => $child->id, 'quantity' => 1],
                ],
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'idempotency.payload_conflict');

        $this->assertSame(1, Order::query()->count());
    }

    public function test_missing_idempotency_key_is_rejected(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        [, $token] = $this->activeDeviceToken($destination);

        $this->withToken($token)
            ->postJson('/api/v1/orders', $this->twoAdultPayload($destination, $adult))
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation.failed')
            ->assertJsonPath('error.details.0.field', IdempotencyManager::HEADER);
    }

    public function test_client_money_fields_are_rejected(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $token = $officer->createToken('phpunit')->plainTextToken;

        $this->withIdempotency($token, 'money-key')
            ->postJson('/api/v1/orders', [
                'destination_id' => $destination->id,
                'grand_total' => 1,
                'items' => [
                    [
                        'ticket_type_id' => $adult->id,
                        'quantity' => 1,
                        'unit_price' => 1,
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'money.client_values_forbidden');

        $this->assertSame(0, Order::query()->count());
    }

    public function test_gate_officer_cannot_create_order(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        $officer = $this->userWithRole(RoleName::GateOfficer);
        $token = $officer->createToken('phpunit')->plainTextToken;

        $this->withIdempotency($token, 'gate-create')
            ->postJson('/api/v1/orders', $this->twoAdultPayload($destination, $adult))
            ->assertForbidden()
            ->assertJsonPath('error.code', 'auth.forbidden');
    }

    public function test_device_without_orders_create_ability_is_forbidden(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        [, $token] = $this->activeDeviceToken($destination, [DeviceAbilities::CATALOG_READ]);

        $this->withIdempotency($token, 'no-ability')
            ->postJson('/api/v1/orders', $this->twoAdultPayload($destination, $adult))
            ->assertForbidden()
            ->assertJsonPath('error.code', 'auth.forbidden');
    }

    public function test_maintenance_device_cannot_create_order(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        $device = Device::factory()->maintenance()->create([
            'destination_id' => $destination->id,
        ]);
        $token = $device->createToken('kiosk', DeviceAbilities::defaultKiosk())->plainTextToken;

        $this->withIdempotency($token, 'maint-order')
            ->postJson('/api/v1/orders', $this->twoAdultPayload($destination, $adult))
            ->assertForbidden()
            ->assertJsonPath('error.code', 'device.maintenance');
    }

    public function test_disabled_device_cannot_create_order(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        $device = Device::factory()->disabled()->create([
            'destination_id' => $destination->id,
        ]);
        $token = $device->createToken('kiosk', DeviceAbilities::defaultKiosk())->plainTextToken;

        $this->withIdempotency($token, 'disabled-order')
            ->postJson('/api/v1/orders', $this->twoAdultPayload($destination, $adult))
            ->assertForbidden()
            ->assertJsonPath('error.code', 'device.inactive');
    }

    public function test_inactive_ticket_type_is_rejected(): void
    {
        $destination = Destination::factory()->create(['is_active' => true]);
        $type = TicketType::factory()->inactive()->create([
            'destination_id' => $destination->id,
            'unit_price' => 50000,
        ]);
        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $token = $officer->createToken('phpunit')->plainTextToken;

        $this->withIdempotency($token, 'inactive-type')
            ->postJson('/api/v1/orders', [
                'destination_id' => $destination->id,
                'items' => [
                    ['ticket_type_id' => $type->id, 'quantity' => 1],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation.failed');
    }

    public function test_empty_items_are_rejected(): void
    {
        $destination = Destination::factory()->create(['is_active' => true]);
        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $token = $officer->createToken('phpunit')->plainTextToken;

        $this->withIdempotency($token, 'empty-items')
            ->postJson('/api/v1/orders', [
                'destination_id' => $destination->id,
                'items' => [],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation.failed');
    }

    public function test_two_creates_get_unique_order_numbers(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        [, $token] = $this->activeDeviceToken($destination);
        $payload = $this->twoAdultPayload($destination, $adult);

        $first = $this->withIdempotency($token, 'num-1')->postJson('/api/v1/orders', $payload);
        $second = $this->withIdempotency($token, 'num-2')->postJson('/api/v1/orders', $payload);

        $first->assertCreated();
        $second->assertCreated();
        $this->assertNotSame($first->json('data.order_number'), $second->json('data.order_number'));
        $this->assertSame(2, Order::query()->count());
    }
}

<?php

namespace Tests\Feature\Pricing;

use App\Enums\RoleName;
use App\Models\Destination;
use App\Models\Device;
use App\Models\TicketType;
use App\Support\DeviceAbilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class QuotePricingTest extends TestCase
{
    use InteractsWithRbac;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_device_can_quote_authoritative_totals(): void
    {
        $destination = Destination::factory()->create(['is_active' => true]);
        $adult = TicketType::factory()->create([
            'destination_id' => $destination->id,
            'code' => 'ADULT',
            'name' => 'Dewasa',
            'unit_price' => 50000,
            'tax_amount' => 1000,
            'service_fee_amount' => 500,
            'max_per_order' => 10,
            'is_active' => true,
        ]);
        $child = TicketType::factory()->create([
            'destination_id' => $destination->id,
            'code' => 'CHILD',
            'name' => 'Anak',
            'unit_price' => 25000,
            'tax_amount' => 0,
            'service_fee_amount' => 250,
            'max_per_order' => 10,
            'is_active' => true,
        ]);

        $device = Device::factory()->create(['is_active' => true]);
        $token = $device->createToken('kiosk', DeviceAbilities::defaultKiosk())->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/v1/pricing/quote', [
            'destination_id' => $destination->id,
            'items' => [
                ['ticket_type_id' => $adult->id, 'quantity' => 2, 'visit_date' => '2026-09-13'],
                ['ticket_type_id' => $child->id, 'quantity' => 1],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.currency', 'IDR')
            ->assertJsonPath('data.subtotal', 125000)
            ->assertJsonPath('data.discount_total', 0)
            ->assertJsonPath('data.tax_total', 2000)
            ->assertJsonPath('data.service_fee_total', 1250)
            ->assertJsonPath('data.grand_total', 128250)
            ->assertJsonPath('data.items.0.unit_price', 50000)
            ->assertJsonPath('data.items.0.line_grand_total', 103000)
            ->assertJsonPath('data.items.1.line_grand_total', 25250);
    }

    public function test_ticket_officer_can_quote_same_totals_as_device(): void
    {
        $destination = Destination::factory()->create(['is_active' => true]);
        $adult = TicketType::factory()->create([
            'destination_id' => $destination->id,
            'unit_price' => 50000,
            'tax_amount' => 1000,
            'service_fee_amount' => 500,
            'is_active' => true,
        ]);

        $payload = [
            'destination_id' => $destination->id,
            'items' => [
                ['ticket_type_id' => $adult->id, 'quantity' => 2],
            ],
        ];

        $device = Device::factory()->create(['is_active' => true]);
        $deviceToken = $device->createToken('kiosk', DeviceAbilities::defaultKiosk())->plainTextToken;
        $deviceQuote = $this->withToken($deviceToken)->postJson('/api/v1/pricing/quote', $payload);
        $deviceQuote->assertOk();

        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $staffToken = $officer->createToken('phpunit')->plainTextToken;
        $staffQuote = $this->withToken($staffToken)->postJson('/api/v1/pricing/quote', $payload);
        $staffQuote->assertOk();

        $this->assertSame(
            $deviceQuote->json('data.grand_total'),
            $staffQuote->json('data.grand_total'),
        );
        $this->assertSame(103000, $staffQuote->json('data.grand_total'));
    }

    public function test_client_grand_total_is_rejected(): void
    {
        $destination = Destination::factory()->create(['is_active' => true]);
        $type = TicketType::factory()->create([
            'destination_id' => $destination->id,
            'unit_price' => 50000,
            'is_active' => true,
        ]);

        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $token = $officer->createToken('phpunit')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/pricing/quote', [
            'destination_id' => $destination->id,
            'items' => [
                [
                    'ticket_type_id' => $type->id,
                    'quantity' => 1,
                    'unit_price' => 1,
                ],
            ],
            'grand_total' => 1,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'money.client_values_forbidden');
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

        $this->withToken($token)->postJson('/api/v1/pricing/quote', [
            'destination_id' => $destination->id,
            'items' => [
                ['ticket_type_id' => $type->id, 'quantity' => 1],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation.failed');
    }

    public function test_mixed_destinations_are_rejected(): void
    {
        $destinationA = Destination::factory()->create(['is_active' => true]);
        $destinationB = Destination::factory()->create(['is_active' => true]);
        $typeB = TicketType::factory()->create([
            'destination_id' => $destinationB->id,
            'unit_price' => 30000,
            'is_active' => true,
        ]);

        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $token = $officer->createToken('phpunit')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/pricing/quote', [
            'destination_id' => $destinationA->id,
            'items' => [
                ['ticket_type_id' => $typeB->id, 'quantity' => 1],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation.failed');
    }

    public function test_max_per_order_exceeded_is_rejected(): void
    {
        $destination = Destination::factory()->create(['is_active' => true]);
        $type = TicketType::factory()->create([
            'destination_id' => $destination->id,
            'unit_price' => 10000,
            'max_per_order' => 3,
            'is_active' => true,
        ]);

        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $token = $officer->createToken('phpunit')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/pricing/quote', [
            'destination_id' => $destination->id,
            'items' => [
                ['ticket_type_id' => $type->id, 'quantity' => 2],
                ['ticket_type_id' => $type->id, 'quantity' => 2],
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

        $this->withToken($token)->postJson('/api/v1/pricing/quote', [
            'destination_id' => $destination->id,
            'items' => [],
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation.failed');
    }

    public function test_gate_officer_cannot_quote(): void
    {
        $destination = Destination::factory()->create(['is_active' => true]);
        $type = TicketType::factory()->create([
            'destination_id' => $destination->id,
            'is_active' => true,
        ]);

        $officer = $this->userWithRole(RoleName::GateOfficer);
        $token = $officer->createToken('phpunit')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/pricing/quote', [
            'destination_id' => $destination->id,
            'items' => [
                ['ticket_type_id' => $type->id, 'quantity' => 1],
            ],
        ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'auth.forbidden');
    }

    public function test_device_without_catalog_read_cannot_quote(): void
    {
        $destination = Destination::factory()->create(['is_active' => true]);
        $type = TicketType::factory()->create([
            'destination_id' => $destination->id,
            'is_active' => true,
        ]);

        $device = Device::factory()->create(['is_active' => true]);
        $token = $device->createToken('kiosk', [DeviceAbilities::HEARTBEAT])->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/pricing/quote', [
            'destination_id' => $destination->id,
            'items' => [
                ['ticket_type_id' => $type->id, 'quantity' => 1],
            ],
        ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'auth.forbidden');
    }

    public function test_tampered_client_unit_price_does_not_change_server_total(): void
    {
        $destination = Destination::factory()->create(['is_active' => true]);
        $type = TicketType::factory()->create([
            'destination_id' => $destination->id,
            'unit_price' => 50000,
            'tax_amount' => 0,
            'service_fee_amount' => 0,
            'is_active' => true,
        ]);

        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $token = $officer->createToken('phpunit')->plainTextToken;

        // Nested money field must be rejected entirely (MVP = reject, not strip).
        $this->withToken($token)->postJson('/api/v1/pricing/quote', [
            'destination_id' => $destination->id,
            'items' => [
                [
                    'ticket_type_id' => $type->id,
                    'quantity' => 2,
                    'unit_price' => 1,
                ],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'money.client_values_forbidden');

        $ok = $this->withToken($token)->postJson('/api/v1/pricing/quote', [
            'destination_id' => $destination->id,
            'items' => [
                ['ticket_type_id' => $type->id, 'quantity' => 2],
            ],
        ]);

        $ok->assertOk()
            ->assertJsonPath('data.grand_total', 100000)
            ->assertJsonPath('data.items.0.unit_price', 50000);
    }
}

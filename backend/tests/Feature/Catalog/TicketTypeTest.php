<?php

namespace Tests\Feature\Catalog;

use App\Enums\RoleName;
use App\Enums\ValidityType;
use App\Models\AuditLog;
use App\Models\Destination;
use App\Models\Device;
use App\Models\TicketType;
use App\Support\DeviceAbilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class TicketTypeTest extends TestCase
{
    use InteractsWithRbac;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_admin_can_create_ticket_type(): void
    {
        $destination = Destination::factory()->create(['code' => 'DESA-01']);
        $admin = $this->userWithRole(RoleName::Admin);
        $token = $admin->createToken('phpunit')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/v1/ticket-types', [
            'destination_id' => $destination->id,
            'code' => 'ADULT',
            'name' => 'Tiket Dewasa',
            'unit_price' => 50000,
            'tax_amount' => 0,
            'service_fee_amount' => 2500,
            'validity_type' => ValidityType::SameDay->value,
            'max_per_order' => 10,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', 'ADULT')
            ->assertJsonPath('data.name', 'Tiket Dewasa')
            ->assertJsonPath('data.unit_price', 50000)
            ->assertJsonPath('data.tax_amount', 0)
            ->assertJsonPath('data.service_fee_amount', 2500)
            ->assertJsonPath('data.max_per_order', 10)
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.currency', 'IDR');

        $this->assertDatabaseHas('ticket_types', [
            'destination_id' => $destination->id,
            'code' => 'ADULT',
            'unit_price' => 50000,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ticket_type.upsert',
            'actor_type' => 'user',
            'actor_id' => $admin->id,
            'entity_type' => 'ticket_type',
        ]);
    }

    public function test_duplicate_code_per_destination_is_rejected(): void
    {
        $destination = Destination::factory()->create();
        TicketType::factory()->create([
            'destination_id' => $destination->id,
            'code' => 'ADULT',
        ]);

        $admin = $this->userWithRole(RoleName::Admin);
        $token = $admin->createToken('phpunit')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/ticket-types', [
                'destination_id' => $destination->id,
                'code' => 'ADULT',
                'name' => 'Duplicate',
                'unit_price' => 10000,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation.failed');
    }

    public function test_same_code_allowed_on_different_destinations(): void
    {
        $a = Destination::factory()->create();
        $b = Destination::factory()->create();
        TicketType::factory()->create([
            'destination_id' => $a->id,
            'code' => 'ADULT',
        ]);

        $admin = $this->userWithRole(RoleName::Admin);
        $token = $admin->createToken('phpunit')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/ticket-types', [
                'destination_id' => $b->id,
                'code' => 'ADULT',
                'name' => 'Adult B',
                'unit_price' => 40000,
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'ADULT')
            ->assertJsonPath('data.destination_id', $b->id);
    }

    public function test_list_active_ticket_types_for_staff(): void
    {
        $destination = Destination::factory()->create();
        TicketType::factory()->create([
            'destination_id' => $destination->id,
            'code' => 'ACTIVE-1',
            'name' => 'Active',
            'is_active' => true,
            'unit_price' => 30000,
        ]);
        TicketType::factory()->inactive()->create([
            'destination_id' => $destination->id,
            'code' => 'OFF-1',
            'name' => 'Off',
            'unit_price' => 20000,
        ]);

        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $token = $officer->createToken('phpunit')->plainTextToken;

        $all = $this->withToken($token)
            ->getJson("/api/v1/destinations/{$destination->id}/ticket-types");
        $all->assertOk();
        $codes = collect($all->json('data'))->pluck('code')->all();
        $this->assertContains('ACTIVE-1', $codes);
        $this->assertContains('OFF-1', $codes);

        $active = $this->withToken($token)
            ->getJson("/api/v1/destinations/{$destination->id}/ticket-types?active=1");
        $active->assertOk();
        $activeCodes = collect($active->json('data'))->pluck('code')->all();
        $this->assertContains('ACTIVE-1', $activeCodes);
        $this->assertNotContains('OFF-1', $activeCodes);
        $this->assertSame(30000, $active->json('data.0.unit_price'));
    }

    public function test_device_with_catalog_read_lists_only_active_ticket_types(): void
    {
        $destination = Destination::factory()->create();
        TicketType::factory()->create([
            'destination_id' => $destination->id,
            'code' => 'ACTIVE-1',
            'is_active' => true,
            'unit_price' => 45000,
            'tax_amount' => 1000,
            'service_fee_amount' => 500,
        ]);
        TicketType::factory()->inactive()->create([
            'destination_id' => $destination->id,
            'code' => 'OFF-1',
        ]);
        $softDeleted = TicketType::factory()->create([
            'destination_id' => $destination->id,
            'code' => 'GONE-1',
            'is_active' => true,
        ]);
        $softDeleted->delete();

        $device = Device::factory()->create(['is_active' => true]);
        $token = $device->createToken('kiosk', DeviceAbilities::defaultKiosk())->plainTextToken;

        $response = $this->withToken($token)
            ->getJson("/api/v1/destinations/{$destination->id}/ticket-types");

        $response->assertOk();
        $codes = collect($response->json('data'))->pluck('code')->all();
        $this->assertContains('ACTIVE-1', $codes);
        $this->assertNotContains('OFF-1', $codes);
        $this->assertNotContains('GONE-1', $codes);
        $this->assertSame(45000, $response->json('data.0.unit_price'));
        $this->assertSame(1000, $response->json('data.0.tax_amount'));
        $this->assertSame(500, $response->json('data.0.service_fee_amount'));
    }

    public function test_device_without_catalog_read_is_forbidden(): void
    {
        $destination = Destination::factory()->create();
        $device = Device::factory()->create(['is_active' => true]);
        $token = $device->createToken('kiosk', [DeviceAbilities::HEARTBEAT])->plainTextToken;

        $this->withToken($token)
            ->getJson("/api/v1/destinations/{$destination->id}/ticket-types")
            ->assertForbidden()
            ->assertJsonPath('error.code', 'auth.forbidden');
    }

    public function test_gate_officer_cannot_manage_ticket_types(): void
    {
        $destination = Destination::factory()->create();
        $officer = $this->userWithRole(RoleName::GateOfficer);
        $token = $officer->createToken('phpunit')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/ticket-types', [
                'destination_id' => $destination->id,
                'code' => 'NOPE',
                'name' => 'Nope',
                'unit_price' => 1000,
            ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'auth.forbidden');
    }

    public function test_gate_officer_cannot_view_ticket_types(): void
    {
        $destination = Destination::factory()->create();
        TicketType::factory()->create(['destination_id' => $destination->id]);

        $officer = $this->userWithRole(RoleName::GateOfficer);
        $token = $officer->createToken('phpunit')->plainTextToken;

        $this->withToken($token)
            ->getJson("/api/v1/destinations/{$destination->id}/ticket-types")
            ->assertForbidden()
            ->assertJsonPath('error.code', 'auth.forbidden');
    }

    public function test_admin_can_update_price_and_deactivate_ticket_type(): void
    {
        $destination = Destination::factory()->create();
        $ticketType = TicketType::factory()->create([
            'destination_id' => $destination->id,
            'code' => 'ADULT',
            'name' => 'Old Name',
            'unit_price' => 50000,
            'tax_amount' => 0,
            'service_fee_amount' => 0,
            'is_active' => true,
        ]);

        $admin = $this->userWithRole(RoleName::Admin);
        $token = $admin->createToken('phpunit')->plainTextToken;

        $this->withToken($token)
            ->patchJson("/api/v1/ticket-types/{$ticketType->id}", [
                'name' => 'New Name',
                'unit_price' => 75000,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.unit_price', 75000)
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ticket_type.upsert',
            'entity_type' => 'ticket_type',
            'entity_id' => $ticketType->id,
        ]);

        $priceAudit = AuditLog::query()
            ->where('entity_type', 'ticket_type')
            ->where('entity_id', $ticketType->id)
            ->where('action', 'ticket_type.upsert')
            ->latest('id')
            ->first();
        $this->assertTrue((bool) data_get($priceAudit?->meta_json, 'price_changed'));

        $this->withToken($token)
            ->patchJson("/api/v1/ticket-types/{$ticketType->id}", [
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('ticket_types', [
            'id' => $ticketType->id,
            'name' => 'New Name',
            'unit_price' => 75000,
            'is_active' => false,
            'deleted_at' => null,
        ]);
    }

    public function test_negative_prices_and_invalid_max_per_order_are_rejected(): void
    {
        $destination = Destination::factory()->create();
        $admin = $this->userWithRole(RoleName::Admin);
        $token = $admin->createToken('phpunit')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/ticket-types', [
                'destination_id' => $destination->id,
                'code' => 'BAD',
                'name' => 'Bad',
                'unit_price' => -1,
                'max_per_order' => 0,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation.failed');
    }

    public function test_days_from_issue_requires_validity_days(): void
    {
        $destination = Destination::factory()->create();
        $admin = $this->userWithRole(RoleName::Admin);
        $token = $admin->createToken('phpunit')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/ticket-types', [
                'destination_id' => $destination->id,
                'code' => 'MULTI',
                'name' => 'Multi Day',
                'unit_price' => 100000,
                'validity_type' => ValidityType::DaysFromIssue->value,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation.failed');

        $this->withToken($token)
            ->postJson('/api/v1/ticket-types', [
                'destination_id' => $destination->id,
                'code' => 'MULTI',
                'name' => 'Multi Day',
                'unit_price' => 100000,
                'validity_type' => ValidityType::DaysFromIssue->value,
                'validity_days' => 3,
            ])
            ->assertCreated()
            ->assertJsonPath('data.validity_days', 3);
    }

    public function test_missing_destination_returns_not_found_on_list(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $token = $admin->createToken('phpunit')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/destinations/999999/ticket-types')
            ->assertNotFound();
    }

    public function test_unauthenticated_list_is_rejected(): void
    {
        $destination = Destination::factory()->create();

        $this->getJson("/api/v1/destinations/{$destination->id}/ticket-types")
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'auth.unauthenticated');
    }

    public function test_device_cannot_create_ticket_type(): void
    {
        $destination = Destination::factory()->create();
        $device = Device::factory()->create(['is_active' => true]);
        $token = $device->createToken('kiosk', DeviceAbilities::defaultKiosk())->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/ticket-types', [
                'destination_id' => $destination->id,
                'code' => 'KIOSK-TRY',
                'name' => 'Should Fail',
                'unit_price' => 1000,
            ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'auth.forbidden');
    }
}

<?php

namespace Tests\Feature\Catalog;

use App\Enums\RoleName;
use App\Models\Destination;
use App\Models\Device;
use App\Models\TicketType;
use App\Support\DeviceAbilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class DestinationTest extends TestCase
{
    use InteractsWithRbac;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_admin_can_create_destination(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $token = $admin->createToken('phpunit')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/v1/destinations', [
            'code' => 'DESA-01',
            'name' => 'Desa Wisata Sample',
            'timezone' => 'Asia/Jakarta',
            'description' => 'Master destination',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', 'DESA-01')
            ->assertJsonPath('data.name', 'Desa Wisata Sample')
            ->assertJsonPath('data.timezone', 'Asia/Jakarta')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('destinations', [
            'code' => 'DESA-01',
            'name' => 'Desa Wisata Sample',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'destination.upsert',
            'actor_type' => 'user',
            'actor_id' => $admin->id,
            'entity_type' => 'destination',
        ]);
    }

    public function test_duplicate_code_is_rejected(): void
    {
        Destination::factory()->create(['code' => 'DESA-01']);
        $admin = $this->userWithRole(RoleName::Admin);
        $token = $admin->createToken('phpunit')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/destinations', [
                'code' => 'DESA-01',
                'name' => 'Another',
                'timezone' => 'Asia/Jakarta',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation.failed');
    }

    public function test_list_active_destinations_for_staff(): void
    {
        Destination::factory()->create(['code' => 'ACTIVE-1', 'name' => 'Active One', 'is_active' => true]);
        Destination::factory()->inactive()->create(['code' => 'OFF-1', 'name' => 'Off One']);

        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $token = $officer->createToken('phpunit')->plainTextToken;

        $all = $this->withToken($token)->getJson('/api/v1/destinations');
        $all->assertOk();
        $codes = collect($all->json('data'))->pluck('code')->all();
        $this->assertContains('ACTIVE-1', $codes);
        $this->assertContains('OFF-1', $codes);

        $active = $this->withToken($token)->getJson('/api/v1/destinations?active=1');
        $active->assertOk();
        $activeCodes = collect($active->json('data'))->pluck('code')->all();
        $this->assertContains('ACTIVE-1', $activeCodes);
        $this->assertNotContains('OFF-1', $activeCodes);
    }

    public function test_device_with_catalog_read_lists_only_active_destinations(): void
    {
        Destination::factory()->create(['code' => 'ACTIVE-1', 'is_active' => true]);
        Destination::factory()->inactive()->create(['code' => 'OFF-1']);

        $device = Device::factory()->create(['is_active' => true]);
        $token = $device->createToken('kiosk', DeviceAbilities::defaultKiosk())->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/destinations');

        $response->assertOk();
        $codes = collect($response->json('data'))->pluck('code')->all();
        $this->assertContains('ACTIVE-1', $codes);
        $this->assertNotContains('OFF-1', $codes);
    }

    public function test_device_without_catalog_read_is_forbidden(): void
    {
        $device = Device::factory()->create(['is_active' => true]);
        $token = $device->createToken('kiosk', [DeviceAbilities::HEARTBEAT])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/destinations')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'auth.forbidden');
    }

    public function test_gate_officer_cannot_manage_destinations(): void
    {
        $officer = $this->userWithRole(RoleName::GateOfficer);
        $token = $officer->createToken('phpunit')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/destinations', [
                'code' => 'NOPE',
                'name' => 'Nope',
                'timezone' => 'Asia/Jakarta',
            ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'auth.forbidden');
    }

    public function test_admin_can_update_and_deactivate_destination(): void
    {
        $destination = Destination::factory()->create([
            'code' => 'DESA-01',
            'name' => 'Old Name',
            'is_active' => true,
        ]);
        TicketType::factory()->create(['destination_id' => $destination->id]);

        $admin = $this->userWithRole(RoleName::Admin);
        $token = $admin->createToken('phpunit')->plainTextToken;

        $this->withToken($token)
            ->patchJson("/api/v1/destinations/{$destination->id}", [
                'name' => 'New Name',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.is_active', true);

        $this->withToken($token)
            ->patchJson("/api/v1/destinations/{$destination->id}", [
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('destinations', [
            'id' => $destination->id,
            'name' => 'New Name',
            'is_active' => false,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('ticket_types', [
            'destination_id' => $destination->id,
        ]);
    }

    public function test_unauthenticated_list_is_rejected(): void
    {
        $this->getJson('/api/v1/destinations')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'auth.unauthenticated');
    }

    public function test_create_requires_code_name_timezone(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $token = $admin->createToken('phpunit')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/destinations', [])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation.failed');
    }

    public function test_device_cannot_create_destination(): void
    {
        $device = Device::factory()->create(['is_active' => true]);
        $token = $device->createToken('kiosk', DeviceAbilities::defaultKiosk())->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/destinations', [
                'code' => 'KIOSK-TRY',
                'name' => 'Should Fail',
                'timezone' => 'Asia/Jakarta',
            ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'auth.forbidden');
    }
}

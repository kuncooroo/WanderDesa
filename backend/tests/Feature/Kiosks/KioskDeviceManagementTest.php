<?php

namespace Tests\Feature\Kiosks;

use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\Destination;
use App\Models\Device;
use App\Support\DeviceAbilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithOrders;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class KioskDeviceManagementTest extends TestCase
{
    use InteractsWithOrders;
    use InteractsWithRbac;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_admin_can_register_issue_activation_and_kiosk_exchanges_token(): void
    {
        $destination = Destination::factory()->create(['is_active' => true]);
        $admin = $this->userWithRole(RoleName::Admin);
        $staffToken = $admin->createToken('phpunit')->plainTextToken;

        $register = $this->withToken($staffToken)->postJson('/api/v1/kiosks', [
            'device_id' => 'kiosk-gate-01',
            'destination_id' => $destination->id,
            'name' => 'Kiosk Gate 01',
        ]);

        $register->assertCreated()
            ->assertJsonPath('data.device_id', 'kiosk-gate-01')
            ->assertJsonPath('data.status', 'registered')
            ->assertJsonPath('data.is_active', false);

        $deviceId = $register->json('data.id');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'device.registered',
            'entity_type' => 'device',
            'entity_id' => $deviceId,
        ]);

        $issued = $this->withToken($staffToken)
            ->postJson("/api/v1/kiosks/{$deviceId}/activate", ['rotate_secret' => true]);

        $issued->assertOk()
            ->assertJsonPath('data.device.id', $deviceId)
            ->assertJsonStructure(['data' => ['activation_code', 'expires_at']]);

        $code = $issued->json('data.activation_code');
        $this->assertNotEmpty($code);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'device.activated',
            'entity_id' => $deviceId,
        ]);

        $device = Device::query()->findOrFail($deviceId);
        $this->assertNotNull($device->activation_secret_hash);
        $this->assertStringNotContainsString($code, (string) json_encode(
            AuditLog::query()->where('action', 'device.activated')->latest('id')->first()?->meta_json
        ));

        $exchange = $this->postJson('/api/v1/kiosks/activate', [
            'device_id' => 'kiosk-gate-01',
            'activation_code' => $code,
            'software_version' => '1.0.0',
        ]);

        $exchange->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.device.status', 'active')
            ->assertJsonPath('data.device.is_active', true)
            ->assertJsonStructure(['data' => ['token']]);

        $deviceToken = $exchange->json('data.token');
        $this->assertNotEmpty($deviceToken);

        $device->refresh();
        $this->assertNull($device->activation_secret_hash);
        $this->assertNull($device->activation_expires_at);
        $this->assertSame('1.0.0', $device->software_version);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'device.token_issued',
            'entity_id' => $deviceId,
        ]);

        // One-time: replay fails
        $this->postJson('/api/v1/kiosks/activate', [
            'device_id' => 'kiosk-gate-01',
            'activation_code' => $code,
        ])->assertUnauthorized()
            ->assertJsonPath('error.code', 'auth.activation_invalid');

        $heartbeat = $this->withToken($deviceToken)->postJson('/api/v1/kiosks/heartbeat', [
            'software_version' => '1.0.1',
            'hardware_version' => 'hw-2',
            'printer_ok' => true,
        ]);

        $heartbeat->assertOk()
            ->assertJsonPath('data.device_status', 'active')
            ->assertJsonStructure(['data' => ['server_time', 'commands']]);

        $device->refresh();
        $this->assertSame('1.0.1', $device->software_version);
        $this->assertNotNull($device->last_heartbeat_at);
        $this->assertDatabaseCount('device_heartbeats', 1);

        $this->withToken($deviceToken)
            ->getJson('/api/v1/kiosks/me')
            ->assertOk()
            ->assertJsonPath('data.device_id', 'kiosk-gate-01')
            ->assertJsonPath('data.status', 'active');

        $this->withToken($deviceToken)
            ->getJson('/api/v1/kiosks/me/config')
            ->assertOk()
            ->assertJsonPath('data.currency', 'IDR')
            ->assertJsonPath('data.destination.id', $destination->id)
            ->assertJsonPath('data.features.print', true);
    }

    public function test_invalid_activation_code_is_rejected(): void
    {
        $device = Device::factory()->create([
            'device_id' => 'bad-code-device',
            'activation_secret_hash' => bcrypt('ABCD-EFGH'),
            'activation_expires_at' => now()->addHour(),
        ]);

        $this->postJson('/api/v1/kiosks/activate', [
            'device_id' => $device->device_id,
            'activation_code' => 'WRONG-CODE',
        ])->assertUnauthorized()
            ->assertJsonPath('error.code', 'auth.activation_invalid');
    }

    public function test_heartbeat_without_ability_is_forbidden(): void
    {
        [$device, $token] = $this->activeDeviceToken(null, [DeviceAbilities::CATALOG_READ]);

        $this->withToken($token)
            ->postJson('/api/v1/kiosks/heartbeat', [
                'software_version' => '1.0.0',
            ])
            ->assertForbidden();
    }

    public function test_maintenance_blocks_orders_and_sends_command_on_heartbeat(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        $admin = $this->userWithRole(RoleName::Admin);
        $staffToken = $admin->createToken('phpunit')->plainTextToken;

        $device = Device::factory()->active()->create([
            'destination_id' => $destination->id,
        ]);
        $deviceToken = $device->createToken('kiosk', DeviceAbilities::defaultKiosk())->plainTextToken;

        $this->withToken($staffToken)
            ->postJson("/api/v1/kiosks/{$device->id}/maintenance", ['enabled' => true])
            ->assertOk()
            ->assertJsonPath('data.status', 'maintenance')
            ->assertJsonPath('data.maintenance_mode', true);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'device.maintenance_on',
            'entity_id' => $device->id,
        ]);

        $this->withIdempotency($deviceToken, 'maint-block-order')
            ->postJson('/api/v1/orders', $this->twoAdultPayload($destination, $adult))
            ->assertForbidden()
            ->assertJsonPath('error.code', 'device.maintenance');

        $this->withToken($deviceToken)
            ->postJson('/api/v1/kiosks/heartbeat', ['software_version' => '2.0.0'])
            ->assertOk()
            ->assertJsonPath('data.device_status', 'maintenance')
            ->assertJsonPath('data.commands.0', 'enter_maintenance');
    }

    public function test_deactivate_revokes_tokens_and_blocks_commerce(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        $admin = $this->userWithRole(RoleName::Admin);
        $staffToken = $admin->createToken('phpunit')->plainTextToken;

        $device = Device::factory()->active()->create([
            'destination_id' => $destination->id,
        ]);
        $deviceToken = $device->createToken('kiosk', DeviceAbilities::defaultKiosk())->plainTextToken;
        $tokenId = $device->tokens()->firstOrFail()->id;

        $this->withToken($staffToken)
            ->postJson("/api/v1/kiosks/{$device->id}/deactivate")
            ->assertOk()
            ->assertJsonPath('data.status', 'disabled')
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'device.disabled',
            'entity_id' => $device->id,
        ]);

        $this->withIdempotency($deviceToken, 'disabled-order')
            ->postJson('/api/v1/orders', $this->twoAdultPayload($destination, $adult))
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'auth.unauthenticated');

        $this->withToken($deviceToken)
            ->postJson('/api/v1/kiosks/heartbeat', ['software_version' => '1.0.0'])
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'auth.unauthenticated');

        // Even if a token were re-minted out of band, middleware blocks inactive devices.
        $staleToken = $device->createToken('kiosk', DeviceAbilities::defaultKiosk())->plainTextToken;
        $this->withToken($staleToken)
            ->postJson('/api/v1/kiosks/heartbeat', ['software_version' => '1.0.0'])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'device.inactive');
    }

    public function test_fleet_health_reports_online_from_heartbeat(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $staffToken = $admin->createToken('phpunit')->plainTextToken;

        $online = Device::factory()->active()->create([
            'device_id' => 'online-1',
            'last_heartbeat_at' => now()->subSeconds(30),
        ]);
        Device::factory()->active()->create([
            'device_id' => 'stale-1',
            'last_heartbeat_at' => now()->subMinutes(10),
        ]);

        $response = $this->withToken($staffToken)->getJson('/api/v1/kiosks/health');

        $response->assertOk();
        $rows = collect($response->json('data'));
        $this->assertTrue($rows->firstWhere('id', $online->id)['online']);
        $this->assertFalse($rows->firstWhere('device_id', 'stale-1')['online']);
    }

    public function test_ticket_officer_cannot_manage_kiosks(): void
    {
        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $token = $officer->createToken('phpunit')->plainTextToken;
        $destination = Destination::factory()->create(['is_active' => true]);

        $this->withToken($token)
            ->postJson('/api/v1/kiosks', [
                'device_id' => 'nope',
                'destination_id' => $destination->id,
                'name' => 'Nope',
            ])
            ->assertForbidden();
    }

    public function test_dashboard_kiosk_fleet_is_visible_to_admin(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        Device::factory()->active()->create([
            'name' => 'Lobby Kiosk',
            'device_id' => 'lobby-01',
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard.kiosks'))
            ->assertOk()
            ->assertSee('Kiosk fleet')
            ->assertSee('Lobby Kiosk')
            ->assertSee('lobby-01');
    }

    public function test_dashboard_kiosk_fleet_is_forbidden_for_ticket_officer(): void
    {
        $officer = $this->userWithRole(RoleName::TicketOfficer);

        $this->actingAs($officer)
            ->get(route('dashboard.kiosks'))
            ->assertForbidden();
    }
}

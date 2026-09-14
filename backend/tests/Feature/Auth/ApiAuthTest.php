<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\Device;
use App\Models\Role;
use App\Models\User;
use App\Support\DeviceAbilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class ApiAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_login_returns_bearer_token_for_active_user(): void
    {
        $role = Role::factory()->create(['name' => 'ticket_officer']);
        $user = User::factory()->create([
            'email' => 'staff@example.com',
            'password' => 'secret-password',
        ]);
        $user->roles()->attach($role->id, ['created_at' => now()]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'staff@example.com',
            'password' => 'secret-password',
            'device_name' => 'phpunit',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', 'staff@example.com')
            ->assertJsonPath('data.user.roles.0', 'ticket_officer');

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.login_success',
            'actor_type' => 'user',
            'actor_id' => $user->id,
        ]);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'name' => 'phpunit',
        ]);
    }

    public function test_api_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'staff@example.com',
            'password' => 'secret-password',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'staff@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'auth.invalid_credentials');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.login_failed',
        ]);
    }

    public function test_api_login_rejects_inactive_user(): void
    {
        User::factory()->inactive()->create([
            'email' => 'inactive@example.com',
            'password' => 'secret-password',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'inactive@example.com',
            'password' => 'secret-password',
        ]);

        $response->assertForbidden()
            ->assertJsonPath('error.code', 'auth.user_inactive');

        $this->assertSame(0, PersonalAccessToken::query()->count());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.login_failed',
        ]);
    }

    public function test_api_login_validates_required_fields(): void
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation.failed');
    }

    public function test_api_me_returns_authenticated_user(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('phpunit')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.principal_type', 'user')
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_api_me_returns_device_principal(): void
    {
        $device = Device::factory()->create([
            'is_active' => true,
        ]);
        $token = $device->createToken('kiosk', DeviceAbilities::defaultKiosk())->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.principal_type', 'device')
            ->assertJsonPath('data.device_id', $device->device_id)
            ->assertJsonPath('data.abilities.0', DeviceAbilities::CATALOG_READ);
    }

    public function test_api_logout_revokes_current_token(): void
    {
        $user = User::factory()->create();
        $plainText = $user->createToken('phpunit')->plainTextToken;

        $this->withToken($plainText)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('data.revoked', true);

        $this->assertSame(0, $user->tokens()->count());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.logout',
            'actor_id' => $user->id,
        ]);

        $this->withToken($plainText)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'auth.unauthenticated');
    }

    public function test_api_auth_endpoints_are_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'nobody@example.com',
                'password' => 'x',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'x',
        ])->assertStatus(429)
            ->assertJsonPath('error.code', 'rate_limit.exceeded');
    }

    public function test_passwords_are_never_written_to_audit_meta(): void
    {
        User::factory()->create([
            'email' => 'staff@example.com',
            'password' => 'secret-password',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'staff@example.com',
            'password' => 'wrong-password',
        ])->assertUnauthorized();

        $log = AuditLog::query()->where('action', 'auth.login_failed')->first();
        $this->assertNotNull($log);
        $encoded = json_encode($log->meta_json);
        $this->assertStringNotContainsString('wrong-password', (string) $encoded);
        $this->assertStringNotContainsString('secret-password', (string) $encoded);
    }
}

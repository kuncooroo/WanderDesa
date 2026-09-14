<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_reachable(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_staff_can_login_and_logout_via_web(): void
    {
        $user = User::factory()->create([
            'email' => 'staff@example.com',
            'password' => 'secret-password',
        ]);

        $this->get('/login')->assertOk();

        $this->post('/login', [
            'email' => 'staff@example.com',
            'password' => 'secret-password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);

        $this->get('/dashboard')->assertOk()->assertSee($user->name);

        $this->post('/logout')->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_web_login_rejects_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'staff@example.com',
            'password' => 'secret-password',
        ]);

        $this->from('/login')->post('/login', [
            'email' => 'staff@example.com',
            'password' => 'wrong',
        ])->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.login_failed',
        ]);
    }

    public function test_web_login_rejects_inactive_user(): void
    {
        User::factory()->inactive()->create([
            'email' => 'inactive@example.com',
            'password' => 'secret-password',
        ]);

        $this->from('/login')->post('/login', [
            'email' => 'inactive@example.com',
            'password' => 'secret-password',
        ])->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_inactive_user_session_is_blocked_from_dashboard(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
        ]);

        $this->actingAs($user);
        $user->forceFill(['is_active' => false])->save();

        $this->get('/dashboard')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_guest_root_redirects_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }
}

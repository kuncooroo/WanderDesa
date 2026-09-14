<?php

namespace Tests\Feature\Settings;

use App\Enums\RoleName;
use App\Livewire\Settings\SettingsBoard;
use App\Models\Setting;
use App\Support\Authorization\NavPermissionMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class SettingsBoardTest extends TestCase
{
    use InteractsWithRbac;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_admin_can_view_settings_but_not_save(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin)
            ->get(route('dashboard.settings'))
            ->assertOk()
            ->assertSee('Pengaturan')
            ->assertSee('settings.update')
            ->assertDontSee('Simpan pengaturan')
            ->assertDontSee('belum diluncurkan');

        Livewire::actingAs($admin)
            ->test(SettingsBoard::class)
            ->set('orderPaymentTtlMinutes', 30)
            ->set('confirmDanger', true)
            ->call('save')
            ->assertForbidden();
    }

    public function test_super_admin_can_save_settings(): void
    {
        $super = $this->userWithRole(RoleName::SuperAdmin);

        Livewire::actingAs($super)
            ->test(SettingsBoard::class)
            ->set('orderPaymentTtlMinutes', 25)
            ->set('kioskHeartbeatStaleSeconds', 180)
            ->set('kioskActivationTtlMinutes', 90)
            ->set('confirmDanger', true)
            ->call('save')
            ->assertSee('Pengaturan disimpan.');

        $this->assertSame(25, Setting::orderPaymentTtlMinutes());
        $this->assertSame(180, Setting::heartbeatStaleSeconds());
        $this->assertSame(90, Setting::activationTtlMinutes());

        $this->assertDatabaseHas('settings', [
            'key' => Setting::ORDER_PAYMENT_TTL_MINUTES,
            'value' => '25',
            'updated_by_user_id' => $super->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'settings.updated',
            'actor_id' => $super->id,
            'entity_type' => 'settings',
        ]);
    }

    public function test_super_admin_must_confirm_before_save(): void
    {
        $super = $this->userWithRole(RoleName::SuperAdmin);

        Livewire::actingAs($super)
            ->test(SettingsBoard::class)
            ->set('orderPaymentTtlMinutes', 40)
            ->set('confirmDanger', false)
            ->call('save')
            ->assertHasErrors(['confirmDanger']);

        $this->assertSame(15, Setting::orderPaymentTtlMinutes());
    }

    public function test_ticket_officer_cannot_open_settings(): void
    {
        $officer = $this->userWithRole(RoleName::TicketOfficer);

        $this->actingAs($officer)
            ->get(route('dashboard.settings'))
            ->assertForbidden();
    }

    public function test_operator_does_not_see_settings_nav_via_maintenance_alone(): void
    {
        $operator = $this->userWithRole(RoleName::Operator);

        $this->assertFalse(NavPermissionMap::canSee($operator, 'settings'));

        $this->actingAs($operator)
            ->get(route('dashboard.settings'))
            ->assertForbidden();
    }
}

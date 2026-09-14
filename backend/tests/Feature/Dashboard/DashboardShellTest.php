<?php

namespace Tests\Feature\Dashboard;

use App\Enums\RoleName;
use App\Models\User;
use App\Support\Navigation\NavBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class DashboardShellTest extends TestCase
{
    use InteractsWithRbac;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_ticket_officer_sees_assisted_sale_not_audit_or_checkin(): void
    {
        $officer = $this->userWithRole(RoleName::TicketOfficer);

        $keys = app(NavBuilder::class)->visibleKeys($officer);

        $this->assertContains('home', $keys);
        $this->assertContains('assisted_sale', $keys);
        $this->assertContains('orders', $keys);
        $this->assertContains('payments', $keys);
        $this->assertContains('tickets', $keys);
        $this->assertNotContains('checkin', $keys);
        $this->assertNotContains('audit_logs', $keys);
        $this->assertNotContains('reports', $keys);

        $this->actingAs($officer)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Notifikasi')
            ->assertSee('Mulai penjualan')
            ->assertSee('Penjualan dibantu')
            ->assertDontSee('Check-in gerbang')
            ->assertDontSee('Audit logs');
    }

    public function test_gate_officer_sees_checkin_not_assisted_sale(): void
    {
        $gate = $this->userWithRole(RoleName::GateOfficer);
        $keys = app(NavBuilder::class)->visibleKeys($gate);

        $this->assertContains('checkin', $keys);
        $this->assertContains('tickets', $keys);
        $this->assertNotContains('assisted_sale', $keys);
        $this->assertNotContains('payments', $keys);

        $this->actingAs($gate)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Check-in gerbang')
            ->assertDontSee('Mulai penjualan');
    }

    public function test_auditor_sees_audit_and_reports_read_only_nav(): void
    {
        $auditor = $this->userWithRole(RoleName::Auditor);
        $keys = app(NavBuilder::class)->visibleKeys($auditor);

        $this->assertContains('audit_logs', $keys);
        $this->assertContains('reports', $keys);
        $this->assertContains('orders', $keys);
        $this->assertContains('payments', $keys);
        $this->assertNotContains('assisted_sale', $keys);
        $this->assertNotContains('settings', $keys);

        $this->actingAs($auditor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Audit logs')
            ->assertDontSee('Mulai penjualan');
    }

    public function test_finance_sees_payments_and_audit_not_assisted_sale(): void
    {
        $finance = $this->userWithRole(RoleName::Finance);
        $keys = app(NavBuilder::class)->visibleKeys($finance);

        $this->assertContains('payments', $keys);
        $this->assertContains('audit_logs', $keys);
        $this->assertContains('reports', $keys);
        $this->assertNotContains('assisted_sale', $keys);
        $this->assertNotContains('checkin', $keys);
    }

    public function test_unauthorized_stub_and_livewire_routes_are_denied(): void
    {
        $officer = $this->userWithRole(RoleName::TicketOfficer);

        $this->actingAs($officer)
            ->get(route('dashboard.check-in'))
            ->assertForbidden()
            ->assertSee('Tidak berwenang');

        $this->actingAs($officer)
            ->get(route('dashboard.audit-logs'))
            ->assertForbidden();

        $this->actingAs($officer)
            ->get(route('dashboard.settings'))
            ->assertForbidden();

        $gate = $this->userWithRole(RoleName::GateOfficer);
        $this->actingAs($gate)
            ->get(route('dashboard.assisted-sale'))
            ->assertForbidden();
    }

    public function test_permitted_orders_module_is_live(): void
    {
        $officer = $this->userWithRole(RoleName::TicketOfficer);

        $this->actingAs($officer)
            ->get(route('dashboard.orders'))
            ->assertOk()
            ->assertSee('Pesanan')
            ->assertDontSee('belum diluncurkan');
    }

    public function test_permitted_catalog_module_is_live(): void
    {
        $officer = $this->userWithRole(RoleName::TicketOfficer);

        $this->actingAs($officer)
            ->get(route('dashboard.catalog'))
            ->assertOk()
            ->assertSee('Destinasi & tiket')
            ->assertDontSee('belum diluncurkan');
    }

    public function test_permitted_payments_module_is_live(): void
    {
        $officer = $this->userWithRole(RoleName::TicketOfficer);

        $this->actingAs($officer)
            ->get(route('dashboard.payments'))
            ->assertOk()
            ->assertSee('Pembayaran')
            ->assertDontSee('belum diluncurkan');
    }

    public function test_permitted_users_module_is_live(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin)
            ->get(route('dashboard.users'))
            ->assertOk()
            ->assertSee('Pengguna')
            ->assertDontSee('belum diluncurkan');
    }

    public function test_permitted_roles_module_is_live(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin)
            ->get(route('dashboard.roles'))
            ->assertOk()
            ->assertSee('Peran')
            ->assertDontSee('belum diluncurkan');
    }

    public function test_permitted_settings_module_is_live(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin)
            ->get(route('dashboard.settings'))
            ->assertOk()
            ->assertSee('Pengaturan')
            ->assertDontSee('belum diluncurkan');
    }

    public function test_user_without_roles_sees_empty_module_message(): void
    {
        $user = User::factory()->create();

        $keys = app(NavBuilder::class)->visibleKeys($user);
        $this->assertSame(['home'], $keys);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Tidak ada modul operasional yang diizinkan');
    }

    public function test_login_uses_guest_layout_copy(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Masuk dashboard staf')
            ->assertDontSee('data-shell');
    }

    public function test_profile_page_is_available_to_authenticated_staff(): void
    {
        $officer = $this->userWithRole(RoleName::TicketOfficer);

        $this->actingAs($officer)
            ->get(route('dashboard.profile'))
            ->assertOk()
            ->assertSee('Profil')
            ->assertSee($officer->email);
    }

    public function test_shell_exposes_nav_item_labels_without_group_headers(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Beranda')
            ->assertSee('Penjualan dibantu')
            ->assertSee('Pesanan')
            ->assertSee('Destinasi & tiket')
            ->assertSee('Kiosk')
            ->assertSee('Pembayaran')
            ->assertSee('Laporan')
            ->assertSee('Pengaturan')
            ->assertDontSee('Operasional')
            ->assertDontSee('Katalog')
            ->assertDontSee('Perangkat')
            ->assertDontSee('Keuangan')
            ->assertDontSee('Administrasi')
            ->assertDontSee('Sistem');
    }
}

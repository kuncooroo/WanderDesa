<?php

namespace Tests\Feature\Roles;

use App\Enums\RoleName;
use App\Livewire\Roles\RoleBoard;
use App\Models\Role;
use App\Support\Authorization\NavPermissionMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class RoleBoardTest extends TestCase
{
    use InteractsWithRbac;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_admin_can_open_roles_page_read_only(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->assertTrue(NavPermissionMap::canSee($admin, 'roles'));

        $this->actingAs($admin)
            ->get(route('dashboard.roles'))
            ->assertOk()
            ->assertSee('Peran')
            ->assertSee('Ticket Officer')
            ->assertSee('Matriks izin')
            ->assertDontSee('belum diluncurkan')
            ->assertDontSee('Simpan izin');
    }

    public function test_super_admin_can_open_matrix_and_role_detail(): void
    {
        $super = $this->userWithRole(RoleName::SuperAdmin);

        Livewire::actingAs($super)
            ->test(RoleBoard::class)
            ->assertSee('Daftar peran')
            ->call('showTab', 'matrix')
            ->assertSet('tab', 'matrix')
            ->assertSee('roles.assign')
            ->assertSee('payments.refund')
            ->call('showTab', 'roles')
            ->call('selectRole', Role::query()->where('name', RoleName::Finance->value)->firstOrFail()->id)
            ->assertSee('Finance')
            ->assertSee('payments.refund');
    }

    public function test_ticket_officer_cannot_open_roles_page(): void
    {
        $officer = $this->userWithRole(RoleName::TicketOfficer);

        $this->assertFalse(NavPermissionMap::canSee($officer, 'roles'));

        $this->actingAs($officer)
            ->get(route('dashboard.roles'))
            ->assertForbidden();
    }

    public function test_auditor_can_view_roles_catalog(): void
    {
        $auditor = $this->userWithRole(RoleName::Auditor);

        $this->actingAs($auditor)
            ->get(route('dashboard.roles'))
            ->assertOk()
            ->assertSee('super_admin')
            ->assertDontSee('Tambah peran');
    }
}

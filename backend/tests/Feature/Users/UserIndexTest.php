<?php

namespace Tests\Feature\Users;

use App\Enums\RoleName;
use App\Livewire\Users\UserIndex;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class UserIndexTest extends TestCase
{
    use InteractsWithRbac;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_admin_can_open_users_page(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin)
            ->get(route('dashboard.users'))
            ->assertOk()
            ->assertSee('Pengguna')
            ->assertDontSee('belum diluncurkan');
    }

    public function test_ticket_officer_cannot_open_users_page(): void
    {
        $officer = $this->userWithRole(RoleName::TicketOfficer);

        $this->actingAs($officer)
            ->get(route('dashboard.users'))
            ->assertForbidden();
    }

    public function test_admin_can_create_user_without_assigning_role(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        Livewire::actingAs($admin)
            ->test(UserIndex::class)
            ->assertSee('Tambah pengguna')
            ->assertDontSeeHtml('wire:model="primaryRole"')
            ->set('name', 'Petugas Baru')
            ->set('email', 'petugas.baru@example.test')
            ->set('password', 'Password1!')
            ->set('password_confirmation', 'Password1!')
            ->set('isActive', true)
            ->call('saveUser')
            ->assertSee('Pengguna dibuat.');

        $created = User::query()->where('email', 'petugas.baru@example.test')->first();
        $this->assertNotNull($created);
        $this->assertTrue($created->is_active);
        $this->assertCount(0, $created->roles);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.upsert',
            'entity_type' => 'user',
            'entity_id' => $created->id,
            'actor_id' => $admin->id,
        ]);
    }

    public function test_super_admin_can_create_user_with_primary_role(): void
    {
        $super = $this->userWithRole(RoleName::SuperAdmin);

        Livewire::actingAs($super)
            ->test(UserIndex::class)
            ->set('name', 'Officer Role')
            ->set('email', 'officer.role@example.test')
            ->set('password', 'Password1!')
            ->set('password_confirmation', 'Password1!')
            ->set('primaryRole', RoleName::TicketOfficer->value)
            ->set('isActive', true)
            ->call('saveUser')
            ->assertSee('Pengguna dibuat.');

        $created = User::query()->where('email', 'officer.role@example.test')->firstOrFail();
        $this->assertTrue($created->hasRole(RoleName::TicketOfficer));
    }

    public function test_admin_can_deactivate_and_soft_delete_user(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $target = User::factory()->create([
            'name' => 'Target User',
            'email' => 'target.user@example.test',
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(UserIndex::class)
            ->call('deactivateUser', $target->id)
            ->assertSee('Pengguna dinonaktifkan.');

        $this->assertFalse((bool) $target->fresh()->is_active);

        Livewire::actingAs($admin)
            ->test(UserIndex::class)
            ->call('activateUser', $target->id)
            ->assertSee('Pengguna diaktifkan.');

        $this->assertTrue((bool) $target->fresh()->is_active);

        Livewire::actingAs($admin)
            ->test(UserIndex::class)
            ->call('deleteUser', $target->id)
            ->assertSee('Pengguna dihapus.');

        $this->assertSoftDeleted('users', ['id' => $target->id]);
    }

    public function test_admin_cannot_delete_own_account(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        Livewire::actingAs($admin)
            ->test(UserIndex::class)
            ->call('deleteUser', $admin->id)
            ->assertSee('You cannot delete your own account.');

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'deleted_at' => null,
        ]);
    }

    public function test_auditor_can_view_but_not_create(): void
    {
        $auditor = $this->userWithRole(RoleName::Auditor);
        User::factory()->create(['name' => 'Visible Staff', 'email' => 'visible@example.test']);

        $this->actingAs($auditor)
            ->get(route('dashboard.users'))
            ->assertOk()
            ->assertSee('Visible Staff')
            ->assertDontSee('Tambah pengguna');

        Livewire::actingAs($auditor)
            ->test(UserIndex::class)
            ->call('startCreate')
            ->assertForbidden();
    }
}

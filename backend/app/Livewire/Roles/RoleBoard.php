<?php

namespace App\Livewire\Roles;

use App\Enums\PermissionName;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Authorization\Authorizer;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Role catalog + permission matrix (docs/09 B18–B19). Read-only in MVP.
 */
#[Layout('layouts.app')]
#[Title('Peran')]
class RoleBoard extends Component
{
    use AuthorizesRequests;

    #[Url(as: 'tab')]
    public string $tab = 'roles';

    #[Url(as: 'role')]
    public string $selectedRoleId = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Role::class);

        if (! in_array($this->tab, ['roles', 'matrix'], true)) {
            $this->tab = 'roles';
        }
    }

    public function showTab(string $tab): void
    {
        if (! in_array($tab, ['roles', 'matrix'], true)) {
            return;
        }

        $this->tab = $tab;
    }

    public function selectRole(int $id): void
    {
        $role = Role::query()->findOrFail($id);
        $this->authorize('view', $role);
        $this->selectedRoleId = (string) $id;
        $this->tab = 'roles';
    }

    public function clearSelection(): void
    {
        $this->selectedRoleId = '';
    }

    public function render()
    {
        $roles = Role::query()
            ->withCount(['permissions', 'users'])
            ->with(['permissions' => fn ($q) => $q->orderBy('name')])
            ->orderBy('name')
            ->get();

        $selected = null;
        if ($this->selectedRoleId !== '') {
            $selected = $roles->firstWhere('id', (int) $this->selectedRoleId);
        }

        $permissions = Permission::query()->orderBy('name')->get(['id', 'name', 'display_name']);

        $grants = [];
        foreach ($roles as $role) {
            $grants[$role->id] = $role->permissions->pluck('name')->all();
        }

        return view('livewire.roles.role-board', [
            'roles' => $roles,
            'selected' => $selected,
            'permissions' => $permissions,
            'grants' => $grants,
            'canAssignRoles' => Authorizer::check($this->staff(), PermissionName::RolesAssign),
        ]);
    }

    private function staff(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}

<?php

namespace App\Livewire\Users;

use App\Actions\Users\CreateUser;
use App\Actions\Users\SoftDeleteUser;
use App\Actions\Users\UpdateUser;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Exceptions\DomainException;
use App\Models\Role;
use App\Models\User;
use App\Support\Authorization\Authorizer;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Staff user admin (docs/09 B17). Role assign requires roles.assign.
 */
#[Layout('layouts.app')]
#[Title('Pengguna')]
class UserIndex extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'active')]
    public string $activeFilter = '';

    public ?int $editingId = null;

    public bool $showForm = false;

    public ?int $detailId = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $isActive = true;

    public string $primaryRole = '';

    public string $flashMessage = '';

    public string $errorMessage = '';

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingActiveFilter(): void
    {
        $this->resetPage();
    }

    public function startCreate(): void
    {
        $this->authorize('create', User::class);
        $this->resetForm();
        $this->editingId = null;
        $this->detailId = null;
        $this->showForm = true;
        $this->clearMessages();
    }

    public function showDetail(int $id): void
    {
        $user = User::query()->findOrFail($id);
        $this->authorize('view', $user);
        $this->detailId = $user->id;
        $this->showForm = false;
        $this->clearMessages();
    }

    public function closeDetail(): void
    {
        $this->detailId = null;
    }

    public function editUser(int $id): void
    {
        $user = User::query()->with('roles')->findOrFail($id);
        $this->authorize('view', $user);

        $this->editingId = $user->id;
        $this->detailId = null;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->password_confirmation = '';
        $this->isActive = (bool) $user->is_active;
        $this->primaryRole = $user->roles->first()?->name ?? '';
        $this->showForm = true;
        $this->clearMessages();
    }

    public function cancelForm(): void
    {
        $this->resetForm();
        $this->showForm = false;
        $this->clearMessages();
    }

    public function saveUser(CreateUser $create, UpdateUser $update): void
    {
        $canAssignRoles = Authorizer::check($this->staff(), PermissionName::RolesAssign);

        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->editingId),
            ],
            'isActive' => ['boolean'],
            'primaryRole' => [
                'nullable',
                'string',
                Rule::in(array_map(fn (RoleName $r) => $r->value, RoleName::cases())),
            ],
        ];

        if ($this->editingId === null) {
            $rules['password'] = ['required', 'string', Password::defaults(), 'confirmed'];
        } elseif ($this->password !== '') {
            $rules['password'] = ['string', Password::defaults(), 'confirmed'];
        }

        $validated = $this->validate($rules);

        try {
            if ($this->editingId === null) {
                $this->authorize('create', User::class);

                $payload = [
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => $this->password,
                    'is_active' => $validated['isActive'],
                ];

                if ($canAssignRoles && $this->primaryRole !== '') {
                    $payload['primary_role'] = $this->primaryRole;
                }

                $create->handle($this->staff(), $payload, request());
                $this->flashMessage = 'Pengguna dibuat.';
            } else {
                $user = User::query()->findOrFail($this->editingId);
                $this->authorize('update', $user);

                $payload = [
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'is_active' => $validated['isActive'],
                    'sync_primary_role' => $canAssignRoles,
                ];

                if ($this->password !== '') {
                    $payload['password'] = $this->password;
                }

                if ($canAssignRoles) {
                    $payload['primary_role'] = $this->primaryRole;
                }

                $update->handle($this->staff(), $user, $payload, request());
                $this->flashMessage = 'Pengguna diperbarui.';
            }
        } catch (DomainException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->errorMessage = '';
        $this->resetForm();
        $this->showForm = false;
        $this->resetPage();
    }

    public function deactivateUser(int $id, UpdateUser $update): void
    {
        $user = User::query()->findOrFail($id);
        $this->authorize('update', $user);

        try {
            $update->handle($this->staff(), $user, [
                'is_active' => false,
                'sync_primary_role' => false,
            ], request());
        } catch (DomainException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->flashMessage = 'Pengguna dinonaktifkan.';
        $this->errorMessage = '';

        if ($this->editingId === $id) {
            $this->resetForm();
            $this->showForm = false;
        }
    }

    public function activateUser(int $id, UpdateUser $update): void
    {
        $user = User::query()->findOrFail($id);
        $this->authorize('update', $user);

        try {
            $update->handle($this->staff(), $user, [
                'is_active' => true,
                'sync_primary_role' => false,
            ], request());
        } catch (DomainException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->flashMessage = 'Pengguna diaktifkan.';
        $this->errorMessage = '';
    }

    public function deleteUser(int $id, SoftDeleteUser $delete): void
    {
        $user = User::query()->findOrFail($id);
        $this->authorize('delete', $user);

        try {
            $delete->handle($this->staff(), $user, request());
        } catch (DomainException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->flashMessage = 'Pengguna dihapus.';
        $this->errorMessage = '';

        if ($this->editingId === $id) {
            $this->resetForm();
            $this->showForm = false;
        }

        $this->resetPage();
    }

    public function render()
    {
        $query = User::query()->with('roles')->orderBy('name');

        $search = trim($this->search);
        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        if ($this->activeFilter === '1') {
            $query->where('is_active', true);
        } elseif ($this->activeFilter === '0') {
            $query->where('is_active', false);
        }

        return view('livewire.users.user-index', [
            'users' => $query->paginate(20),
            'roles' => Role::query()->orderBy('name')->get(['id', 'name', 'display_name']),
            'detailUser' => $this->detailId
                ? User::query()->with('roles')->find($this->detailId)
                : null,
            'canCreate' => Authorizer::check($this->staff(), PermissionName::UsersCreate),
            'canUpdate' => Authorizer::check($this->staff(), PermissionName::UsersUpdate),
            'canDelete' => Authorizer::check($this->staff(), PermissionName::UsersDelete),
            'canAssignRoles' => Authorizer::check($this->staff(), PermissionName::RolesAssign),
        ]);
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->email = '';
        $this->password = '';
        $this->password_confirmation = '';
        $this->isActive = true;
        $this->primaryRole = '';
        $this->resetValidation();
    }

    private function clearMessages(): void
    {
        $this->flashMessage = '';
        $this->errorMessage = '';
    }

    private function staff(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}

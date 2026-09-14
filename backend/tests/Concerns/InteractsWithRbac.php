<?php

namespace Tests\Concerns;

use App\Enums\RoleName;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

trait InteractsWithRbac
{
    protected function seedRbac(): void
    {
        $this->seed(RolePermissionSeeder::class);
    }

    protected function userWithRole(RoleName|string $role, array $attributes = []): User
    {
        $roleName = $role instanceof RoleName ? $role->value : $role;
        $roleModel = Role::query()->where('name', $roleName)->firstOrFail();

        $user = User::factory()->create($attributes);
        $user->roles()->attach($roleModel->id, ['created_at' => now()]);

        return $user->fresh(['roles.permissions']);
    }
}

<?php

namespace Database\Seeders;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Permission;
use App\Models\Role;
use App\Support\Authorization\RolePermissionMatrix;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds MVP roles, permissions, and permission_role matrix (docs/07-RBAC.md).
 * Idempotent: safe to re-run.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $permissionIds = [];

            foreach (PermissionName::cases() as $permission) {
                $model = Permission::query()->updateOrCreate(
                    ['name' => $permission->value],
                    ['display_name' => $permission->displayName()],
                );
                $permissionIds[$permission->value] = $model->id;
            }

            foreach (RoleName::cases() as $roleName) {
                $role = Role::query()->updateOrCreate(
                    ['name' => $roleName->value],
                    [
                        'display_name' => $roleName->displayName(),
                        'description' => $roleName->description(),
                    ],
                );

                $grantNames = RolePermissionMatrix::permissionsFor($roleName);
                $grantIds = [];

                foreach ($grantNames as $grantName) {
                    $grantIds[] = $permissionIds[$grantName];
                }

                $sync = [];
                foreach ($grantIds as $id) {
                    $sync[$id] = ['created_at' => now()];
                }

                $role->permissions()->sync($sync);
            }

            // Ensure STAFF is never present (docs/07 decision).
            Role::query()->where('name', 'staff')->delete();
        });
    }
}

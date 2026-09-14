<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Optionally creates the first Super Admin from env (no default password).
 *
 * Required env (all must be set or this seeder no-ops):
 *   SUPER_ADMIN_NAME
 *   SUPER_ADMIN_EMAIL
 *   SUPER_ADMIN_PASSWORD
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $name = env('SUPER_ADMIN_NAME');
        $email = env('SUPER_ADMIN_EMAIL');
        $password = env('SUPER_ADMIN_PASSWORD');

        if (! is_string($name) || $name === ''
            || ! is_string($email) || $email === ''
            || ! is_string($password) || $password === '') {
            $this->command?->warn(
                'SuperAdminSeeder skipped: set SUPER_ADMIN_NAME, SUPER_ADMIN_EMAIL, SUPER_ADMIN_PASSWORD.',
            );

            return;
        }

        $role = Role::query()->where('name', RoleName::SuperAdmin->value)->firstOrFail();

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'is_active' => true,
            ],
        );

        if (! $user->roles()->where('roles.id', $role->id)->exists()) {
            $user->roles()->attach($role->id, ['created_at' => now()]);
        }
    }
}

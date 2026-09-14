<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Optional least-privilege pilot accounts (one primary role each).
 * Super Admin is never created here — SuperAdminSeeder only.
 */
class PilotStaffSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = config('ops.pilot_staff', []);
        if (! is_array($accounts)) {
            return;
        }

        foreach ($accounts as $key => $account) {
            if (! is_array($account)) {
                continue;
            }

            $this->seedAccount(is_string($key) ? $key : 'staff', $account);
        }
    }

    /**
     * @param  array<string, mixed>  $account
     */
    private function seedAccount(string $key, array $account): void
    {
        $name = $account['name'] ?? null;
        $email = $account['email'] ?? null;
        $password = $account['password'] ?? null;
        $roleName = $account['role'] ?? null;

        if (! is_string($name) || $name === ''
            || ! is_string($email) || $email === ''
            || ! is_string($password) || $password === ''
            || ! is_string($roleName) || $roleName === '') {
            $this->command?->warn("PilotStaffSeeder skipped [{$key}]: set name, email, and password env vars.");

            return;
        }

        $roleEnum = RoleName::tryFrom($roleName);
        if ($roleEnum === null || $roleEnum === RoleName::SuperAdmin) {
            $this->command?->error("PilotStaffSeeder refused [{$key}]: role must be an MVP named role other than super_admin.");

            return;
        }

        $role = Role::query()->where('name', $roleEnum->value)->firstOrFail();

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => $password,
                'is_active' => true,
            ],
        );

        if (! $user->roles()->where('roles.id', $role->id)->exists()) {
            $user->roles()->sync([$role->id => ['created_at' => now()]]);
        }
    }
}

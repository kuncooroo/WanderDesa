<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Do not put secrets, payment keys, or default admin passwords here.
     * Super Admin is created only when SUPER_ADMIN_* env vars are set.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            SuperAdminSeeder::class,
            PilotStaffSeeder::class,
        ]);
    }
}

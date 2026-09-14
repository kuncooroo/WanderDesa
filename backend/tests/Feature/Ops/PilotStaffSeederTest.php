<?php

namespace Tests\Feature\Ops;

use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\PilotStaffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class PilotStaffSeederTest extends TestCase
{
    use InteractsWithRbac;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_empty_pilot_env_creates_no_users(): void
    {
        config(['ops.pilot_staff' => [
            'ticket_officer' => [
                'role' => RoleName::TicketOfficer->value,
                'name' => null,
                'email' => null,
                'password' => null,
            ],
        ]]);

        $this->seed(PilotStaffSeeder::class);

        $this->assertSame(0, User::query()->count());
    }

    public function test_ticket_officer_is_seeded_without_super_admin(): void
    {
        config(['ops.pilot_staff' => [
            'ticket_officer' => [
                'role' => RoleName::TicketOfficer->value,
                'name' => 'Loket Satu',
                'email' => 'officer@example.test',
                'password' => 'pilot-officer-secret',
            ],
            'break_glass' => [
                'role' => RoleName::SuperAdmin->value,
                'name' => 'Should Not',
                'email' => 'root@example.test',
                'password' => 'nope',
            ],
        ]]);

        $this->seed(PilotStaffSeeder::class);

        $officer = User::query()->where('email', 'officer@example.test')->first();
        $this->assertNotNull($officer);
        $this->assertTrue($officer->hasRole(RoleName::TicketOfficer));
        $this->assertFalse($officer->hasRole(RoleName::SuperAdmin));
        $this->assertNull(User::query()->where('email', 'root@example.test')->first());
    }
}

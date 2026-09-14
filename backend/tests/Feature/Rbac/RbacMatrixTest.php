<?php

namespace Tests\Feature\Rbac;

use App\Actions\Rbac\AssignRole;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\CheckIn;
use App\Models\Destination;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Support\Authorization\Authorizer;
use App\Support\Authorization\NavPermissionMap;
use App\Support\Authorization\PermissionCatalog;
use App\Support\Authorization\RolePermissionMatrix;
use App\Support\DeviceAbilities;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

#[Group('critical')]
class RbacMatrixTest extends TestCase
{
    use InteractsWithRbac;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_role_permission_seeder_installs_mvp_catalog(): void
    {
        $this->assertSame(
            count(RoleName::cases()),
            Role::query()->count(),
        );
        $this->assertSame(
            count(PermissionName::cases()),
            Permission::query()->count(),
        );
        $this->assertDatabaseMissing('roles', ['name' => 'staff']);

        foreach (RoleName::cases() as $roleName) {
            $role = Role::query()->where('name', $roleName->value)->firstOrFail();
            $expected = RolePermissionMatrix::permissionsFor($roleName);
            $actual = $role->permissions()->pluck('name')->sort()->values()->all();
            sort($expected);

            $this->assertSame($expected, $actual, "Matrix mismatch for {$roleName->value}");
        }
    }

    public function test_human_never_permissions_are_seeded_but_unassigned(): void
    {
        foreach (PermissionName::neverGrantedToHumans() as $permission) {
            $this->assertDatabaseHas('permissions', ['name' => $permission->value]);
            $this->assertSame(
                0,
                Permission::query()->where('name', $permission->value)->firstOrFail()->roles()->count(),
                "{$permission->value} must not be granted to any role",
            );
        }
    }

    public function test_ticket_officer_cannot_refund(): void
    {
        $user = $this->userWithRole(RoleName::TicketOfficer);
        $payment = Payment::factory()->create();

        $this->assertFalse(Authorizer::check($user, PermissionName::PaymentsRefund));
        $this->assertFalse($user->can('refund', $payment));
        $this->assertTrue($user->can('create', Order::class));
    }

    public function test_auditor_cannot_create_order(): void
    {
        $user = $this->userWithRole(RoleName::Auditor);

        $this->assertFalse(Authorizer::check($user, PermissionName::OrdersCreate));
        $this->assertFalse($user->can('create', Order::class));
        $this->assertTrue($user->can('viewAny', Order::class));
        $this->assertTrue($user->can(PermissionName::AuditLogsView->value));
    }

    public function test_gate_officer_cannot_manage_catalog(): void
    {
        $user = $this->userWithRole(RoleName::GateOfficer);
        $destination = Destination::factory()->create();
        $ticketType = TicketType::factory()->create();

        $this->assertFalse($user->can('create', Destination::class));
        $this->assertFalse($user->can('update', $destination));
        $this->assertFalse($user->can('create', TicketType::class));
        $this->assertFalse($user->can('update', $ticketType));
        $this->assertTrue($user->can('create', CheckIn::class));
        $this->assertTrue($user->can('validateTicket', Ticket::class));
    }

    public function test_super_admin_can_assign_roles(): void
    {
        $super = $this->userWithRole(RoleName::SuperAdmin);
        $target = User::factory()->create();

        $this->assertTrue($super->can('assignRoles', $target));

        app(AssignRole::class)->handle($super, $target, RoleName::TicketOfficer);

        $this->assertTrue($target->fresh()->hasRole(RoleName::TicketOfficer));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'roles.assign',
            'actor_id' => $super->id,
            'entity_id' => $target->id,
        ]);
    }

    public function test_admin_cannot_assign_super_admin_or_any_role(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $target = User::factory()->create();

        $this->assertFalse(Authorizer::check($admin, PermissionName::RolesAssign));
        $this->assertFalse($admin->can('assignRoles', $target));

        $this->expectException(AuthorizationException::class);
        app(AssignRole::class)->handle($admin, $target, RoleName::SuperAdmin);
    }

    public function test_authorizer_denies_by_default_for_unknown_and_never_granted(): void
    {
        $super = $this->userWithRole(RoleName::SuperAdmin);

        $this->assertFalse(Authorizer::check($super, 'not.a.real.permission'));
        $this->assertFalse(Authorizer::check($super, PermissionName::TicketsCreate));
        $this->assertFalse(Authorizer::check($super, PermissionName::CheckinsReverse));
        $this->assertFalse($super->can(PermissionName::TicketsCreate->value));
        $this->assertFalse(Authorizer::check(null, PermissionName::OrdersView));
    }

    public function test_finance_can_refund_ticket_officer_cannot(): void
    {
        $finance = $this->userWithRole(RoleName::Finance);
        $officer = $this->userWithRole(RoleName::TicketOfficer);

        $this->assertTrue(Authorizer::check($finance, PermissionName::PaymentsRefund));
        $this->assertFalse(Authorizer::check($officer, PermissionName::PaymentsRefund));
    }

    public function test_operator_has_maintenance_but_not_activate(): void
    {
        $operator = $this->userWithRole(RoleName::Operator);

        $this->assertTrue(Authorizer::check($operator, PermissionName::KiosksMaintenance));
        $this->assertFalse(Authorizer::check($operator, PermissionName::KiosksActivate));
        $this->assertFalse(Authorizer::check($operator, PermissionName::KiosksDeactivate));
    }

    public function test_nav_permission_helper_filters_modules(): void
    {
        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $auditor = $this->userWithRole(RoleName::Auditor);
        $gate = $this->userWithRole(RoleName::GateOfficer);

        $this->assertTrue(NavPermissionMap::canSee($officer, 'assisted_sale'));
        $this->assertFalse(NavPermissionMap::canSee($auditor, 'assisted_sale'));
        $this->assertTrue(NavPermissionMap::canSee($gate, 'checkin'));
        $this->assertFalse(NavPermissionMap::canSee($officer, 'checkin'));
        $this->assertContains('home', NavPermissionMap::visibleModules($officer));
    }

    public function test_device_abilities_are_distinct_from_staff_permissions(): void
    {
        foreach (DeviceAbilities::defaultKiosk() as $ability) {
            $this->assertFalse(
                PermissionCatalog::isKnown($ability),
                "Device ability {$ability} must not collide with staff permission catalog",
            );
        }

        $this->assertNotContains('roles.assign', DeviceAbilities::defaultKiosk());
        $this->assertNotContains(PermissionName::PaymentsRefund->value, DeviceAbilities::defaultKiosk());
    }

    public function test_super_admin_seeder_skips_without_env(): void
    {
        $this->seed(SuperAdminSeeder::class);

        $this->assertSame(0, User::query()->count());
    }

    public function test_super_admin_seeder_creates_user_when_env_set(): void
    {
        config()->set('app.env', 'testing');

        putenv('SUPER_ADMIN_NAME=Break Glass');
        putenv('SUPER_ADMIN_EMAIL=super@example.com');
        putenv('SUPER_ADMIN_PASSWORD=secure-pass-phrase');
        $_ENV['SUPER_ADMIN_NAME'] = 'Break Glass';
        $_ENV['SUPER_ADMIN_EMAIL'] = 'super@example.com';
        $_ENV['SUPER_ADMIN_PASSWORD'] = 'secure-pass-phrase';
        $_SERVER['SUPER_ADMIN_NAME'] = 'Break Glass';
        $_SERVER['SUPER_ADMIN_EMAIL'] = 'super@example.com';
        $_SERVER['SUPER_ADMIN_PASSWORD'] = 'secure-pass-phrase';

        try {
            $this->seed(SuperAdminSeeder::class);

            $user = User::query()->where('email', 'super@example.com')->first();
            $this->assertNotNull($user);
            $this->assertTrue($user->hasRole(RoleName::SuperAdmin));
            $this->assertTrue($user->hasPermission(PermissionName::RolesAssign));
        } finally {
            putenv('SUPER_ADMIN_NAME');
            putenv('SUPER_ADMIN_EMAIL');
            putenv('SUPER_ADMIN_PASSWORD');
            unset(
                $_ENV['SUPER_ADMIN_NAME'],
                $_ENV['SUPER_ADMIN_EMAIL'],
                $_ENV['SUPER_ADMIN_PASSWORD'],
                $_SERVER['SUPER_ADMIN_NAME'],
                $_SERVER['SUPER_ADMIN_EMAIL'],
                $_SERVER['SUPER_ADMIN_PASSWORD'],
            );
        }
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->assertSame(count(RoleName::cases()), Role::query()->count());
        $this->assertSame(count(PermissionName::cases()), Permission::query()->count());
    }
}

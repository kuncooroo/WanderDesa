<?php

namespace App\Actions\Users;

use App\Actions\Rbac\AssignRole;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Exceptions\DomainException;
use App\Models\User;
use App\Support\AuditWriter;
use App\Support\Authorization\Authorizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Create a staff user (docs/09 B17). Role assign only when actor has roles.assign.
 */
final class CreateUser
{
    public function __construct(
        private readonly AuditWriter $audit,
        private readonly AssignRole $assignRole,
    ) {}

    /**
     * @param  array{
     *     name: string,
     *     email: string,
     *     password: string,
     *     is_active?: bool,
     *     primary_role?: string|null
     * }  $data
     */
    public function handle(User $actor, array $data, ?Request $request = null): User
    {
        Authorizer::authorize($actor, PermissionName::UsersCreate);

        $primaryRole = $data['primary_role'] ?? null;

        if ($primaryRole !== null && $primaryRole !== '') {
            Authorizer::authorize($actor, PermissionName::RolesAssign);
            $this->assertAssignableRole($actor, $primaryRole);
        }

        return DB::transaction(function () use ($actor, $data, $primaryRole, $request): User {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'is_active' => $data['is_active'] ?? true,
            ]);

            if (is_string($primaryRole) && $primaryRole !== '') {
                $this->assignRole->handle($actor, $user, $primaryRole);
            }

            $fresh = $user->fresh(['roles']);

            $this->audit->write(
                action: 'user.upsert',
                actorType: 'user',
                actorId: $actor->id,
                entityType: 'user',
                entityId: $fresh->id,
                before: null,
                after: $this->snapshot($fresh),
                meta: ['operation' => 'create'],
                request: $request,
            );

            return $fresh->loadMissing('roles.permissions');
        });
    }

    private function assertAssignableRole(User $actor, string $roleName): void
    {
        if (! in_array($roleName, array_map(fn (RoleName $r) => $r->value, RoleName::cases()), true)) {
            throw new DomainException('validation.failed', 'Unknown role.', 422);
        }

        if ($roleName === RoleName::SuperAdmin->value && ! $actor->hasRole(RoleName::SuperAdmin)) {
            throw new DomainException(
                'rbac.forbidden',
                'Only Super Admin may assign the super_admin role.',
                403,
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => $user->is_active,
            'roles' => $user->roles->pluck('name')->sort()->values()->all(),
        ];
    }
}

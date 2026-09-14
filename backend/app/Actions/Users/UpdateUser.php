<?php

namespace App\Actions\Users;

use App\Actions\Rbac\AssignRole;
use App\Actions\Rbac\RevokeRole;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Exceptions\DomainException;
use App\Models\User;
use App\Support\AuditWriter;
use App\Support\Authorization\Authorizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Update staff profile / active flag; optional primary-role sync (docs/09 B17).
 */
final class UpdateUser
{
    public function __construct(
        private readonly AuditWriter $audit,
        private readonly AssignRole $assignRole,
        private readonly RevokeRole $revokeRole,
    ) {}

    /**
     * @param  array{
     *     name?: string,
     *     email?: string,
     *     password?: string|null,
     *     is_active?: bool,
     *     primary_role?: string|null,
     *     sync_primary_role?: bool
     * }  $data
     */
    public function handle(User $actor, User $target, array $data, ?Request $request = null): User
    {
        Authorizer::authorize($actor, PermissionName::UsersUpdate);
        $this->assertCanMutateTarget($actor, $target);

        $syncRole = (bool) ($data['sync_primary_role'] ?? false);
        $primaryRole = $data['primary_role'] ?? null;

        if ($syncRole) {
            Authorizer::authorize($actor, PermissionName::RolesAssign);

            if (is_string($primaryRole) && $primaryRole !== '') {
                $this->assertAssignableRole($actor, $primaryRole);
            }
        }

        if (array_key_exists('is_active', $data)
            && $data['is_active'] === false
            && $actor->id === $target->id) {
            throw new DomainException(
                'user.self_action_forbidden',
                'You cannot deactivate your own account.',
                422,
            );
        }

        return DB::transaction(function () use ($actor, $target, $data, $syncRole, $primaryRole, $request): User {
            /** @var User $locked */
            $locked = User::query()->whereKey($target->id)->lockForUpdate()->firstOrFail();
            $before = $this->snapshot($locked->load('roles'));

            $payload = [];
            foreach (['name', 'email', 'is_active'] as $field) {
                if (array_key_exists($field, $data)) {
                    $payload[$field] = $data[$field];
                }
            }

            if (array_key_exists('password', $data)
                && is_string($data['password'])
                && $data['password'] !== '') {
                $payload['password'] = $data['password'];
            }

            if ($payload !== []) {
                $locked->fill($payload);
                $locked->save();
            }

            if ($syncRole) {
                $this->syncPrimaryRole($actor, $locked, is_string($primaryRole) ? $primaryRole : '');
            }

            $fresh = $locked->fresh(['roles']);

            $this->audit->write(
                action: 'user.upsert',
                actorType: 'user',
                actorId: $actor->id,
                entityType: 'user',
                entityId: $fresh->id,
                before: $before,
                after: $this->snapshot($fresh),
                meta: [
                    'operation' => 'update',
                    'password_changed' => array_key_exists('password', $payload),
                ],
                request: $request,
            );

            return $fresh->loadMissing('roles.permissions');
        });
    }

    private function syncPrimaryRole(User $actor, User $target, string $primaryRole): void
    {
        $current = $target->roles()->pluck('name')->all();

        if ($primaryRole === '') {
            foreach ($current as $roleName) {
                $this->revokeRole->handle($actor, $target, $roleName);
            }

            return;
        }

        foreach ($current as $roleName) {
            if ($roleName !== $primaryRole) {
                $this->revokeRole->handle($actor, $target, $roleName);
            }
        }

        if (! in_array($primaryRole, $current, true)) {
            $this->assignRole->handle($actor, $target, $primaryRole);
        }
    }

    private function assertCanMutateTarget(User $actor, User $target): void
    {
        if ($target->hasRole(RoleName::SuperAdmin) && ! $actor->hasRole(RoleName::SuperAdmin)) {
            throw new DomainException(
                'user.protected',
                'Only Super Admin may update a Super Admin account.',
                403,
            );
        }
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

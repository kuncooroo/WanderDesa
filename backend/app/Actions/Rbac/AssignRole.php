<?php

namespace App\Actions\Rbac;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Role;
use App\Models\User;
use App\Support\AuditWriter;
use App\Support\Authorization\Authorizer;
use Illuminate\Support\Facades\DB;

/**
 * Assign a role to a staff user. Requires roles.assign (Super Admin only in MVP).
 * Audit hooks land fully in TASK-016; we still write an append-only trail here.
 */
final class AssignRole
{
    public function __construct(
        private readonly AuditWriter $audit,
    ) {}

    public function handle(User $actor, User $target, RoleName|string $role): User
    {
        Authorizer::authorize($actor, PermissionName::RolesAssign);

        $roleName = $role instanceof RoleName ? $role->value : $role;
        $roleModel = Role::query()->where('name', $roleName)->firstOrFail();

        DB::transaction(function () use ($actor, $target, $roleModel, $roleName): void {
            if ($target->roles()->where('roles.id', $roleModel->id)->exists()) {
                return;
            }

            $before = $target->roles()->pluck('name')->sort()->values()->all();

            $target->roles()->attach($roleModel->id, ['created_at' => now()]);

            $after = $target->fresh()->roles()->pluck('name')->sort()->values()->all();

            $this->audit->write(
                action: 'roles.assign',
                actorType: 'user',
                actorId: $actor->id,
                entityType: 'user',
                entityId: $target->id,
                before: ['roles' => $before],
                after: ['roles' => $after],
                meta: [
                    'assigned_role' => $roleName,
                    'target_user_id' => $target->id,
                ],
            );
        });

        return $target->fresh(['roles.permissions']);
    }
}

<?php

namespace App\Actions\Users;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Exceptions\DomainException;
use App\Models\User;
use App\Support\AuditWriter;
use App\Support\Authorization\Authorizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Soft-delete a staff user (docs/07 users.delete). Never deletes payments/tickets.
 */
final class SoftDeleteUser
{
    public function __construct(
        private readonly AuditWriter $audit,
    ) {}

    public function handle(User $actor, User $target, ?Request $request = null): void
    {
        Authorizer::authorize($actor, PermissionName::UsersDelete);

        if ($actor->id === $target->id) {
            throw new DomainException(
                'user.self_action_forbidden',
                'You cannot delete your own account.',
                422,
            );
        }

        if ($target->hasRole(RoleName::SuperAdmin) && ! $actor->hasRole(RoleName::SuperAdmin)) {
            throw new DomainException(
                'user.protected',
                'Only Super Admin may delete a Super Admin account.',
                403,
            );
        }

        DB::transaction(function () use ($actor, $target, $request): void {
            /** @var User $locked */
            $locked = User::query()->whereKey($target->id)->lockForUpdate()->firstOrFail();

            $before = [
                'id' => $locked->id,
                'name' => $locked->name,
                'email' => $locked->email,
                'is_active' => $locked->is_active,
                'roles' => $locked->roles()->pluck('name')->sort()->values()->all(),
            ];

            $locked->delete();

            $this->audit->write(
                action: 'user.deleted',
                actorType: 'user',
                actorId: $actor->id,
                entityType: 'user',
                entityId: $before['id'],
                before: $before,
                after: null,
                meta: ['operation' => 'soft_delete'],
                request: $request,
            );
        });
    }
}

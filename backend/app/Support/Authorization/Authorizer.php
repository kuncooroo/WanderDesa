<?php

namespace App\Support\Authorization;

use App\Enums\PermissionName;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Deny-by-default authorization for Actions and shared domain entry points.
 *
 * Usage in Actions:
 *   Authorizer::authorize($actor, PermissionName::OrdersCreate);
 *   // or: Authorizer::authorize($actor, 'payments.refund');
 *
 * Policies and `$user->can('orders.view')` also resolve via Gate::before.
 * Client UI checks are UX only — always re-check here before mutations.
 */
final class Authorizer
{
    public static function check(
        Authenticatable|User|null $actor,
        PermissionName|string $permission,
    ): bool {
        if (! $actor instanceof User) {
            return false;
        }

        $name = $permission instanceof PermissionName ? $permission->value : $permission;

        if (PermissionCatalog::isNeverGrantedToHumans($name)) {
            return false;
        }

        if (! PermissionCatalog::isKnown($name)) {
            return false;
        }

        return $actor->hasPermission($name);
    }

    /**
     * @throws AuthorizationException
     */
    public static function authorize(
        Authenticatable|User|null $actor,
        PermissionName|string $permission,
    ): void {
        if (! self::check($actor, $permission)) {
            $name = $permission instanceof PermissionName ? $permission->value : $permission;

            throw new AuthorizationException("Missing permission: {$name}");
        }
    }
}

<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\User;
use App\Support\Authorization\Authorizer;

/**
 * Staff user administration. Role assignment is roles.assign only.
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return Authorizer::check($actor, PermissionName::UsersView);
    }

    public function view(User $actor, User $target): bool
    {
        return Authorizer::check($actor, PermissionName::UsersView);
    }

    public function create(User $actor): bool
    {
        return Authorizer::check($actor, PermissionName::UsersCreate);
    }

    public function update(User $actor, User $target): bool
    {
        return Authorizer::check($actor, PermissionName::UsersUpdate);
    }

    public function delete(User $actor, User $target): bool
    {
        return Authorizer::check($actor, PermissionName::UsersDelete);
    }

    public function assignRoles(User $actor, ?User $target = null): bool
    {
        return Authorizer::check($actor, PermissionName::RolesAssign);
    }
}

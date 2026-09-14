<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Role;
use App\Models\User;
use App\Support\Authorization\Authorizer;

/**
 * Role catalog is read-only in MVP (docs/09 B18–B19).
 * Visible to roles.assign holders and users.view (Admin matrix / Auditor transparency).
 */
class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return Authorizer::check($user, PermissionName::RolesAssign)
            || Authorizer::check($user, PermissionName::UsersView);
    }

    public function view(User $user, Role $role): bool
    {
        return $this->viewAny($user);
    }
}

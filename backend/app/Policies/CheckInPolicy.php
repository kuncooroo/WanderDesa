<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\CheckIn;
use App\Models\User;
use App\Support\Authorization\Authorizer;

/**
 * checkins.reverse is never granted in MVP.
 */
class CheckInPolicy
{
    public function viewAny(User $user): bool
    {
        return Authorizer::check($user, PermissionName::CheckinsView);
    }

    public function view(User $user, CheckIn $checkIn): bool
    {
        return Authorizer::check($user, PermissionName::CheckinsView);
    }

    public function create(User $user): bool
    {
        return Authorizer::check($user, PermissionName::CheckinsCreate);
    }

    public function reverse(User $user, ?CheckIn $checkIn = null): bool
    {
        return Authorizer::check($user, PermissionName::CheckinsReverse);
    }
}

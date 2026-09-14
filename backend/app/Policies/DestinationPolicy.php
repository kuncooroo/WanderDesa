<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Destination;
use App\Models\User;
use App\Support\Authorization\Authorizer;

class DestinationPolicy
{
    public function viewAny(User $user): bool
    {
        return Authorizer::check($user, PermissionName::DestinationsView);
    }

    public function view(User $user, Destination $destination): bool
    {
        return Authorizer::check($user, PermissionName::DestinationsView);
    }

    public function create(User $user): bool
    {
        return Authorizer::check($user, PermissionName::DestinationsManage);
    }

    public function update(User $user, Destination $destination): bool
    {
        return Authorizer::check($user, PermissionName::DestinationsManage);
    }

    public function delete(User $user, Destination $destination): bool
    {
        return Authorizer::check($user, PermissionName::DestinationsManage);
    }
}

<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Device;
use App\Models\User;
use App\Support\Authorization\Authorizer;

class DevicePolicy
{
    public function viewAny(User $user): bool
    {
        return Authorizer::check($user, PermissionName::KiosksView);
    }

    public function view(User $user, Device $device): bool
    {
        return Authorizer::check($user, PermissionName::KiosksView);
    }

    public function create(User $user): bool
    {
        return Authorizer::check($user, PermissionName::KiosksCreate);
    }

    public function update(User $user, Device $device): bool
    {
        return Authorizer::check($user, PermissionName::KiosksUpdate);
    }

    public function activate(User $user, ?Device $device = null): bool
    {
        return Authorizer::check($user, PermissionName::KiosksActivate);
    }

    public function deactivate(User $user, ?Device $device = null): bool
    {
        return Authorizer::check($user, PermissionName::KiosksDeactivate);
    }

    public function maintenance(User $user, ?Device $device = null): bool
    {
        return Authorizer::check($user, PermissionName::KiosksMaintenance);
    }
}

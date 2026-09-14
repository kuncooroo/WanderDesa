<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Setting;
use App\Models\User;
use App\Support\Authorization\Authorizer;

class SettingPolicy
{
    public function viewAny(User $user): bool
    {
        return Authorizer::check($user, PermissionName::SettingsView);
    }

    public function view(User $user, Setting $setting): bool
    {
        return Authorizer::check($user, PermissionName::SettingsView);
    }

    public function update(User $user, ?Setting $setting = null): bool
    {
        return Authorizer::check($user, PermissionName::SettingsUpdate);
    }

    public function manageIntegrations(User $user): bool
    {
        return Authorizer::check($user, PermissionName::IntegrationsManage);
    }
}

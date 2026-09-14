<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Report;
use App\Models\User;
use App\Support\Authorization\Authorizer;

class ReportPolicy
{
    public function viewAny(User $user): bool
    {
        return Authorizer::check($user, PermissionName::ReportsView);
    }

    public function view(User $user, ?Report $report = null): bool
    {
        return Authorizer::check($user, PermissionName::ReportsView);
    }

    public function export(User $user): bool
    {
        return Authorizer::check($user, PermissionName::ReportsExport);
    }
}

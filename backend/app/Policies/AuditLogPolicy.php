<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Authorization\Authorizer;

/**
 * Audit logs are append-only; no update/delete for any role.
 */
class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return Authorizer::check($user, PermissionName::AuditLogsView);
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        return Authorizer::check($user, PermissionName::AuditLogsView);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AuditLog $auditLog): bool
    {
        return false;
    }

    public function delete(User $user, AuditLog $auditLog): bool
    {
        return false;
    }
}

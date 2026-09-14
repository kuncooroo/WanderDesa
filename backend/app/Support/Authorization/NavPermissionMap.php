<?php

namespace App\Support\Authorization;

use App\Enums\PermissionName;
use App\Models\User;

/**
 * Dashboard nav visibility keys for TASK-018 (UX only — server still enforces).
 *
 * @see docs/09-UI-UX.md role-specific navigation
 */
final class NavPermissionMap
{
    /**
     * Module key => any-of permissions that reveal the module in the sidebar.
     *
     * @return array<string, list<string>>
     */
    public static function modules(): array
    {
        return [
            'home' => [], // authenticated staff always
            'assisted_sale' => [
                PermissionName::OrdersCreate->value,
                PermissionName::PaymentsCreate->value,
            ],
            'orders' => [PermissionName::OrdersView->value],
            'payments' => [PermissionName::PaymentsView->value],
            'cashier_shifts' => [
                PermissionName::PaymentsView->value,
                PermissionName::PaymentsCreate->value,
            ],
            'tickets' => [PermissionName::TicketsView->value],
            'checkin' => [
                PermissionName::CheckinsCreate->value,
                PermissionName::TicketsValidate->value,
            ],
            'catalog' => [
                PermissionName::DestinationsView->value,
                PermissionName::TicketTypesView->value,
            ],
            'kiosks' => [PermissionName::KiosksView->value],
            'reports' => [
                PermissionName::ReportsView->value,
                PermissionName::AnalyticsView->value,
            ],
            'users' => [PermissionName::UsersView->value],
            'roles' => [
                PermissionName::RolesAssign->value,
                PermissionName::UsersView->value,
            ],
            'audit_logs' => [PermissionName::AuditLogsView->value],
            'settings' => [
                PermissionName::SettingsView->value,
                PermissionName::SettingsUpdate->value,
            ],
        ];
    }

    public static function canSee(User $user, string $module): bool
    {
        $modules = self::modules();

        if (! array_key_exists($module, $modules)) {
            return false;
        }

        $required = $modules[$module];

        if ($required === []) {
            return true;
        }

        foreach ($required as $permission) {
            if ($user->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public static function visibleModules(User $user): array
    {
        $visible = [];

        foreach (array_keys(self::modules()) as $module) {
            if (self::canSee($user, $module)) {
                $visible[] = $module;
            }
        }

        return $visible;
    }
}

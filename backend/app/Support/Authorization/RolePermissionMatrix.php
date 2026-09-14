<?php

namespace App\Support\Authorization;

use App\Enums\PermissionName;
use App\Enums\RoleName;

/**
 * Role × permission defaults from docs/07-RBAC.md §5.
 * ✓ = grant · blank/○/✗ = deny (○ defaults deny for MVP).
 */
final class RolePermissionMatrix
{
    /**
     * @return array<string, list<string>> role name => permission names
     */
    public static function grants(): array
    {
        $matrix = [];

        foreach (RoleName::cases() as $role) {
            $matrix[$role->value] = self::permissionsFor($role);
        }

        return $matrix;
    }

    /**
     * @return list<string>
     */
    public static function permissionsFor(RoleName $role): array
    {
        return array_map(
            static fn (PermissionName $p) => $p->value,
            match ($role) {
                RoleName::SuperAdmin => self::superAdmin(),
                RoleName::Admin => self::admin(),
                RoleName::Manager => self::manager(),
                RoleName::Finance => self::finance(),
                RoleName::TicketOfficer => self::ticketOfficer(),
                RoleName::GateOfficer => self::gateOfficer(),
                RoleName::Operator => self::operator(),
                RoleName::Auditor => self::auditor(),
            },
        );
    }

    public static function roleHas(RoleName $role, PermissionName|string $permission): bool
    {
        $name = $permission instanceof PermissionName ? $permission->value : $permission;

        return in_array($name, self::permissionsFor($role), true);
    }

    /**
     * @return list<PermissionName>
     */
    private static function superAdmin(): array
    {
        return [
            PermissionName::UsersView,
            PermissionName::UsersCreate,
            PermissionName::UsersUpdate,
            PermissionName::UsersDelete,
            PermissionName::RolesAssign,
            PermissionName::DestinationsView,
            PermissionName::DestinationsManage,
            PermissionName::TicketTypesView,
            PermissionName::TicketTypesManage,
            PermissionName::OrdersView,
            PermissionName::OrdersCreate,
            PermissionName::OrdersUpdate,
            PermissionName::OrdersCancel,
            PermissionName::TransactionsView,
            PermissionName::PaymentsView,
            PermissionName::PaymentsCreate,
            PermissionName::PaymentsRefund,
            PermissionName::TicketsView,
            PermissionName::TicketsCancel,
            PermissionName::TicketsValidate,
            PermissionName::TicketsPrint,
            PermissionName::TicketsReprint,
            PermissionName::CheckinsView,
            PermissionName::CheckinsCreate,
            PermissionName::ReportsView,
            PermissionName::ReportsExport,
            PermissionName::AnalyticsView,
            PermissionName::SettingsView,
            PermissionName::SettingsUpdate,
            PermissionName::IntegrationsManage,
            PermissionName::AuditLogsView,
            PermissionName::KiosksView,
            PermissionName::KiosksCreate,
            PermissionName::KiosksUpdate,
            PermissionName::KiosksActivate,
            PermissionName::KiosksDeactivate,
            PermissionName::KiosksMaintenance,
        ];
    }

    /**
     * @return list<PermissionName>
     */
    private static function admin(): array
    {
        return [
            PermissionName::UsersView,
            PermissionName::UsersCreate,
            PermissionName::UsersUpdate,
            PermissionName::UsersDelete,
            PermissionName::DestinationsView,
            PermissionName::DestinationsManage,
            PermissionName::TicketTypesView,
            PermissionName::TicketTypesManage,
            PermissionName::OrdersView,
            PermissionName::OrdersCreate,
            PermissionName::OrdersUpdate,
            PermissionName::OrdersCancel,
            PermissionName::TransactionsView,
            PermissionName::PaymentsView,
            PermissionName::PaymentsCreate,
            PermissionName::TicketsView,
            PermissionName::TicketsCancel,
            PermissionName::TicketsValidate,
            PermissionName::TicketsPrint,
            PermissionName::TicketsReprint,
            PermissionName::CheckinsView,
            PermissionName::CheckinsCreate,
            PermissionName::ReportsView,
            PermissionName::ReportsExport,
            PermissionName::AnalyticsView,
            PermissionName::SettingsView,
            PermissionName::AuditLogsView,
            PermissionName::KiosksView,
            PermissionName::KiosksCreate,
            PermissionName::KiosksUpdate,
            PermissionName::KiosksActivate,
            PermissionName::KiosksDeactivate,
            PermissionName::KiosksMaintenance,
        ];
    }

    /**
     * @return list<PermissionName>
     */
    private static function manager(): array
    {
        return [
            PermissionName::DestinationsView,
            PermissionName::TicketTypesView,
            PermissionName::OrdersView,
            PermissionName::TransactionsView,
            PermissionName::PaymentsView,
            PermissionName::TicketsView,
            PermissionName::CheckinsView,
            PermissionName::ReportsView,
            PermissionName::ReportsExport,
            PermissionName::AnalyticsView,
            PermissionName::KiosksView,
        ];
    }

    /**
     * @return list<PermissionName>
     */
    private static function finance(): array
    {
        return [
            PermissionName::DestinationsView,
            PermissionName::TicketTypesView,
            PermissionName::OrdersView,
            PermissionName::TransactionsView,
            PermissionName::PaymentsView,
            PermissionName::PaymentsRefund,
            PermissionName::TicketsView,
            PermissionName::ReportsView,
            PermissionName::ReportsExport,
            PermissionName::AuditLogsView,
        ];
    }

    /**
     * @return list<PermissionName>
     */
    private static function ticketOfficer(): array
    {
        return [
            PermissionName::DestinationsView,
            PermissionName::TicketTypesView,
            PermissionName::OrdersView,
            PermissionName::OrdersCreate,
            PermissionName::OrdersCancel,
            PermissionName::PaymentsView,
            PermissionName::PaymentsCreate,
            PermissionName::TicketsView,
            PermissionName::TicketsPrint,
            PermissionName::TicketsReprint,
        ];
    }

    /**
     * @return list<PermissionName>
     */
    private static function gateOfficer(): array
    {
        return [
            PermissionName::DestinationsView,
            PermissionName::TicketsView,
            PermissionName::TicketsValidate,
            PermissionName::CheckinsView,
            PermissionName::CheckinsCreate,
        ];
    }

    /**
     * @return list<PermissionName>
     */
    private static function operator(): array
    {
        return [
            PermissionName::DestinationsView,
            PermissionName::TicketTypesView,
            PermissionName::TicketsView,
            PermissionName::TicketsReprint,
            PermissionName::KiosksView,
            PermissionName::KiosksUpdate,
            PermissionName::KiosksMaintenance,
        ];
    }

    /**
     * @return list<PermissionName>
     */
    private static function auditor(): array
    {
        return [
            PermissionName::UsersView,
            PermissionName::DestinationsView,
            PermissionName::TicketTypesView,
            PermissionName::OrdersView,
            PermissionName::TransactionsView,
            PermissionName::PaymentsView,
            PermissionName::TicketsView,
            PermissionName::CheckinsView,
            PermissionName::ReportsView,
            PermissionName::ReportsExport,
            PermissionName::AuditLogsView,
            PermissionName::KiosksView,
        ];
    }
}

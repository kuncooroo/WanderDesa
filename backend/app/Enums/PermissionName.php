<?php

namespace App\Enums;

/**
 * Canonical MVP permission catalog (docs/07-RBAC.md §3).
 * Naming: resource.action
 */
enum PermissionName: string
{
    // Users
    case UsersView = 'users.view';
    case UsersCreate = 'users.create';
    case UsersUpdate = 'users.update';
    case UsersDelete = 'users.delete';
    case RolesAssign = 'roles.assign';

    // Catalog
    case DestinationsView = 'destinations.view';
    case DestinationsManage = 'destinations.manage';
    case TicketTypesView = 'ticket_types.view';
    case TicketTypesManage = 'ticket_types.manage';

    // Orders
    case OrdersView = 'orders.view';
    case OrdersCreate = 'orders.create';
    case OrdersUpdate = 'orders.update';
    case OrdersCancel = 'orders.cancel';

    // Transactions (visibility alias; mutations denied globally)
    case TransactionsView = 'transactions.view';
    case TransactionsCreate = 'transactions.create';
    case TransactionsUpdate = 'transactions.update';
    case TransactionsCancel = 'transactions.cancel';

    // Payments
    case PaymentsView = 'payments.view';
    case PaymentsCreate = 'payments.create';
    case PaymentsRefund = 'payments.refund';

    // Tickets
    case TicketsView = 'tickets.view';
    case TicketsCreate = 'tickets.create';
    case TicketsUpdate = 'tickets.update';
    case TicketsCancel = 'tickets.cancel';
    case TicketsValidate = 'tickets.validate';
    case TicketsPrint = 'tickets.print';
    case TicketsReprint = 'tickets.reprint';

    // Check-ins
    case CheckinsView = 'checkins.view';
    case CheckinsCreate = 'checkins.create';
    case CheckinsReverse = 'checkins.reverse';

    // Reports / analytics
    case ReportsView = 'reports.view';
    case ReportsExport = 'reports.export';
    case AnalyticsView = 'analytics.view';

    // Settings / integrations / audit
    case SettingsView = 'settings.view';
    case SettingsUpdate = 'settings.update';
    case IntegrationsManage = 'integrations.manage';
    case AuditLogsView = 'audit_logs.view';

    // Kiosks / devices
    case KiosksView = 'kiosks.view';
    case KiosksCreate = 'kiosks.create';
    case KiosksUpdate = 'kiosks.update';
    case KiosksActivate = 'kiosks.activate';
    case KiosksDeactivate = 'kiosks.deactivate';
    case KiosksMaintenance = 'kiosks.maintenance';

    public function displayName(): string
    {
        return str($this->value)->replace('.', ' ')->title()->toString();
    }

    /**
     * Never granted to human roles in MVP (docs/07 §3.11 / matrix ✗).
     *
     * @return list<self>
     */
    public static function neverGrantedToHumans(): array
    {
        return [
            self::TransactionsCreate,
            self::TransactionsUpdate,
            self::TransactionsCancel,
            self::TicketsCreate,
            self::TicketsUpdate,
            self::CheckinsReverse,
        ];
    }

    public function isNeverGrantedToHumans(): bool
    {
        return in_array($this, self::neverGrantedToHumans(), true);
    }
}

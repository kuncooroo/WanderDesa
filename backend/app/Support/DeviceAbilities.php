<?php

namespace App\Support;

/**
 * Least-privilege abilities for kiosk Sanctum tokens.
 *
 * Device abilities are NOT staff RBAC permissions (docs/07-RBAC.md §6).
 * Staff use dotted permission names (e.g. orders.create) via roles.
 * Device tokens use Sanctum ability strings (colon style) issued at activation
 * (TASK-019). Never grant refunds, user admin, settings, roles.assign, or
 * checkins.reverse on device tokens.
 *
 * Docs §6 → Sanctum ability mapping:
 *   device.heartbeat  → devices:heartbeat
 *   catalog.read      → catalog:read
 *   orders.create     → orders:create
 *   payments.initiate → payments:initiate
 *   payments.status   → payments:poll
 *   tickets.read_own  → tickets:read_own
 *   tickets.print_own → tickets:print_own
 */
final class DeviceAbilities
{
    public const CATALOG_READ = 'catalog:read';

    public const ORDERS_CREATE = 'orders:create';

    public const PAYMENTS_INITIATE = 'payments:initiate';

    public const PAYMENTS_POLL = 'payments:poll';

    public const HEARTBEAT = 'devices:heartbeat';

    public const TICKETS_READ_OWN = 'tickets:read_own';

    public const TICKETS_PRINT_OWN = 'tickets:print_own';

    /**
     * @return list<string>
     */
    public static function defaultKiosk(): array
    {
        return [
            self::CATALOG_READ,
            self::ORDERS_CREATE,
            self::PAYMENTS_INITIATE,
            self::PAYMENTS_POLL,
            self::HEARTBEAT,
            self::TICKETS_READ_OWN,
            self::TICKETS_PRINT_OWN,
        ];
    }
}

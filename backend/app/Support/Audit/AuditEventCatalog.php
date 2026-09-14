<?php

namespace App\Support\Audit;

/**
 * Minimum auditable events from docs/05 §32 — coverage tracker for TASK-016+.
 *
 * Status:
 * - implemented: Action writes this action string today
 * - deferred: expected when a later task lands (device, printer, jobs, …)
 * - covered_by: satisfied by a related implemented action
 */
final class AuditEventCatalog
{
    public const STATUS_IMPLEMENTED = 'implemented';

    public const STATUS_DEFERRED = 'deferred';

    public const STATUS_COVERED_BY = 'covered_by';

    /**
     * @return array<string, array{status: string, notes?: string, covered_by?: string}>
     */
    public static function events(): array
    {
        return [
            'order.created' => ['status' => self::STATUS_IMPLEMENTED],
            'order.cancelled' => ['status' => self::STATUS_IMPLEMENTED],
            'order.expired' => ['status' => self::STATUS_IMPLEMENTED],
            'payment.initiated' => ['status' => self::STATUS_IMPLEMENTED],
            'payment.paid' => ['status' => self::STATUS_IMPLEMENTED],
            'payment.failed' => ['status' => self::STATUS_IMPLEMENTED],
            'payment.cancelled' => ['status' => self::STATUS_IMPLEMENTED],
            'payment.expired' => ['status' => self::STATUS_IMPLEMENTED],
            'payment.reconciled' => [
                'status' => self::STATUS_COVERED_BY,
                'covered_by' => 'payment.paid',
                'notes' => 'Reconcile applies MarkPaymentPaid / failed paths',
            ],
            'refund.created' => ['status' => self::STATUS_IMPLEMENTED],
            'ticket.issued' => ['status' => self::STATUS_IMPLEMENTED],
            'ticket.printed' => ['status' => self::STATUS_IMPLEMENTED],
            'ticket.reprinted' => ['status' => self::STATUS_IMPLEMENTED],
            'ticket.print_failed' => ['status' => self::STATUS_IMPLEMENTED],
            'ticket.checked_in' => ['status' => self::STATUS_IMPLEMENTED],
            'ticket.validated' => ['status' => self::STATUS_IMPLEMENTED],
            'ticket.validate_denied' => [
                'status' => self::STATUS_COVERED_BY,
                'covered_by' => 'ticket.validated',
                'notes' => 'DENY reason_code stored on ticket.validated after_json',
            ],
            'ticket.expired' => ['status' => self::STATUS_IMPLEMENTED],
            'ticket.refunded' => ['status' => self::STATUS_IMPLEMENTED],
            'device.registered' => ['status' => self::STATUS_IMPLEMENTED],
            'device.activated' => ['status' => self::STATUS_IMPLEMENTED, 'notes' => 'Staff issues one-time activation material'],
            'device.token_issued' => ['status' => self::STATUS_IMPLEMENTED, 'notes' => 'Kiosk exchanges activation code for Sanctum token'],
            'device.updated' => ['status' => self::STATUS_IMPLEMENTED],
            'device.maintenance_on' => ['status' => self::STATUS_IMPLEMENTED],
            'device.maintenance_off' => ['status' => self::STATUS_IMPLEMENTED],
            'device.disabled' => ['status' => self::STATUS_IMPLEMENTED],
            'device.offline_detected' => ['status' => self::STATUS_IMPLEMENTED],
            'gate.open_command' => ['status' => self::STATUS_DEFERRED, 'notes' => 'optional gate'],
            'report.exported' => ['status' => self::STATUS_IMPLEMENTED],
            'auth.login_success' => ['status' => self::STATUS_IMPLEMENTED],
            'auth.login_failed' => ['status' => self::STATUS_IMPLEMENTED],
            'auth.logout' => ['status' => self::STATUS_IMPLEMENTED],
            'roles.assign' => ['status' => self::STATUS_IMPLEMENTED],
            'roles.revoke' => ['status' => self::STATUS_IMPLEMENTED],
            'settings.updated' => ['status' => self::STATUS_IMPLEMENTED],
            'destination.upsert' => ['status' => self::STATUS_IMPLEMENTED],
            'ticket_type.upsert' => [
                'status' => self::STATUS_IMPLEMENTED,
                'notes' => 'Includes price before/after; meta.price_changed on update',
            ],
            'ticket_type.price_changed' => [
                'status' => self::STATUS_COVERED_BY,
                'covered_by' => 'ticket_type.upsert',
                'notes' => 'Price before/after on ticket_type.upsert audit',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function implementedActions(): array
    {
        $actions = [];

        foreach (self::events() as $action => $meta) {
            if ($meta['status'] === self::STATUS_IMPLEMENTED) {
                $actions[] = $action;
            }
        }

        return $actions;
    }

    /**
     * @return list<string>
     */
    public static function deferredActions(): array
    {
        $actions = [];

        foreach (self::events() as $action => $meta) {
            if ($meta['status'] === self::STATUS_DEFERRED) {
                $actions[] = $action;
            }
        }

        return $actions;
    }
}

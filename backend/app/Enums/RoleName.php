<?php

namespace App\Enums;

/**
 * MVP seeded roles (docs/07-RBAC.md §1.2). No generic `staff` role.
 */
enum RoleName: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Manager = 'manager';
    case Finance = 'finance';
    case TicketOfficer = 'ticket_officer';
    case GateOfficer = 'gate_officer';
    case Operator = 'operator';
    case Auditor = 'auditor';

    public function displayName(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Admin => 'Admin',
            self::Manager => 'Manager',
            self::Finance => 'Finance',
            self::TicketOfficer => 'Ticket Officer',
            self::GateOfficer => 'Gate Officer',
            self::Operator => 'Operator',
            self::Auditor => 'Auditor',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SuperAdmin => 'System ownership, integrations, role assignment, break-glass',
            self::Admin => 'Destination ops configuration and non-break-glass user administration',
            self::Manager => 'Operational monitoring and business reports',
            self::Finance => 'Payments visibility, refunds, reconciliation',
            self::TicketOfficer => 'Assisted ticket sales and print',
            self::GateOfficer => 'QR validation and check-in',
            self::Operator => 'Kiosk fleet health and maintenance mode',
            self::Auditor => 'Read-only audit trail and compliance reports',
        };
    }

    /**
     * @return list<self>
     */
    public static function mvp(): array
    {
        return self::cases();
    }
}

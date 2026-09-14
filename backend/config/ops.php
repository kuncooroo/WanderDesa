<?php

return [
    'notifications' => [
        /*
         * Optional Laravel Mail fan-out for ops alerts. Database channel is always used.
         * Keep false unless SMTP/SES/etc. is configured (SRS-MAIL-02/03).
         */
        'mail' => filter_var(env('OPS_NOTIFICATION_MAIL', false), FILTER_VALIDATE_BOOLEAN),
    ],

    /*
     * Daily MySQL dump (SRS-BK-01). Scheduled at 02:15 app timezone (UTC in config/app.php).
     * No-op when DB_CONNECTION is not mysql. Dumps include audit tables (full schema).
     */
    'backup' => [
        'enabled' => filter_var(env('BACKUP_MYSQL_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'path' => env('BACKUP_PATH', storage_path('app/backups')),
        'retention_days' => max(1, (int) env('BACKUP_RETENTION_DAYS', 14)),
        'mysqldump_bin' => env('BACKUP_MYSQLDUMP_BIN', 'mysqldump'),
    ],

    /*
     * Optional one-destination pilot staff (least privilege). Empty values are skipped.
     * Never put Super Admin here — use SUPER_ADMIN_* only.
     */
    'pilot_staff' => [
        'admin' => [
            'role' => 'admin',
            'name' => env('PILOT_ADMIN_NAME'),
            'email' => env('PILOT_ADMIN_EMAIL'),
            'password' => env('PILOT_ADMIN_PASSWORD'),
        ],
        'manager' => [
            'role' => 'manager',
            'name' => env('PILOT_MANAGER_NAME'),
            'email' => env('PILOT_MANAGER_EMAIL'),
            'password' => env('PILOT_MANAGER_PASSWORD'),
        ],
        'finance' => [
            'role' => 'finance',
            'name' => env('PILOT_FINANCE_NAME'),
            'email' => env('PILOT_FINANCE_EMAIL'),
            'password' => env('PILOT_FINANCE_PASSWORD'),
        ],
        'ticket_officer' => [
            'role' => 'ticket_officer',
            'name' => env('PILOT_TICKET_OFFICER_NAME'),
            'email' => env('PILOT_TICKET_OFFICER_EMAIL'),
            'password' => env('PILOT_TICKET_OFFICER_PASSWORD'),
        ],
        'gate_officer' => [
            'role' => 'gate_officer',
            'name' => env('PILOT_GATE_OFFICER_NAME'),
            'email' => env('PILOT_GATE_OFFICER_EMAIL'),
            'password' => env('PILOT_GATE_OFFICER_PASSWORD'),
        ],
        'operator' => [
            'role' => 'operator',
            'name' => env('PILOT_OPERATOR_NAME'),
            'email' => env('PILOT_OPERATOR_EMAIL'),
            'password' => env('PILOT_OPERATOR_PASSWORD'),
        ],
        'auditor' => [
            'role' => 'auditor',
            'name' => env('PILOT_AUDITOR_NAME'),
            'email' => env('PILOT_AUDITOR_EMAIL'),
            'password' => env('PILOT_AUDITOR_PASSWORD'),
        ],
    ],
];

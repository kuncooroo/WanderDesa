<?php

namespace App\Support\Tickets;

use App\Models\TicketPrintLog;

final readonly class PrintAttemptRecord
{
    public function __construct(
        public TicketPrintLog $log,
        public string $auditAction,
    ) {}
}

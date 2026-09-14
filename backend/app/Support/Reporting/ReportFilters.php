<?php

namespace App\Support\Reporting;

final readonly class ReportFilters
{
    public function __construct(
        public ReportWindow $window,
        public ?int $destinationId = null,
        public ?string $paymentStatus = null,
        public ?int $cashierUserId = null,
    ) {}
}

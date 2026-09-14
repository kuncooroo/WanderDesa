<?php

namespace App\Livewire\Reports;

use App\Actions\Reporting\ExportDailySalesReport;
use App\Enums\PermissionName;
use App\Models\Destination;
use App\Models\Report;
use App\Models\User;
use App\Services\Reporting\ReportingQueryService;
use App\Support\Authorization\Authorizer;
use App\Support\Reporting\ReportFilters;
use App\Support\Reporting\ReportWindow;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * EOD/ops reports. Totals come from Laravel; this component only filters and renders.
 */
#[Layout('layouts.app')]
#[Title('Laporan')]
class ReportBoard extends Component
{
    use AuthorizesRequests;

    #[Url(as: 'view')]
    public string $report = 'sales';

    #[Url(as: 'from')]
    public string $fromDate = '';

    #[Url(as: 'to')]
    public string $toDate = '';

    #[Url(as: 'destination')]
    public ?int $destinationId = null;

    #[Url(as: 'cashier')]
    public ?int $cashierUserId = null;

    #[Url(as: 'status')]
    public string $paymentStatus = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Report::class);

        $today = $this->today();
        if ($this->fromDate === '') {
            $this->fromDate = $today;
        }
        if ($this->toDate === '') {
            $this->toDate = $today;
        }
    }

    public function updatedFromDate(): void
    {
        $this->normalizeRange();
    }

    public function updatedToDate(): void
    {
        $this->normalizeRange();
    }

    public function updatedDestinationId(): void
    {
        if ($this->destinationId === 0) {
            $this->destinationId = null;
        }
    }

    public function updatedCashierUserId(): void
    {
        if ($this->cashierUserId === 0) {
            $this->cashierUserId = null;
        }
    }

    public function showReport(string $report): void
    {
        $allowed = ['sales', 'by_product', 'by_cashier', 'payments', 'tickets', 'refunds', 'kiosks'];
        if (! in_array($report, $allowed, true)) {
            return;
        }

        $this->report = $report;
    }

    public function exportSales(ExportDailySalesReport $export)
    {
        $this->authorize('export', Report::class);

        $filters = $this->currentFilters();
        $result = $export->handle($this->staff(), $filters, request());

        return response()->streamDownload(
            static function () use ($result): void {
                echo $result['csv'];
            },
            $result['filename'],
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ],
        );
    }

    public function render(ReportingQueryService $reports)
    {
        $this->authorize('viewAny', Report::class);

        $filters = $this->currentFilters();
        $payload = match ($this->report) {
            'by_product' => $reports->salesByProduct($filters),
            'by_cashier' => $reports->salesByCashier($filters),
            'payments' => $reports->paymentsByStatus($filters),
            'tickets' => $reports->ticketUsage($filters),
            'refunds' => $reports->refundsAndCancels($filters),
            'kiosks' => $reports->kioskHealth($this->destinationId),
            default => $reports->dailySales($filters),
        };

        $showCashierFilter = in_array($this->report, ['sales', 'by_product', 'by_cashier'], true);
        $salesSummary = match ($this->report) {
            'sales' => $payload,
            'by_product', 'by_cashier' => $reports->dailySales($filters),
            default => null,
        };

        $paymentMethodChart = $this->report === 'payments'
            ? $reports->paymentsByInstrument($filters)
            : null;

        $productTopChart = $this->report === 'by_product'
            ? $reports->topProductsByQty($filters, 5)
            : null;

        return view('livewire.reports.report-board', [
            'payload' => $payload,
            'salesSummary' => $salesSummary,
            'paymentMethodChart' => $paymentMethodChart,
            'productTopChart' => $productTopChart,
            'destinations' => Destination::query()->orderBy('name')->get(['id', 'name', 'timezone']),
            'cashiers' => $showCashierFilter ? $reports->cashierOptions() : [],
            'showCashierFilter' => $showCashierFilter,
            'canExport' => Authorizer::check($this->staff(), PermissionName::ReportsExport),
            'timezone' => $filters->window->timezone,
        ]);
    }

    /**
     * Live chart refresh without full page reload (called from @script / Alpine).
     *
     * @return array{labels?: list<string>, series: list<int>, categories?: list<string>}
     */
    public function chartPayload(ReportingQueryService $reports): array
    {
        $this->authorize('viewAny', Report::class);
        $filters = $this->currentFilters();

        return match ($this->report) {
            'payments' => $reports->paymentsByInstrument($filters),
            'by_product' => $reports->topProductsByQty($filters, 5),
            default => ['labels' => [], 'categories' => [], 'series' => []],
        };
    }

    private function currentFilters(): ReportFilters
    {
        $from = $this->fromDate !== '' ? $this->fromDate : $this->today();
        $to = $this->toDate !== '' ? $this->toDate : $from;
        if ($to < $from) {
            $to = $from;
        }

        try {
            return app(ReportingQueryService::class)->filters(
                $from,
                $to,
                $this->destinationId ?: null,
                $this->paymentStatus !== '' ? $this->paymentStatus : null,
                $this->cashierUserId ?: null,
            );
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'toDate' => $e->getMessage(),
            ]);
        }
    }

    private function normalizeRange(): void
    {
        $today = $this->today();
        if ($this->fromDate === '') {
            $this->fromDate = $today;
        }
        if ($this->toDate === '') {
            $this->toDate = $this->fromDate;
        }
        if ($this->toDate < $this->fromDate) {
            $this->toDate = $this->fromDate;
        }

        try {
            ReportWindow::forRange(
                $this->fromDate,
                $this->toDate,
                app(ReportingQueryService::class)->timezoneFor($this->destinationId),
            );
        } catch (\InvalidArgumentException) {
            $this->toDate = $this->fromDate;
        }
    }

    private function today(): string
    {
        $tz = app(ReportingQueryService::class)->timezoneFor($this->destinationId);

        return CarbonImmutable::now($tz)->toDateString();
    }

    private function staff(): User
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            throw new AuthorizationException('Unauthenticated.');
        }

        return $user;
    }
}

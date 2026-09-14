<?php

namespace App\Livewire\Dashboard;

use App\Enums\PermissionName;
use App\Models\User;
use App\Services\Reporting\ReportingQueryService;
use App\Support\Authorization\Authorizer;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Component;

/**
 * Beranda line chart: daily gross revenue trend (server totals only).
 */
final class HomeRevenueChart extends Component
{
    public int $days = 7;

    public function mount(): void
    {
        $this->ensureCanView();
    }

    public function setDays(int $days): void
    {
        if (! in_array($days, [7, 30], true)) {
            return;
        }

        $this->days = $days;
    }

    /**
     * @return array{days: int, timezone: string, from: string, to: string, categories: list<string>, series: list<int>}
     */
    public function chartPayload(ReportingQueryService $reports): array
    {
        $this->ensureCanView();

        return $reports->dailyRevenueTrend($this->days);
    }

    public function render(ReportingQueryService $reports)
    {
        $this->ensureCanView();

        $trend = $reports->dailyRevenueTrend($this->days);

        return view('livewire.dashboard.home-revenue-chart', [
            'trend' => $trend,
        ]);
    }

    private function ensureCanView(): void
    {
        $user = $this->staff();

        if (! Authorizer::check($user, PermissionName::ReportsView)
            && ! Authorizer::check($user, PermissionName::AnalyticsView)) {
            throw new AuthorizationException('Unauthorized.');
        }
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

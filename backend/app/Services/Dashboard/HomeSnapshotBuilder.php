<?php

namespace App\Services\Dashboard;

use App\Enums\Channel;
use App\Enums\PaymentStatus;
use App\Enums\PermissionName;
use App\Enums\TicketValidateResult;
use App\Models\AuditLog;
use App\Models\CheckIn;
use App\Models\Device;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Reporting\ReportingQueryService;
use App\Support\Authorization\Authorizer;
use App\Support\Authorization\NavPermissionMap;
use App\Support\Reporting\ReportWindow;
use Illuminate\Support\Carbon;

/**
 * Role-aware home dashboard snapshot (docs/09 B2). Read-only aggregates.
 */
final class HomeSnapshotBuilder
{
    private const SENSITIVE_AUDIT_ACTIONS = [
        'refund.created',
        'roles.assign',
        'roles.revoke',
        'settings.updated',
        'device.disabled',
        'user.deleted',
        'user.upsert',
    ];

    private const PAYMENT_AGING_MINUTES = 15;

    public function __construct(
        private readonly ReportingQueryService $reporting,
    ) {}

    /**
     * @return array{
     *     date: string,
     *     timezone: string,
     *     shortcuts: list<array{label: string, url: string, primary: bool}>,
     *     widgets: list<array<string, mixed>>
     * }
     */
    public function build(User $user): array
    {
        $timezone = ReportWindow::DEFAULT_TIMEZONE;
        $today = Carbon::now($timezone)->toDateString();
        $window = ReportWindow::forDate($today, $timezone);

        $widgets = [];

        if (Authorizer::check($user, PermissionName::OrdersCreate)) {
            $widgets[] = $this->assistedSalesWidget($user, $window);
        }

        if (Authorizer::check($user, PermissionName::CheckinsCreate)
            || Authorizer::check($user, PermissionName::TicketsValidate)) {
            $widgets[] = $this->gateWidget($user, $window);
        }

        if (Authorizer::check($user, PermissionName::KiosksView)) {
            $widgets[] = $this->fleetWidget();
        }

        if (Authorizer::check($user, PermissionName::ReportsView)
            || Authorizer::check($user, PermissionName::AnalyticsView)) {
            $widgets[] = $this->salesTodayWidget($today);
        }

        if (Authorizer::check($user, PermissionName::PaymentsView)) {
            $widgets[] = $this->paymentsAgingWidget();
        }

        if (Authorizer::check($user, PermissionName::AuditLogsView)) {
            $widgets[] = $this->sensitiveAuditWidget();
        }

        return [
            'date' => $today,
            'timezone' => $timezone,
            'shortcuts' => $this->shortcuts($user),
            'widgets' => $widgets,
        ];
    }

    /**
     * @return list<array{label: string, url: string, primary: bool}>
     */
    private function shortcuts(User $user): array
    {
        $items = [];

        if (NavPermissionMap::canSee($user, 'assisted_sale')) {
            $items[] = [
                'label' => 'Mulai penjualan',
                'url' => route('dashboard.assisted-sale'),
                'primary' => true,
            ];
        }

        if (NavPermissionMap::canSee($user, 'checkin')) {
            $items[] = [
                'label' => 'Check-in gerbang',
                'url' => route('dashboard.check-in'),
                'primary' => true,
            ];
        }

        if (NavPermissionMap::canSee($user, 'payments')) {
            $items[] = [
                'label' => 'Pembayaran',
                'url' => route('dashboard.payments'),
                'primary' => false,
            ];
        }

        if (NavPermissionMap::canSee($user, 'kiosks')) {
            $items[] = [
                'label' => 'Kiosk',
                'url' => route('dashboard.kiosks'),
                'primary' => false,
            ];
        }

        if (NavPermissionMap::canSee($user, 'reports')) {
            $items[] = [
                'label' => 'Laporan',
                'url' => route('dashboard.reports'),
                'primary' => false,
            ];
        }

        if (NavPermissionMap::canSee($user, 'audit_logs')) {
            $items[] = [
                'label' => 'Audit logs',
                'url' => route('dashboard.audit-logs'),
                'primary' => false,
            ];
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function assistedSalesWidget(User $user, ReportWindow $window): array
    {
        $query = Order::query()
            ->where('channel', Channel::Assisted->value)
            ->whereBetween('created_at', [$window->startUtc, $window->endUtc]);

        // Officers see their own shift-ish count; broader roles see all assisted today.
        if (! Authorizer::check($user, PermissionName::ReportsView)
            && ! Authorizer::check($user, PermissionName::UsersView)) {
            $query->where('created_by_user_id', $user->id);
        }

        $total = (clone $query)->count();
        $paid = (clone $query)->whereNotNull('paid_at')->count();

        return [
            'type' => 'assisted_today',
            'title' => 'Penjualan dibantu hari ini',
            'empty' => $total === 0,
            'stats' => [
                ['label' => 'Pesanan', 'value' => $total],
                ['label' => 'Lunas', 'value' => $paid],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function gateWidget(User $user, ReportWindow $window): array
    {
        $allowsQuery = CheckIn::query()
            ->whereBetween('checked_in_at', [$window->startUtc, $window->endUtc]);

        if (Authorizer::check($user, PermissionName::CheckinsCreate)
            && ! Authorizer::check($user, PermissionName::ReportsView)
            && ! Authorizer::check($user, PermissionName::AuditLogsView)) {
            $allowsQuery->where('checked_in_by_user_id', $user->id);
        }

        $allows = $allowsQuery->count();

        $deniesQuery = AuditLog::query()
            ->where('action', 'ticket.validated')
            ->whereBetween('created_at', [$window->startUtc, $window->endUtc])
            ->where('after_json->result', TicketValidateResult::Deny->value);

        if (Authorizer::check($user, PermissionName::TicketsValidate)
            && ! Authorizer::check($user, PermissionName::ReportsView)
            && ! Authorizer::check($user, PermissionName::AuditLogsView)) {
            $deniesQuery->where('actor_type', 'user')->where('actor_id', $user->id);
        }

        $denies = $deniesQuery->count();

        return [
            'type' => 'gate_today',
            'title' => 'Gerbang hari ini',
            'empty' => $allows === 0 && $denies === 0,
            'stats' => [
                ['label' => 'Allow (check-in)', 'value' => $allows],
                ['label' => 'Deny (validasi)', 'value' => $denies],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fleetWidget(): array
    {
        $health = $this->reporting->kioskHealth();
        $maintenance = Device::query()->where('maintenance_mode', true)->count();

        return [
            'type' => 'fleet',
            'title' => 'Armada kiosk',
            'empty' => (int) $health['total'] === 0,
            'stats' => [
                ['label' => 'Online', 'value' => (int) $health['online_count']],
                ['label' => 'Offline', 'value' => (int) $health['offline_count']],
                ['label' => 'Maintenance', 'value' => $maintenance],
                ['label' => 'Total', 'value' => (int) $health['total']],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function salesTodayWidget(string $today): array
    {
        $filters = $this->reporting->filters($today, $today);
        $sales = $this->reporting->dailySales($filters);

        $channels = [];
        foreach ($sales['channels'] as $row) {
            $channels[] = [
                'channel' => $row['channel'],
                'order_count' => $row['order_count'],
                'gross_sales' => $row['gross_sales'],
            ];
        }

        $totalOrders = (int) $sales['totals']['order_count'];
        $gross = (int) $sales['totals']['gross_sales'];

        return [
            'type' => 'sales_today',
            'title' => 'Penjualan hari ini',
            'empty' => $totalOrders === 0,
            'currency' => 'IDR',
            'stats' => [
                ['label' => 'Order lunas', 'value' => $totalOrders],
                ['label' => 'Gross', 'value' => $gross, 'money' => true],
            ],
            'channels' => $channels,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentsAgingWidget(): array
    {
        $open = Payment::query()
            ->whereIn('status', [PaymentStatus::Pending->value, PaymentStatus::Processing->value])
            ->count();

        $aging = Payment::query()
            ->whereIn('status', [PaymentStatus::Pending->value, PaymentStatus::Processing->value])
            ->where('created_at', '<=', now()->subMinutes(self::PAYMENT_AGING_MINUTES))
            ->count();

        return [
            'type' => 'payments_aging',
            'title' => 'Pembayaran terbuka',
            'empty' => $open === 0,
            'stats' => [
                ['label' => 'Pending/processing', 'value' => $open],
                ['label' => 'Aging >'.self::PAYMENT_AGING_MINUTES.' mnt', 'value' => $aging],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sensitiveAuditWidget(): array
    {
        $rows = AuditLog::query()
            ->whereIn('action', self::SENSITIVE_AUDIT_ACTIONS)
            ->orderByDesc('id')
            ->limit(8)
            ->get(['id', 'action', 'actor_type', 'actor_id', 'entity_type', 'entity_id', 'created_at']);

        return [
            'type' => 'sensitive_audit',
            'title' => 'Audit sensitif terbaru',
            'empty' => $rows->isEmpty(),
            'rows' => $rows->map(static fn (AuditLog $log): array => [
                'id' => $log->id,
                'action' => $log->action,
                'actor' => $log->actor_type.($log->actor_id ? ':'.$log->actor_id : ''),
                'entity' => $log->entity_type
                    ? $log->entity_type.($log->entity_id ? '#'.$log->entity_id : '')
                    : '—',
                'at' => $log->created_at?->timezone(ReportWindow::DEFAULT_TIMEZONE)?->toDateTimeString(),
            ])->all(),
        ];
    }
}

<?php

namespace App\Services\Reporting;

use App\Enums\Channel;
use App\Enums\DeviceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\TicketStatus;
use App\Models\Destination;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Ticket;
use App\Support\ApiTimestamp;
use App\Support\Reporting\ReportFilters;
use App\Support\Reporting\ReportWindow;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Read-only operational/finance aggregates from authoritative MySQL status columns.
 */
final class ReportingQueryService
{
    public function filters(
        string $from,
        string $to,
        ?int $destinationId = null,
        ?string $paymentStatus = null,
        ?int $cashierUserId = null,
    ): ReportFilters {
        return new ReportFilters(
            window: ReportWindow::forRange($from, $to, $this->timezoneFor($destinationId)),
            destinationId: $destinationId,
            paymentStatus: $paymentStatus,
            cashierUserId: $cashierUserId,
        );
    }

    public function timezoneFor(?int $destinationId): string
    {
        if ($destinationId === null) {
            return ReportWindow::DEFAULT_TIMEZONE;
        }

        $timezone = Destination::query()->whereKey($destinationId)->value('timezone');

        return is_string($timezone) && $timezone !== ''
            ? $timezone
            : ReportWindow::DEFAULT_TIMEZONE;
    }

    /**
     * Gross sales: orders with paid_at in the local window (including later refunds).
     * Net Sales = Gross − Discount − Refund (server-owned IDR integers).
     *
     * @return array<string, mixed>
     */
    public function dailySales(ReportFilters $filters): array
    {
        $window = $filters->window;

        $rows = DB::table('orders')
            ->selectRaw(
                'channel, COUNT(*) as order_count, COALESCE(SUM(grand_total), 0) as gross_sales, COALESCE(SUM(discount_total), 0) as discount_total',
            )
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$window->startUtc, $window->endUtc])
            ->when($filters->destinationId !== null, fn ($q) => $q->where('destination_id', $filters->destinationId))
            ->tap(fn ($q) => $this->applyCashierToOrders($q, $filters))
            ->groupBy('channel')
            ->get()
            ->keyBy('channel');

        $channels = [];
        $totalCount = 0;
        $totalSales = 0;
        $totalDiscount = 0;

        foreach (Channel::cases() as $channel) {
            $row = $rows->get($channel->value);
            $count = (int) ($row->order_count ?? 0);
            $sales = (int) ($row->gross_sales ?? 0);
            $discount = (int) ($row->discount_total ?? 0);
            $channels[] = [
                'channel' => $channel->value,
                'order_count' => $count,
                'gross_sales' => $sales,
                'discount_total' => $discount,
            ];
            $totalCount += $count;
            $totalSales += $sales;
            $totalDiscount += $discount;
        }

        $refundTotal = $this->refundTotal($filters);
        $netSales = $totalSales - $totalDiscount - $refundTotal;

        return [
            ...$this->windowMeta($filters),
            'currency' => 'IDR',
            'channels' => $channels,
            'totals' => [
                'order_count' => $totalCount,
                'gross_sales' => $totalSales,
                'discount_total' => $totalDiscount,
                'refund_total' => $refundTotal,
                'net_sales' => $netSales,
            ],
        ];
    }

    /**
     * Paid order items grouped by ticket type / product.
     *
     * @return array<string, mixed>
     */
    public function salesByProduct(ReportFilters $filters): array
    {
        $window = $filters->window;

        $rows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->selectRaw(
                'order_items.ticket_type_id,
                order_items.ticket_type_code,
                order_items.ticket_type_name,
                COALESCE(SUM(order_items.quantity), 0) as qty_sold,
                COALESCE(SUM(order_items.line_grand_total), 0) as omzet',
            )
            ->whereNotNull('orders.paid_at')
            ->whereBetween('orders.paid_at', [$window->startUtc, $window->endUtc])
            ->when($filters->destinationId !== null, fn ($q) => $q->where('orders.destination_id', $filters->destinationId))
            ->tap(fn ($q) => $this->applyCashierToOrders($q, $filters, 'orders'))
            ->groupBy('order_items.ticket_type_id', 'order_items.ticket_type_code', 'order_items.ticket_type_name')
            ->orderByDesc('omzet')
            ->get();

        $products = [];
        $qtyTotal = 0;
        $omzetTotal = 0;

        foreach ($rows as $row) {
            $qty = (int) $row->qty_sold;
            $omzet = (int) $row->omzet;
            $products[] = [
                'ticket_type_id' => (int) $row->ticket_type_id,
                'ticket_type_code' => (string) $row->ticket_type_code,
                'product_name' => (string) $row->ticket_type_name,
                'qty_sold' => $qty,
                'omzet' => $omzet,
            ];
            $qtyTotal += $qty;
            $omzetTotal += $omzet;
        }

        return [
            ...$this->windowMeta($filters),
            'currency' => 'IDR',
            'rows' => $products,
            'totals' => [
                'qty_sold' => $qtyTotal,
                'omzet' => $omzetTotal,
            ],
        ];
    }

    /**
     * Top N products by units sold (for charting). Server-owned qty only.
     *
     * @return array{categories: list<string>, series: list<int>, rows: list<array<string, mixed>>}
     */
    public function topProductsByQty(ReportFilters $filters, int $limit = 5): array
    {
        $limit = max(1, min(20, $limit));
        $payload = $this->salesByProduct($filters);

        $rows = $payload['rows'];
        usort($rows, static fn (array $a, array $b): int => $b['qty_sold'] <=> $a['qty_sold']);
        $rows = array_slice($rows, 0, $limit);

        return [
            'categories' => array_map(static fn (array $row): string => $row['product_name'], $rows),
            'series' => array_map(static fn (array $row): int => (int) $row['qty_sold'], $rows),
            'rows' => $rows,
        ];
    }

    /**
     * Daily gross revenue trend for the last N local calendar days (inclusive of today).
     *
     * @return array{days: int, timezone: string, from: string, to: string, categories: list<string>, series: list<int>}
     */
    public function dailyRevenueTrend(int $days = 7, ?int $destinationId = null): array
    {
        $days = in_array($days, [7, 30], true) ? $days : 7;
        $timezone = $this->timezoneFor($destinationId);
        $toLocal = CarbonImmutable::now($timezone)->toDateString();
        $fromLocal = CarbonImmutable::now($timezone)->subDays($days - 1)->toDateString();
        $filters = $this->filters($fromLocal, $toLocal, $destinationId);
        $window = $filters->window;

        $orders = DB::table('orders')
            ->select(['paid_at', 'grand_total'])
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$window->startUtc, $window->endUtc])
            ->when(
                $filters->destinationId !== null,
                fn ($q) => $q->where('destination_id', $filters->destinationId),
            )
            ->get();

        $byDate = [];
        foreach ($orders as $order) {
            $localDate = CarbonImmutable::parse($order->paid_at, 'UTC')
                ->timezone($timezone)
                ->toDateString();
            $byDate[$localDate] = ($byDate[$localDate] ?? 0) + (int) $order->grand_total;
        }

        $categories = [];
        $series = [];
        $cursor = CarbonImmutable::createFromFormat('Y-m-d', $fromLocal, $timezone)->startOfDay();
        $end = CarbonImmutable::createFromFormat('Y-m-d', $toLocal, $timezone)->startOfDay();

        while ($cursor->lte($end)) {
            $date = $cursor->toDateString();
            $categories[] = $date;
            $series[] = (int) ($byDate[$date] ?? 0);
            $cursor = $cursor->addDay();
        }

        return [
            'days' => $days,
            'timezone' => $timezone,
            'from' => $fromLocal,
            'to' => $toLocal,
            'categories' => $categories,
            'series' => $series,
        ];
    }

    /**
     * Payment method mix for charts (authoritative PaymentMethod enum buckets).
     *
     * @return array{labels: list<string>, series: list<int>, rows: list<array{label: string, method: string, payment_count: int, amount_total: int}>}
     */
    public function paymentsByInstrument(ReportFilters $filters): array
    {
        $window = $filters->window;

        $rows = DB::table('payments')
            ->selectRaw('payments.method, COUNT(*) as payment_count, COALESCE(SUM(payments.amount), 0) as amount_total')
            ->when(
                $filters->destinationId !== null,
                fn ($q) => $q->join('orders', 'orders.id', '=', 'payments.order_id')
                    ->where('orders.destination_id', $filters->destinationId),
            )
            ->whereBetween('payments.created_at', [$window->startUtc, $window->endUtc])
            ->when(
                $filters->paymentStatus !== null,
                fn ($q) => $q->where('payments.status', $filters->paymentStatus),
            )
            ->groupBy('payments.method')
            ->get()
            ->keyBy('method');

        $outRows = [];
        $labels = [];
        $series = [];

        foreach (PaymentMethod::cases() as $method) {
            $row = $rows->get($method->value);
            $count = (int) ($row->payment_count ?? 0);
            $amount = (int) ($row->amount_total ?? 0);
            $outRows[] = [
                'label' => $method->label(),
                'method' => $method->value,
                'payment_count' => $count,
                'amount_total' => $amount,
            ];
            $labels[] = $method->label();
            $series[] = $count;
        }

        return [
            ...$this->windowMeta($filters),
            'currency' => 'IDR',
            'labels' => $labels,
            'series' => $series,
            'rows' => $outRows,
        ];
    }

    /**
     * Paid sales attributed to cashiers + closed-shift cash difference summary.
     *
     * @return array<string, mixed>
     */
    public function salesByCashier(ReportFilters $filters): array
    {
        $window = $filters->window;

        $salesRows = DB::query()
            ->fromSub(function ($query) use ($window, $filters): void {
                $query->from('orders')
                    ->selectRaw(
                        'orders.id,
                        orders.grand_total,
                        (
                            SELECT payments.collected_by_user_id
                            FROM payments
                            WHERE payments.order_id = orders.id
                              AND payments.collected_by_user_id IS NOT NULL
                            ORDER BY payments.paid_at IS NULL, payments.paid_at DESC, payments.id DESC
                            LIMIT 1
                        ) as collected_by_user_id,
                        orders.created_by_user_id',
                    )
                    ->whereNotNull('orders.paid_at')
                    ->whereBetween('orders.paid_at', [$window->startUtc, $window->endUtc])
                    ->when($filters->destinationId !== null, fn ($q) => $q->where('orders.destination_id', $filters->destinationId));
            }, 'paid_orders')
            ->selectRaw(
                'COALESCE(collected_by_user_id, created_by_user_id) as cashier_user_id,
                COUNT(*) as transaction_count,
                COALESCE(SUM(grand_total), 0) as omzet',
            )
            ->whereRaw('COALESCE(collected_by_user_id, created_by_user_id) IS NOT NULL')
            ->when(
                $filters->cashierUserId !== null,
                fn ($q) => $q->whereRaw(
                    'COALESCE(collected_by_user_id, created_by_user_id) = ?',
                    [$filters->cashierUserId],
                ),
            )
            ->groupByRaw('COALESCE(collected_by_user_id, created_by_user_id)')
            ->get();

        $shiftDiffs = DB::table('cashier_shifts')
            ->selectRaw('user_id, COALESCE(SUM(difference), 0) as difference_total, COUNT(*) as closed_shift_count')
            ->where('status', 'closed')
            ->whereNotNull('closed_at')
            ->whereBetween('closed_at', [$window->startUtc, $window->endUtc])
            ->when($filters->cashierUserId !== null, fn ($q) => $q->where('user_id', $filters->cashierUserId))
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $byCashier = [];
        foreach ($salesRows as $row) {
            $byCashier[(int) $row->cashier_user_id] = [
                'transaction_count' => (int) $row->transaction_count,
                'omzet' => (int) $row->omzet,
            ];
        }

        $allIds = collect(array_keys($byCashier))
            ->merge($shiftDiffs->keys()->map(fn ($id) => (int) $id))
            ->unique()
            ->sort()
            ->values();

        $names = DB::table('users')
            ->whereIn('id', $allIds->all())
            ->pluck('name', 'id');

        $out = [];
        $txTotal = 0;
        $omzetTotal = 0;
        $diffTotal = 0;

        foreach ($allIds as $cashierId) {
            $sales = $byCashier[$cashierId] ?? ['transaction_count' => 0, 'omzet' => 0];
            $shift = $shiftDiffs->get($cashierId);
            $difference = (int) ($shift->difference_total ?? 0);
            $closedShifts = (int) ($shift->closed_shift_count ?? 0);

            $out[] = [
                'cashier_user_id' => $cashierId,
                'cashier_name' => (string) ($names[$cashierId] ?? 'Pengguna #'.$cashierId),
                'transaction_count' => $sales['transaction_count'],
                'omzet' => $sales['omzet'],
                'shift_difference_total' => $difference,
                'closed_shift_count' => $closedShifts,
            ];
            $txTotal += $sales['transaction_count'];
            $omzetTotal += $sales['omzet'];
            $diffTotal += $difference;
        }

        usort($out, static fn (array $a, array $b): int => $b['omzet'] <=> $a['omzet']);

        return [
            ...$this->windowMeta($filters),
            'currency' => 'IDR',
            'rows' => $out,
            'totals' => [
                'transaction_count' => $txTotal,
                'omzet' => $omzetTotal,
                'shift_difference_total' => $diffTotal,
            ],
        ];
    }

    /**
     * Staff users eligible as cashier filter options.
     *
     * @return list<array{id: int, name: string}>
     */
    public function cashierOptions(): array
    {
        return DB::table('users')
            ->select('users.id', 'users.name')
            ->whereNull('users.deleted_at')
            ->where(function ($q): void {
                $q->whereExists(function ($sub): void {
                    $sub->selectRaw('1')
                        ->from('payments')
                        ->whereColumn('payments.collected_by_user_id', 'users.id');
                })->orWhereExists(function ($sub): void {
                    $sub->selectRaw('1')
                        ->from('orders')
                        ->whereColumn('orders.created_by_user_id', 'users.id');
                })->orWhereExists(function ($sub): void {
                    $sub->selectRaw('1')
                        ->from('cashier_shifts')
                        ->whereColumn('cashier_shifts.user_id', 'users.id');
                })->orWhereExists(function ($sub): void {
                    $sub->selectRaw('1')
                        ->from('role_user')
                        ->join('roles', 'roles.id', '=', 'role_user.role_id')
                        ->whereColumn('role_user.user_id', 'users.id')
                        ->where('roles.name', 'ticket_officer');
                });
            })
            ->orderBy('users.name')
            ->get()
            ->map(static fn ($row): array => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
            ])
            ->all();
    }

    /**
     * Payments grouped by current status, windowed on created_at.
     *
     * @return array<string, mixed>
     */
    public function paymentsByStatus(ReportFilters $filters): array
    {
        $window = $filters->window;

        $query = DB::table('payments')
            ->selectRaw('payments.status, COUNT(*) as payment_count, COALESCE(SUM(payments.amount), 0) as amount_total')
            ->when(
                $filters->destinationId !== null,
                fn ($q) => $q->join('orders', 'orders.id', '=', 'payments.order_id')
                    ->where('orders.destination_id', $filters->destinationId),
            )
            ->whereBetween('payments.created_at', [$window->startUtc, $window->endUtc])
            ->when(
                $filters->paymentStatus !== null,
                fn ($q) => $q->where('payments.status', $filters->paymentStatus),
            )
            ->groupBy('payments.status');

        $rows = $query->get()->keyBy('status');

        $statuses = $filters->paymentStatus !== null
            ? [PaymentStatus::from($filters->paymentStatus)]
            : PaymentStatus::cases();

        $out = [];
        $totalCount = 0;
        $totalAmount = 0;

        foreach ($statuses as $status) {
            $row = $rows->get($status->value);
            $count = (int) ($row->payment_count ?? 0);
            $amount = (int) ($row->amount_total ?? 0);
            $out[] = [
                'status' => $status->value,
                'payment_count' => $count,
                'amount_total' => $amount,
            ];
            $totalCount += $count;
            $totalAmount += $amount;
        }

        return [
            ...$this->windowMeta($filters),
            'currency' => 'IDR',
            'status_filter' => $filters->paymentStatus,
            'rows' => $out,
            'totals' => [
                'payment_count' => $totalCount,
                'amount_total' => $totalAmount,
            ],
        ];
    }

    /**
     * Ticket activity by event timestamps (issued_at / used_at / cancelled_at / refunded_at).
     *
     * @return array<string, mixed>
     */
    public function ticketUsage(ReportFilters $filters): array
    {
        $window = $filters->window;

        $base = fn () => Ticket::query()
            ->when($filters->destinationId !== null, fn ($q) => $q->where('destination_id', $filters->destinationId));

        $issuedCount = (clone $base())->whereBetween('issued_at', [$window->startUtc, $window->endUtc])->count();
        $usedCount = (clone $base())->whereBetween('used_at', [$window->startUtc, $window->endUtc])->count();
        $cancelledCount = (clone $base())->whereBetween('cancelled_at', [$window->startUtc, $window->endUtc])->count();
        $refundedCount = (clone $base())->whereBetween('refunded_at', [$window->startUtc, $window->endUtc])->count();

        $statusRows = DB::table('tickets')
            ->selectRaw('status, COUNT(*) as ticket_count')
            ->whereBetween('issued_at', [$window->startUtc, $window->endUtc])
            ->when($filters->destinationId !== null, fn ($q) => $q->where('destination_id', $filters->destinationId))
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $byStatus = [];
        foreach (TicketStatus::cases() as $status) {
            $byStatus[] = [
                'status' => $status->value,
                'ticket_count' => (int) ($statusRows->get($status->value)->ticket_count ?? 0),
            ];
        }

        return [
            ...$this->windowMeta($filters),
            'issued_count' => $issuedCount,
            'used_count' => $usedCount,
            'cancelled_count' => $cancelledCount,
            'refunded_count' => $refundedCount,
            'by_status' => $byStatus,
        ];
    }

    /**
     * Cancelled/refunded orders and refunded payments in the window. No visitor PII.
     *
     * @return array<string, mixed>
     */
    public function refundsAndCancels(ReportFilters $filters): array
    {
        $window = $filters->window;

        $cancelled = Order::query()
            ->with('destination:id,name')
            ->whereNotNull('cancelled_at')
            ->whereBetween('cancelled_at', [$window->startUtc, $window->endUtc])
            ->when($filters->destinationId !== null, fn ($q) => $q->where('destination_id', $filters->destinationId))
            ->orderByDesc('cancelled_at')
            ->limit(200)
            ->get();

        $refundedOrders = Order::query()
            ->with('destination:id,name')
            ->whereNotNull('refunded_at')
            ->whereBetween('refunded_at', [$window->startUtc, $window->endUtc])
            ->when($filters->destinationId !== null, fn ($q) => $q->where('destination_id', $filters->destinationId))
            ->orderByDesc('refunded_at')
            ->limit(200)
            ->get();

        $refundedPayments = Payment::query()
            ->with(['order.destination:id,name'])
            ->whereNotNull('refunded_at')
            ->whereBetween('refunded_at', [$window->startUtc, $window->endUtc])
            ->when(
                $filters->destinationId !== null,
                fn ($q) => $q->whereHas('order', fn ($order) => $order->where('destination_id', $filters->destinationId)),
            )
            ->orderByDesc('refunded_at')
            ->limit(200)
            ->get();

        $rows = [];

        foreach ($cancelled as $order) {
            $rows[] = $this->orderEventRow($order, 'order_cancelled', $order->cancelled_at);
        }
        foreach ($refundedOrders as $order) {
            $rows[] = $this->orderEventRow($order, 'order_refunded', $order->refunded_at);
        }
        foreach ($refundedPayments as $payment) {
            $order = $payment->order;
            $channel = $order?->channel;
            $rows[] = [
                'kind' => 'payment_refunded',
                'order_number' => $order?->order_number,
                'payment_number' => $payment->payment_number,
                'channel' => $channel instanceof Channel ? $channel->value : $channel,
                'amount' => (int) $payment->amount,
                'currency' => $payment->currency,
                'status' => $payment->status instanceof PaymentStatus
                    ? $payment->status->value
                    : (string) $payment->status,
                'occurred_at' => ApiTimestamp::utc($payment->refunded_at),
                'destination_name' => $order?->destination?->name,
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            return strcmp((string) ($b['occurred_at'] ?? ''), (string) ($a['occurred_at'] ?? ''));
        });

        return [
            ...$this->windowMeta($filters),
            'currency' => 'IDR',
            'rows' => $rows,
        ];
    }

    /**
     * Current kiosk fleet snapshot (not a historical time series).
     *
     * @return array<string, mixed>
     */
    public function kioskHealth(?int $destinationId = null): array
    {
        $staleSeconds = Setting::heartbeatStaleSeconds();
        $threshold = now()->subSeconds($staleSeconds);

        $query = DB::table('devices')
            ->when($destinationId !== null, fn ($q) => $q->where('destination_id', $destinationId));

        $statusRows = (clone $query)
            ->selectRaw('status, COUNT(*) as device_count')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $byStatus = [];
        foreach (DeviceStatus::cases() as $status) {
            $row = $statusRows->get($status->value);
            $byStatus[] = [
                'status' => $status->value,
                'device_count' => (int) ($row->device_count ?? 0),
            ];
        }

        $total = (clone $query)->count();
        $online = (clone $query)->where('last_heartbeat_at', '>', $threshold)->count();

        return [
            'destination_id' => $destinationId,
            'stale_after_seconds' => $staleSeconds,
            'by_status' => $byStatus,
            'online_count' => $online,
            'offline_count' => max(0, $total - $online),
            'total' => $total,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function windowMeta(ReportFilters $filters): array
    {
        $window = $filters->window;

        return [
            'from' => $window->fromLocal,
            'to' => $window->toLocal,
            'timezone' => $window->timezone,
            'destination_id' => $filters->destinationId,
            'cashier_user_id' => $filters->cashierUserId,
            'range_start_utc' => ApiTimestamp::utc($window->startUtc),
            'range_end_utc' => ApiTimestamp::utc($window->endUtc),
        ];
    }

    private function refundTotal(ReportFilters $filters): int
    {
        $window = $filters->window;

        return (int) DB::table('payments')
            ->when(
                $filters->destinationId !== null || $filters->cashierUserId !== null,
                fn ($q) => $q->join('orders', 'orders.id', '=', 'payments.order_id'),
            )
            ->whereNotNull('payments.refunded_at')
            ->whereBetween('payments.refunded_at', [$window->startUtc, $window->endUtc])
            ->when(
                $filters->destinationId !== null,
                fn ($q) => $q->where('orders.destination_id', $filters->destinationId),
            )
            ->when(
                $filters->cashierUserId !== null,
                function ($q) use ($filters): void {
                    $q->where(function ($inner) use ($filters): void {
                        $inner->where('payments.collected_by_user_id', $filters->cashierUserId)
                            ->orWhere('orders.created_by_user_id', $filters->cashierUserId)
                            ->orWhereExists(function ($shift) use ($filters): void {
                                $shift->selectRaw('1')
                                    ->from('cashier_shifts')
                                    ->whereColumn('cashier_shifts.id', 'payments.refund_cashier_shift_id')
                                    ->where('cashier_shifts.user_id', $filters->cashierUserId);
                            });
                    });
                },
            )
            ->sum('payments.amount');
    }

    /**
     * @param  Builder|\Illuminate\Database\Eloquent\Builder<Model>  $query
     */
    private function applyCashierToOrders(mixed $query, ReportFilters $filters, string $ordersTable = 'orders'): void
    {
        if ($filters->cashierUserId === null) {
            return;
        }

        $cashierId = $filters->cashierUserId;

        $query->where(function ($inner) use ($ordersTable, $cashierId): void {
            $inner->where("{$ordersTable}.created_by_user_id", $cashierId)
                ->orWhereExists(function ($payments) use ($ordersTable, $cashierId): void {
                    $payments->selectRaw('1')
                        ->from('payments')
                        ->whereColumn('payments.order_id', "{$ordersTable}.id")
                        ->where('payments.collected_by_user_id', $cashierId);
                });
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function orderEventRow(Order $order, string $kind, mixed $occurredAt): array
    {
        $channel = $order->channel;

        return [
            'kind' => $kind,
            'order_number' => $order->order_number,
            'payment_number' => null,
            'channel' => $channel instanceof Channel ? $channel->value : $channel,
            'amount' => (int) $order->grand_total,
            'currency' => $order->currency,
            'status' => $order->status?->value ?? (string) $order->status,
            'occurred_at' => ApiTimestamp::utc($occurredAt),
            'destination_name' => $order->destination?->name,
        ];
    }
}

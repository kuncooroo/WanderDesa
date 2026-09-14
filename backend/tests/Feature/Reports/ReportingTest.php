<?php

namespace Tests\Feature\Reports;

use App\Enums\CashierShiftStatus;
use App\Enums\Channel;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Enums\TicketStatus;
use App\Livewire\Reports\ReportBoard;
use App\Models\CashierShift;
use App\Models\Destination;
use App\Models\Device;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Report;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Services\Reporting\ReportingQueryService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithOrders;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class ReportingTest extends TestCase
{
    use InteractsWithOrders;
    use InteractsWithRbac;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_daily_sales_match_fixture_totals_and_respect_jakarta_day(): void
    {
        $destination = Destination::factory()->create(['timezone' => 'Asia/Jakarta']);

        $inDay = CarbonImmutable::parse('2026-09-14 00:30:00', 'Asia/Jakarta');
        $previousLocalDay = CarbonImmutable::parse('2026-09-13 23:30:00', 'Asia/Jakarta');

        $this->paidOrder($destination, Channel::Kiosk, 50000, $inDay);
        $this->paidOrder($destination, Channel::Kiosk, 50000, $inDay);
        $this->paidOrder($destination, Channel::Assisted, 75000, $inDay);
        $this->paidOrder($destination, Channel::Kiosk, 99999, $previousLocalDay);
        Order::factory()->create([
            'destination_id' => $destination->id,
            'channel' => Channel::Kiosk,
            'status' => OrderStatus::PendingPayment,
            'grand_total' => 100000,
            'paid_at' => null,
        ]);

        $filters = app(ReportingQueryService::class)->filters('2026-09-14', '2026-09-14', $destination->id);
        $sales = app(ReportingQueryService::class)->dailySales($filters);

        $this->assertSame(175000, $sales['totals']['gross_sales']);
        $this->assertSame(0, $sales['totals']['discount_total']);
        $this->assertSame(0, $sales['totals']['refund_total']);
        $this->assertSame(175000, $sales['totals']['net_sales']);
        $this->assertSame(3, $sales['totals']['order_count']);
        $this->assertSame(100000, $sales['channels'][0]['gross_sales']);
        $this->assertSame('kiosk', $sales['channels'][0]['channel']);
        $this->assertSame(75000, $sales['channels'][1]['gross_sales']);
        $this->assertSame('assisted', $sales['channels'][1]['channel']);
    }

    public function test_net_sales_subtracts_discount_and_refund(): void
    {
        $destination = Destination::factory()->create(['timezone' => 'Asia/Jakarta']);
        $at = CarbonImmutable::parse('2026-09-14 12:00:00', 'Asia/Jakarta');

        Order::factory()->paid()->create([
            'destination_id' => $destination->id,
            'channel' => Channel::Assisted,
            'grand_total' => 90000,
            'discount_total' => 10000,
            'subtotal' => 100000,
            'paid_at' => $at->utc(),
        ]);

        $refunded = Order::factory()->refunded()->create([
            'destination_id' => $destination->id,
            'channel' => Channel::Kiosk,
            'grand_total' => 40000,
            'discount_total' => 0,
            'paid_at' => $at->utc()->subHour(),
            'refunded_at' => $at->utc(),
        ]);
        Payment::factory()->refunded()->create([
            'order_id' => $refunded->id,
            'amount' => 40000,
            'refunded_at' => $at->utc(),
        ]);

        $filters = app(ReportingQueryService::class)->filters('2026-09-14', '2026-09-14', $destination->id);
        $sales = app(ReportingQueryService::class)->dailySales($filters);

        // Gross includes both paid-day orders (90000 + 40000 that was paid earlier same day? paid_at is subHour still same day)
        $this->assertSame(130000, $sales['totals']['gross_sales']);
        $this->assertSame(10000, $sales['totals']['discount_total']);
        $this->assertSame(40000, $sales['totals']['refund_total']);
        $this->assertSame(80000, $sales['totals']['net_sales']);
    }

    public function test_sales_by_product_and_cashier_breakdown(): void
    {
        $destination = Destination::factory()->create(['timezone' => 'Asia/Jakarta']);
        $cashier = $this->userWithRole(RoleName::TicketOfficer);
        $at = CarbonImmutable::parse('2026-09-14 09:00:00', 'Asia/Jakarta');

        $type = TicketType::factory()->create([
            'destination_id' => $destination->id,
            'code' => 'ADULT',
            'name' => 'Dewasa',
            'unit_price' => 50000,
        ]);

        $order = Order::factory()->paid()->create([
            'destination_id' => $destination->id,
            'channel' => Channel::Assisted,
            'created_by_user_id' => $cashier->id,
            'grand_total' => 100000,
            'paid_at' => $at->utc(),
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'ticket_type_id' => $type->id,
            'ticket_type_code' => 'ADULT',
            'ticket_type_name' => 'Dewasa',
            'quantity' => 2,
            'line_grand_total' => 100000,
        ]);
        Payment::factory()->paid()->create([
            'order_id' => $order->id,
            'amount' => 100000,
            'collected_by_user_id' => $cashier->id,
            'paid_at' => $at->utc(),
        ]);

        CashierShift::query()->create([
            'user_id' => $cashier->id,
            'opened_at' => $at->utc()->subHours(2),
            'closed_at' => $at->utc()->addHours(2),
            'initial_cash' => 100000,
            'total_cash_sales' => 100000,
            'total_cash_refund' => 0,
            'expected_cash' => 200000,
            'actual_cash' => 200000,
            'difference' => 0,
            'status' => CashierShiftStatus::Closed,
            'notes' => null,
        ]);

        $filters = app(ReportingQueryService::class)->filters('2026-09-14', '2026-09-14', $destination->id);
        $products = app(ReportingQueryService::class)->salesByProduct($filters);
        $cashiers = app(ReportingQueryService::class)->salesByCashier($filters);

        $this->assertSame(2, $products['totals']['qty_sold']);
        $this->assertSame(100000, $products['totals']['omzet']);
        $this->assertSame('Dewasa', $products['rows'][0]['product_name']);

        $row = collect($cashiers['rows'])->firstWhere('cashier_user_id', $cashier->id);
        $this->assertNotNull($row);
        $this->assertSame(1, $row['transaction_count']);
        $this->assertSame(100000, $row['omzet']);
        $this->assertSame(0, $row['shift_difference_total']);
        $this->assertSame(1, $row['closed_shift_count']);
    }

    public function test_payments_tickets_and_refunds_use_status_columns(): void
    {
        $destination = Destination::factory()->create(['timezone' => 'Asia/Jakarta']);
        $at = CarbonImmutable::parse('2026-09-14 10:00:00', 'Asia/Jakarta');

        $paid = $this->paidOrder($destination, Channel::Kiosk, 50000, $at);
        Payment::factory()->paid()->create([
            'order_id' => $paid->id,
            'amount' => 50000,
            'created_at' => $at->utc(),
            'paid_at' => $at->utc(),
        ]);
        Payment::factory()->failed()->create([
            'order_id' => $paid->id,
            'amount' => 50000,
            'created_at' => $at->utc(),
        ]);

        $type = TicketType::factory()->create(['destination_id' => $destination->id]);
        $item = OrderItem::factory()->create([
            'order_id' => $paid->id,
            'ticket_type_id' => $type->id,
        ]);
        Ticket::factory()->create([
            'order_id' => $paid->id,
            'order_item_id' => $item->id,
            'destination_id' => $destination->id,
            'ticket_type_id' => $type->id,
            'status' => TicketStatus::Issued,
            'issued_at' => $at->utc(),
        ]);
        Ticket::factory()->used()->create([
            'order_id' => $paid->id,
            'order_item_id' => $item->id,
            'destination_id' => $destination->id,
            'ticket_type_id' => $type->id,
            'issued_at' => $at->utc(),
            'used_at' => $at->utc(),
        ]);
        Ticket::factory()->cancelled()->create([
            'order_id' => $paid->id,
            'order_item_id' => $item->id,
            'destination_id' => $destination->id,
            'ticket_type_id' => $type->id,
            'issued_at' => $at->utc(),
            'cancelled_at' => $at->utc(),
        ]);

        $cancelled = Order::factory()->cancelled()->create([
            'destination_id' => $destination->id,
            'grand_total' => 25000,
            'cancelled_at' => $at->utc(),
        ]);
        $refundedOrder = Order::factory()->refunded()->create([
            'destination_id' => $destination->id,
            'grand_total' => 40000,
            'paid_at' => $at->utc()->subHour(),
            'refunded_at' => $at->utc(),
        ]);
        Payment::factory()->refunded()->create([
            'order_id' => $refundedOrder->id,
            'amount' => 40000,
            'created_at' => $at->utc()->subHour(),
            'refunded_at' => $at->utc(),
        ]);

        $filters = app(ReportingQueryService::class)->filters('2026-09-14', '2026-09-14', $destination->id);
        $reports = app(ReportingQueryService::class);

        $payments = $reports->paymentsByStatus($filters);
        $paidRow = collect($payments['rows'])->firstWhere('status', PaymentStatus::Paid->value);
        $failedRow = collect($payments['rows'])->firstWhere('status', PaymentStatus::Failed->value);
        $this->assertSame(1, $paidRow['payment_count']);
        $this->assertSame(50000, $paidRow['amount_total']);
        $this->assertSame(1, $failedRow['payment_count']);

        $tickets = $reports->ticketUsage($filters);
        $this->assertSame(3, $tickets['issued_count']);
        $this->assertSame(1, $tickets['used_count']);
        $this->assertSame(1, $tickets['cancelled_count']);

        $refunds = $reports->refundsAndCancels($filters);
        $kinds = collect($refunds['rows'])->pluck('kind')->all();
        $this->assertContains('order_cancelled', $kinds);
        $this->assertContains('order_refunded', $kinds);
        $this->assertContains('payment_refunded', $kinds);
        $this->assertSame($cancelled->order_number, collect($refunds['rows'])->firstWhere('kind', 'order_cancelled')['order_number']);
    }

    public function test_kiosk_health_counts_online_from_heartbeat(): void
    {
        $destination = Destination::factory()->create();
        Device::factory()->active()->create([
            'destination_id' => $destination->id,
            'last_heartbeat_at' => now(),
        ]);
        Device::factory()->active()->create([
            'destination_id' => $destination->id,
            'last_heartbeat_at' => now()->subHours(2),
        ]);

        $health = app(ReportingQueryService::class)->kioskHealth($destination->id);

        $this->assertSame(2, $health['total']);
        $this->assertSame(1, $health['online_count']);
        $this->assertSame(1, $health['offline_count']);
    }

    public function test_manager_can_read_report_api_and_ticket_officer_cannot(): void
    {
        $destination = Destination::factory()->create(['timezone' => 'Asia/Jakarta']);
        $at = CarbonImmutable::parse('2026-09-14 11:00:00', 'Asia/Jakarta');
        $this->paidOrder($destination, Channel::Assisted, 80000, $at);

        $manager = $this->userWithRole(RoleName::Manager);
        $token = $manager->createToken('phpunit')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/reports/sales/daily?date=2026-09-14&destination_id='.$destination->id)
            ->assertOk()
            ->assertJsonPath('data.totals.gross_sales', 80000)
            ->assertJsonPath('data.channels.1.channel', 'assisted')
            ->assertJsonPath('data.currency', 'IDR');

        $this->withToken($token)
            ->getJson('/api/v1/reports/payments?from=2026-09-14&to=2026-09-14')
            ->assertOk();

        $this->withToken($token)
            ->getJson('/api/v1/reports/tickets/usage?from=2026-09-14&to=2026-09-14')
            ->assertOk();

        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $officerToken = $officer->createToken('phpunit')->plainTextToken;
        $this->withToken($officerToken)
            ->getJson('/api/v1/reports/sales/daily?date=2026-09-14')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'auth.forbidden');
    }

    public function test_device_token_cannot_access_reports(): void
    {
        $destination = Destination::factory()->create();
        [, $token] = $this->activeDeviceToken($destination);

        $this->withToken($token)
            ->getJson('/api/v1/reports/sales/daily?date=2026-09-14')
            ->assertForbidden();
    }

    public function test_invalid_range_and_client_money_are_rejected(): void
    {
        $finance = $this->userWithRole(RoleName::Finance);
        $token = $finance->createToken('phpunit')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/reports/payments?from=2026-09-14&to=2026-09-13')
            ->assertStatus(422);

        $this->withToken($token)
            ->getJson('/api/v1/reports/sales/daily?date=2026-09-14&amount=1')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'money.client_values_forbidden');

        $this->withToken($token)
            ->getJson('/api/v1/reports/tickets/usage?from=2026-01-01&to=2026-04-05')
            ->assertStatus(422);
    }

    public function test_export_requires_reports_export_and_writes_audit(): void
    {
        $finance = $this->userWithRole(RoleName::Finance);
        $token = $finance->createToken('phpunit')->plainTextToken;

        $response = $this->withToken($token)
            ->get('/api/v1/reports/sales/daily/export?date=2026-09-14')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();
        $this->assertStringContainsString('gross_sales_idr', $csv);
        $this->assertStringContainsString('net_sales_idr', $csv);
        $this->assertStringContainsString('product_name', $csv);
        $this->assertStringContainsString('kiosk', $csv);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'report.exported',
            'actor_id' => $finance->id,
            'actor_type' => 'user',
        ]);

        $viewer = $this->userWithPermission(PermissionName::ReportsView);
        $this->assertTrue($viewer->can('viewAny', Report::class));
        $this->assertFalse($viewer->can('export', Report::class));

        $this->withToken($viewer->createToken('phpunit')->plainTextToken)
            ->get('/api/v1/reports/sales/daily/export?date=2026-09-14')
            ->assertForbidden();
    }

    public function test_dashboard_allows_finance_and_denies_ticket_officer(): void
    {
        $finance = $this->userWithRole(RoleName::Finance);

        $this->actingAs($finance)
            ->get(route('dashboard.reports'))
            ->assertOk()
            ->assertSee('Laporan')
            ->assertSee('Penjualan harian')
            ->assertDontSee('belum diluncurkan');

        Livewire::actingAs($finance)
            ->test(ReportBoard::class)
            ->assertSee('Penjualan per kanal')
            ->assertSee('Net Sales')
            ->call('showReport', 'by_product')
            ->assertSee('Penjualan per produk')
            ->assertSee('5 Tiket/Wahana Terlaris')
            ->call('showReport', 'by_cashier')
            ->assertSee('Penjualan per kasir')
            ->call('showReport', 'payments')
            ->assertSee('Pembayaran per status')
            ->assertSee('Perbandingan metode pembayaran')
            ->call('showReport', 'tickets')
            ->assertSee('Tiket terbit vs dipakai')
            ->call('showReport', 'refunds')
            ->assertSee('Refund dan pembatalan')
            ->call('showReport', 'kiosks')
            ->assertSee('Kesehatan kiosk');

        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $this->actingAs($officer)
            ->get(route('dashboard.reports'))
            ->assertForbidden();
    }

    public function test_payment_instrument_and_top_product_chart_payloads(): void
    {
        $destination = Destination::factory()->create(['timezone' => 'Asia/Jakarta']);
        $at = CarbonImmutable::parse('2026-09-14 10:00:00', 'Asia/Jakarta');

        Payment::factory()->cash()->paid()->create([
            'amount' => 10000,
            'created_at' => $at->utc(),
            'paid_at' => $at->utc(),
            'order_id' => Order::factory()->paid()->create([
                'destination_id' => $destination->id,
                'paid_at' => $at->utc(),
            ])->id,
        ]);
        Payment::factory()->paid()->create([
            'method' => PaymentMethod::Qris,
            'provider' => 'sandbox',
            'amount' => 20000,
            'created_at' => $at->utc(),
            'paid_at' => $at->utc(),
            'order_id' => Order::factory()->paid()->create([
                'destination_id' => $destination->id,
                'paid_at' => $at->utc(),
            ])->id,
        ]);
        Payment::factory()->paid()->create([
            'method' => PaymentMethod::Debit,
            'provider' => 'sandbox',
            'amount' => 30000,
            'created_at' => $at->utc(),
            'paid_at' => $at->utc(),
            'order_id' => Order::factory()->paid()->create([
                'destination_id' => $destination->id,
                'paid_at' => $at->utc(),
            ])->id,
        ]);
        Payment::factory()->paid()->create([
            'method' => PaymentMethod::EWallet,
            'provider' => 'sandbox',
            'amount' => 40000,
            'created_at' => $at->utc(),
            'paid_at' => $at->utc(),
            'order_id' => Order::factory()->paid()->create([
                'destination_id' => $destination->id,
                'paid_at' => $at->utc(),
            ])->id,
        ]);

        $typeA = TicketType::factory()->create([
            'destination_id' => $destination->id,
            'name' => 'Tiket A',
            'code' => 'TA',
        ]);
        $typeB = TicketType::factory()->create([
            'destination_id' => $destination->id,
            'name' => 'Tiket B',
            'code' => 'TB',
        ]);
        $order = Order::factory()->paid()->create([
            'destination_id' => $destination->id,
            'paid_at' => $at->utc(),
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'ticket_type_id' => $typeA->id,
            'ticket_type_code' => $typeA->code,
            'ticket_type_name' => $typeA->name,
            'quantity' => 5,
            'line_grand_total' => 50000,
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'ticket_type_id' => $typeB->id,
            'ticket_type_code' => $typeB->code,
            'ticket_type_name' => $typeB->name,
            'quantity' => 2,
            'line_grand_total' => 20000,
        ]);

        $filters = app(ReportingQueryService::class)->filters('2026-09-14', '2026-09-14', $destination->id);
        $instruments = app(ReportingQueryService::class)->paymentsByInstrument($filters);
        $this->assertSame(['Cash', 'QRIS', 'Debit', 'E-Wallet'], $instruments['labels']);
        $this->assertSame([1, 1, 1, 1], $instruments['series']);

        $top = app(ReportingQueryService::class)->topProductsByQty($filters, 5);
        $this->assertSame(['Tiket A', 'Tiket B'], $top['categories']);
        $this->assertSame([5, 2], $top['series']);

        $finance = $this->userWithRole(RoleName::Finance);
        $board = Livewire::actingAs($finance)
            ->test(ReportBoard::class)
            ->set('fromDate', '2026-09-14')
            ->set('toDate', '2026-09-14')
            ->set('destinationId', $destination->id)
            ->call('showReport', 'payments');

        $chart = $board->instance()->chartPayload(app(ReportingQueryService::class));
        $this->assertSame(['Cash', 'QRIS', 'Debit', 'E-Wallet'], $chart['labels']);
        $this->assertSame([1, 1, 1, 1], $chart['series']);
    }

    public function test_daily_revenue_trend_fills_missing_days(): void
    {
        $destination = Destination::factory()->create(['timezone' => 'Asia/Jakarta']);
        $today = CarbonImmutable::now('Asia/Jakarta');
        $this->paidOrder($destination, Channel::Kiosk, 25000, $today);

        $trend = app(ReportingQueryService::class)->dailyRevenueTrend(7, $destination->id);

        $this->assertSame(7, $trend['days']);
        $this->assertCount(7, $trend['categories']);
        $this->assertCount(7, $trend['series']);
        $this->assertSame($today->toDateString(), $trend['to']);
        $this->assertSame(25000, $trend['series'][6]);
    }

    public function test_dashboard_export_is_gated(): void
    {
        $viewer = $this->userWithPermission(PermissionName::ReportsView);

        Livewire::actingAs($viewer)
            ->test(ReportBoard::class)
            ->assertDontSee('Unduh CSV')
            ->call('exportSales')
            ->assertForbidden();

        $manager = $this->userWithRole(RoleName::Manager);
        Livewire::actingAs($manager)
            ->test(ReportBoard::class)
            ->assertSee('Unduh CSV')
            ->call('exportSales')
            ->assertFileDownloaded('wanderdesa-sales-'.now('Asia/Jakarta')->toDateString().'.csv');
    }

    private function paidOrder(
        Destination $destination,
        Channel $channel,
        int $grandTotal,
        CarbonImmutable $paidAtLocal,
    ): Order {
        return Order::factory()->paid()->create([
            'destination_id' => $destination->id,
            'channel' => $channel,
            'grand_total' => $grandTotal,
            'subtotal' => $grandTotal,
            'paid_at' => $paidAtLocal->utc(),
        ]);
    }

    private function userWithPermission(PermissionName $permission): User
    {
        $permissionModel = Permission::query()->where('name', $permission->value)->firstOrFail();
        $role = Role::factory()->create([
            'name' => 'test_'.$permission->value,
            'display_name' => 'Test '.$permission->value,
        ]);
        $role->permissions()->attach($permissionModel->id, ['created_at' => now()]);

        $user = User::factory()->create();
        $user->roles()->attach($role->id, ['created_at' => now()]);

        return $user->fresh(['roles.permissions']);
    }
}

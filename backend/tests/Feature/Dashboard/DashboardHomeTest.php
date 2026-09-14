<?php

namespace Tests\Feature\Dashboard;

use App\Enums\Channel;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Livewire\Dashboard\HomeRevenueChart;
use App\Models\AuditLog;
use App\Models\CheckIn;
use App\Models\Device;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Ticket;
use App\Services\Reporting\ReportingQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class DashboardHomeTest extends TestCase
{
    use InteractsWithRbac;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_ticket_officer_sees_assisted_widget_scoped_to_self(): void
    {
        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $other = $this->userWithRole(RoleName::TicketOfficer);

        Order::factory()->create([
            'channel' => Channel::Assisted,
            'status' => OrderStatus::PendingPayment,
            'created_by_user_id' => $officer->id,
            'created_at' => now(),
        ]);
        Order::factory()->create([
            'channel' => Channel::Assisted,
            'status' => OrderStatus::PendingPayment,
            'created_by_user_id' => $other->id,
            'created_at' => now(),
        ]);

        $this->actingAs($officer)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Penjualan dibantu hari ini')
            ->assertSee('Mulai penjualan')
            ->assertDontSee('Armada kiosk')
            ->assertDontSee('Penjualan hari ini');
    }

    public function test_gate_officer_sees_gate_widget(): void
    {
        $gate = $this->userWithRole(RoleName::GateOfficer);
        $ticket = Ticket::factory()->create();

        CheckIn::factory()->create([
            'ticket_id' => $ticket->id,
            'destination_id' => $ticket->destination_id,
            'checked_in_by_user_id' => $gate->id,
            'checked_in_at' => now(),
            'result' => 'allow',
        ]);

        AuditLog::factory()->create([
            'action' => 'ticket.validated',
            'actor_type' => 'user',
            'actor_id' => $gate->id,
            'after_json' => ['result' => 'DENY', 'reason_code' => 'DENY_EXPIRED'],
            'created_at' => now(),
        ]);

        $this->actingAs($gate)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Gerbang hari ini')
            ->assertSee('Check-in gerbang')
            ->assertSee('Allow (check-in)')
            ->assertDontSee('Mulai penjualan');
    }

    public function test_operator_sees_fleet_widget(): void
    {
        $operator = $this->userWithRole(RoleName::Operator);

        Device::factory()->create([
            'last_heartbeat_at' => now(),
            'maintenance_mode' => false,
        ]);
        Device::factory()->create([
            'last_heartbeat_at' => now()->subHours(2),
            'maintenance_mode' => true,
        ]);

        $this->actingAs($operator)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Armada kiosk')
            ->assertSee('Online')
            ->assertSee('Maintenance');
    }

    public function test_finance_sees_sales_and_payments_widgets(): void
    {
        $finance = $this->userWithRole(RoleName::Finance);

        Order::factory()->create([
            'channel' => Channel::Kiosk,
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
            'grand_total' => 100000,
        ]);

        Payment::factory()->create([
            'status' => PaymentStatus::Processing,
            'created_at' => now()->subMinutes(30),
        ]);

        $this->actingAs($finance)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Penjualan hari ini')
            ->assertSee('Tren Pendapatan Harian')
            ->assertSee('Pembayaran terbuka')
            ->assertSee('Rp 100.000')
            ->assertDontSee('Mulai penjualan');

        Livewire::actingAs($finance)
            ->test(HomeRevenueChart::class)
            ->assertSee('Tren Pendapatan Harian')
            ->call('setDays', 30)
            ->assertSet('days', 30);

        $payload = Livewire::actingAs($finance)
            ->test(HomeRevenueChart::class)
            ->instance()
            ->chartPayload(app(ReportingQueryService::class));

        $this->assertSame(7, $payload['days']);
        $this->assertCount(7, $payload['categories']);
    }

    public function test_auditor_sees_sensitive_audit_widget(): void
    {
        $auditor = $this->userWithRole(RoleName::Auditor);

        AuditLog::factory()->create([
            'action' => 'refund.created',
            'actor_type' => 'user',
            'actor_id' => 1,
            'created_at' => now(),
        ]);

        $this->actingAs($auditor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Audit sensitif terbaru')
            ->assertSee('refund.created')
            ->assertSee('Audit logs');
    }
}

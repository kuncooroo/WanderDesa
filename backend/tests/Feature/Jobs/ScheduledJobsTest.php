<?php

namespace Tests\Feature\Jobs;

use App\Actions\Ops\NotifyOpsAlerts;
use App\Actions\Orders\ExpireUnpaidOrder;
use App\Actions\Payments\ApplyProviderPaymentEvent;
use App\Actions\Payments\MarkPaymentPaid;
use App\Actions\Tickets\ExpireTicket;
use App\Enums\DeviceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\TicketStatus;
use App\Integrations\Payments\PaymentGateway;
use App\Jobs\DetectOfflineDevices;
use App\Jobs\ExpireUnpaidOrders;
use App\Jobs\ExpireUnusedTickets;
use App\Jobs\ReconcileOpenPayments;
use App\Models\AuditLog;
use App\Models\Destination;
use App\Models\Device;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Support\Audit\AuditWriter;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithOrders;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class ScheduledJobsTest extends TestCase
{
    use InteractsWithOrders;
    use InteractsWithRbac;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_unpaid_order_past_ttl_expires_without_tickets(): void
    {
        CarbonImmutable::setTestNow('2026-09-14 10:00:00');

        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        $order = Order::factory()->create([
            'destination_id' => $destination->id,
            'status' => OrderStatus::PendingPayment,
            'grand_total' => 50000,
            'expires_at' => now()->subMinute(),
        ]);
        $this->attachOrderLine($order, $adult, 1);
        $payment = Payment::factory()->processing()->create([
            'order_id' => $order->id,
            'method' => PaymentMethod::EWallet,
            'amount' => 50000,
        ]);

        $this->app->make(ExpireUnpaidOrders::class)->handle(
            $this->app->make(PaymentGateway::class),
            $this->app->make(ApplyProviderPaymentEvent::class),
            $this->app->make(ExpireUnpaidOrder::class),
        );

        $this->assertSame(OrderStatus::Expired, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->expired_at);
        $this->assertSame(PaymentStatus::Expired, $payment->fresh()->status);
        $this->assertSame(0, Ticket::query()->count());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'order.expired',
            'entity_id' => $order->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'payment.expired',
            'entity_id' => $payment->id,
        ]);

        $this->app->make(ExpireUnpaidOrders::class)->handle(
            $this->app->make(PaymentGateway::class),
            $this->app->make(ApplyProviderPaymentEvent::class),
            $this->app->make(ExpireUnpaidOrder::class),
        );

        $this->assertSame(1, AuditLog::query()->where('action', 'order.expired')->where('entity_id', $order->id)->count());

        CarbonImmutable::setTestNow();
    }

    public function test_paid_order_is_not_expired_by_ttl_job(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        $order = Order::factory()->paid()->create([
            'destination_id' => $destination->id,
            'grand_total' => 50000,
            'expires_at' => now()->subHour(),
        ]);
        $this->attachOrderLine($order, $adult, 1);
        Payment::factory()->paid()->create([
            'order_id' => $order->id,
            'amount' => 50000,
        ]);

        $this->app->make(ExpireUnpaidOrders::class)->handle(
            $this->app->make(PaymentGateway::class),
            $this->app->make(ApplyProviderPaymentEvent::class),
            $this->app->make(ExpireUnpaidOrder::class),
        );

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'order.expired', 'entity_id' => $order->id]);
    }

    public function test_late_provider_paid_can_recover_ttl_expired_digital_payment(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        $order = Order::factory()->create([
            'destination_id' => $destination->id,
            'status' => OrderStatus::PendingPayment,
            'grand_total' => 51500,
            'expires_at' => now()->subMinute(),
        ]);
        $this->attachOrderLine($order, $adult, 1);
        $payment = Payment::factory()->processing()->create([
            'order_id' => $order->id,
            'method' => PaymentMethod::EWallet,
            'amount' => 51500,
        ]);

        $this->app->make(ExpireUnpaidOrder::class)->handle($order);

        $this->assertSame(OrderStatus::Expired, $order->fresh()->status);
        $this->assertSame(PaymentStatus::Expired, $payment->fresh()->status);

        $this->app->make(MarkPaymentPaid::class)->handle($payment->fresh());

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(1, Ticket::query()->where('order_id', $order->id)->count());
    }

    public function test_unused_tickets_past_valid_end_expire_and_used_tickets_do_not(): void
    {
        CarbonImmutable::setTestNow('2026-09-14 18:00:00');

        $destination = Destination::factory()->create();
        $type = TicketType::factory()->create(['destination_id' => $destination->id]);
        $order = Order::factory()->paid()->create(['destination_id' => $destination->id]);
        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'ticket_type_id' => $type->id,
        ]);
        $payment = Payment::factory()->paid()->create(['order_id' => $order->id]);

        $stale = Ticket::factory()->create([
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'destination_id' => $destination->id,
            'ticket_type_id' => $type->id,
            'payment_id' => $payment->id,
            'status' => TicketStatus::Active,
            'valid_start_at' => now()->subDay(),
            'valid_end_at' => now()->subSecond(),
        ]);
        $used = Ticket::factory()->used()->create([
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'destination_id' => $destination->id,
            'ticket_type_id' => $type->id,
            'payment_id' => $payment->id,
            'valid_start_at' => now()->subDay(),
            'valid_end_at' => now()->subSecond(),
            'used_at' => now()->subHour(),
        ]);
        $future = Ticket::factory()->create([
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'destination_id' => $destination->id,
            'ticket_type_id' => $type->id,
            'payment_id' => $payment->id,
            'status' => TicketStatus::Active,
            'valid_start_at' => now()->subHour(),
            'valid_end_at' => now()->addHour(),
        ]);

        $this->app->make(ExpireUnusedTickets::class)->handle(
            $this->app->make(ExpireTicket::class),
        );
        $this->app->make(ExpireUnusedTickets::class)->handle(
            $this->app->make(ExpireTicket::class),
        );

        $this->assertSame(TicketStatus::Expired, $stale->fresh()->status);
        $this->assertNotNull($stale->fresh()->expired_at);
        $this->assertSame(TicketStatus::Used, $used->fresh()->status);
        $this->assertSame(TicketStatus::Active, $future->fresh()->status);
        $this->assertSame(1, AuditLog::query()->where('action', 'ticket.expired')->where('entity_id', $stale->id)->count());

        CarbonImmutable::setTestNow();
    }

    public function test_offline_detection_respects_heartbeat_sla_and_is_idempotent(): void
    {
        CarbonImmutable::setTestNow('2026-09-14 12:00:00');

        $online = Device::factory()->active()->create([
            'last_heartbeat_at' => now(),
        ]);
        $offline = Device::factory()->active()->create([
            'last_heartbeat_at' => now()->subMinutes(5),
        ]);
        Device::factory()->create([
            'status' => DeviceStatus::Registered,
            'is_active' => false,
            'last_heartbeat_at' => null,
        ]);

        $notify = $this->app->make(NotifyOpsAlerts::class);
        $job = $this->app->make(DetectOfflineDevices::class);
        $job->handle($this->app->make(AuditWriter::class), $notify);
        $job->handle($this->app->make(AuditWriter::class), $notify);

        $this->assertSame(0, AuditLog::query()->where('action', 'device.offline_detected')->where('entity_id', $online->id)->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'device.offline_detected')->where('entity_id', $offline->id)->count());
        $this->assertFalse($offline->fresh()->isOnline());
        $this->assertSame(DeviceStatus::Active, $offline->fresh()->status);

        CarbonImmutable::setTestNow('2026-09-14 12:10:00');
        $offline->forceFill(['last_heartbeat_at' => now()])->save();
        $job->handle($this->app->make(AuditWriter::class), $notify);
        $this->assertSame(1, AuditLog::query()->where('action', 'device.offline_detected')->where('entity_id', $offline->id)->count());

        CarbonImmutable::setTestNow('2026-09-14 12:15:00');
        $job->handle($this->app->make(AuditWriter::class), $notify);
        $this->assertSame(2, AuditLog::query()->where('action', 'device.offline_detected')->where('entity_id', $offline->id)->count());

        CarbonImmutable::setTestNow();
    }

    public function test_schedule_registers_ttl_reconcile_offline_and_ticket_jobs(): void
    {
        $this->artisan('schedule:list')
            ->assertSuccessful()
            ->expectsOutputToContain('expire-unpaid-orders')
            ->expectsOutputToContain('reconcile-open-payments')
            ->expectsOutputToContain('detect-offline-devices')
            ->expectsOutputToContain('expire-unused-tickets')
            ->expectsOutputToContain('backup-mysql');

        $this->assertTrue(class_exists(ReconcileOpenPayments::class));
    }
}

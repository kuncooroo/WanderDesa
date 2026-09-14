<?php

namespace Tests\Feature\Tickets;

use App\Actions\Payments\MarkPaymentPaid;
use App\Actions\Tickets\IssueTickets;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Enums\TicketStatus;
use App\Enums\ValidityType;
use App\Exceptions\DomainException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Ticket;
use App\Services\Qr\GeneratedQrPayload;
use App\Services\Qr\QrPayloadGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\InteractsWithCashierShifts;
use Tests\Concerns\InteractsWithOrders;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class IssueTicketsTest extends TestCase
{
    use InteractsWithCashierShifts;
    use InteractsWithOrders;
    use InteractsWithRbac;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_paid_order_yields_ticket_count_matching_quantities(): void
    {
        ['destination' => $destination, 'adult' => $adult, 'child' => $child] = $this->sellableCatalog();
        $order = Order::factory()->create([
            'destination_id' => $destination->id,
            'status' => OrderStatus::PendingPayment,
            'grand_total' => 128000,
        ]);
        $this->attachOrderLine($order, $adult, 2);
        $this->attachOrderLine($order, $child, 1);
        $payment = Payment::factory()->processing()->create([
            'order_id' => $order->id,
            'method' => PaymentMethod::EWallet,
            'amount' => 128000,
        ]);

        $this->app->make(MarkPaymentPaid::class)->handle($payment);

        $this->assertSame(3, Ticket::query()->where('order_id', $order->id)->count());
        $this->assertSame(2, Ticket::query()->where('ticket_type_id', $adult->id)->count());
        $this->assertSame(1, Ticket::query()->where('ticket_type_id', $child->id)->count());
        $this->assertTrue(
            Ticket::query()->where('order_id', $order->id)->get()->every(
                fn (Ticket $ticket): bool => in_array($ticket->status, [
                    TicketStatus::Issued,
                    TicketStatus::Active,
                    TicketStatus::Expired,
                ], true),
            ),
        );
        $this->assertSame($order->channel, Ticket::query()->where('order_id', $order->id)->first()?->channel);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ticket.issued',
            'entity_type' => 'ticket',
        ]);
        $this->assertSame(3, Ticket::query()->where('order_id', $order->id)->distinct()->count('ticket_code'));
        $this->assertSame(3, Ticket::query()->where('order_id', $order->id)->distinct()->count('qr_payload_hash'));
    }

    public function test_issue_tickets_is_idempotent(): void
    {
        $payment = $this->paidPaymentWithLine(2);

        $action = $this->app->make(IssueTickets::class);
        $first = $action->handle($payment);
        $second = $action->handle($payment->fresh());

        $this->assertCount(2, $first);
        $this->assertEqualsCanonicalizing(
            $first->pluck('id')->all(),
            $second->pluck('id')->all(),
        );
        $this->assertSame(2, Ticket::query()->count());
    }

    public function test_unpaid_order_cannot_issue_tickets(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        $order = Order::factory()->create([
            'destination_id' => $destination->id,
            'status' => OrderStatus::PendingPayment,
        ]);
        $this->attachOrderLine($order, $adult, 1);
        $payment = Payment::factory()->processing()->create([
            'order_id' => $order->id,
            'amount' => $order->grand_total,
        ]);

        try {
            $this->app->make(IssueTickets::class)->handle($payment);
            $this->fail('Expected DomainException');
        } catch (DomainException $e) {
            $this->assertSame('order.state_conflict', $e->errorCode);
            $this->assertSame(409, $e->statusCode);
        }

        $this->assertSame(0, Ticket::query()->count());
    }

    public function test_same_day_ticket_is_active_within_validity_window(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        $adult->forceFill(['validity_type' => ValidityType::SameDay])->save();

        $order = Order::factory()->create([
            'destination_id' => $destination->id,
            'status' => OrderStatus::PendingPayment,
        ]);
        $this->attachOrderLine($order, $adult, 1);
        $payment = Payment::factory()->processing()->create([
            'order_id' => $order->id,
            'amount' => $order->grand_total,
        ]);

        $this->app->make(MarkPaymentPaid::class)->handle($payment);

        $ticket = Ticket::query()->firstOrFail();
        $this->assertSame(TicketStatus::Active, $ticket->status);
        $this->assertNotNull($ticket->activated_at);
        $this->assertTrue($ticket->valid_start_at->lte(now()));
        $this->assertTrue($ticket->valid_end_at->gte(now()));
    }

    public function test_partial_issue_failure_rolls_back(): void
    {
        $payment = $this->paidPaymentWithLine(2);

        $this->mock(QrPayloadGenerator::class, function ($mock): void {
            $mock->shouldReceive('generate')
                ->once()
                ->andReturn(new GeneratedQrPayload(
                    payload: 'WD1.test.'.str_repeat('a', 32).'.'.str_repeat('b', 32),
                    hash: hash('sha256', 'first-payload'),
                    version: 1,
                    secretHint: 'v1',
                ));
            $mock->shouldReceive('generate')
                ->once()
                ->andThrow(new RuntimeException('qr boom'));
        });

        try {
            $this->app->make(IssueTickets::class)->handle($payment);
            $this->fail('Expected QR generator failure');
        } catch (RuntimeException $e) {
            $this->assertSame('qr boom', $e->getMessage());
        }

        $this->assertSame(0, Ticket::query()->count());
    }

    public function test_cash_confirm_issues_tickets(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $this->openCashierShiftFor($officer);
        $token = $officer->createToken('phpunit')->plainTextToken;
        $order = $this->createPendingOrder($token, $destination, $adult, 'cash-issue');

        $paymentId = $this->withIdempotency($token, 'cash-issue-init')
            ->postJson('/api/v1/orders/'.$order['id'].'/payments', [
                'method' => PaymentMethod::Cash->value,
            ])
            ->json('data.payment.id');

        $confirm = $this->withIdempotency($token, 'cash-issue-confirm')
            ->postJson("/api/v1/payments/{$paymentId}/confirm-cash", ['received' => true]);

        $confirm->assertOk()
            ->assertJsonPath('data.tickets_issued', true);

        $code = $confirm->json('data.tickets.0.ticket_code');
        $this->assertIsString($code);
        $this->assertStringStartsWith('TCK-', $code);

        $this->assertSame(2, Ticket::query()->where('order_id', $order['id'])->count());
        $this->assertSame(PaymentStatus::Paid, Payment::query()->find($paymentId)?->status);
    }

    private function paidPaymentWithLine(int $quantity): Payment
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        $order = Order::factory()->paid()->create([
            'destination_id' => $destination->id,
            'grand_total' => $adult->unit_price * $quantity,
        ]);
        $this->attachOrderLine($order, $adult, $quantity);

        return Payment::factory()->create([
            'order_id' => $order->id,
            'status' => PaymentStatus::Paid,
            'method' => PaymentMethod::EWallet,
            'amount' => $order->grand_total,
            'paid_at' => now(),
        ]);
    }
}

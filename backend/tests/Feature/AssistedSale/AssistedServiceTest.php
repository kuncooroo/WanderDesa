<?php

namespace Tests\Feature\AssistedSale;

use App\Actions\Pricing\QuoteOrderAction;
use App\Enums\Channel;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Enums\TicketDenyCode;
use App\Enums\TicketStatus;
use App\Enums\TicketValidateResult;
use App\Livewire\AssistedSale\SaleWizard;
use App\Livewire\Gate\CheckInPanel;
use App\Models\Gate;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Ticket;
use App\Support\Authorization\Authorizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\InteractsWithCashierShifts;
use Tests\Concerns\InteractsWithOrders;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

#[Group('critical')]
class AssistedServiceTest extends TestCase
{
    use InteractsWithCashierShifts;
    use InteractsWithOrders;
    use InteractsWithRbac;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        config(['tickets.qr_secret' => 'test-ticket-qr-secret', 'tickets.qr_kid' => 'v1']);
    }

    public function test_ticket_officer_completes_assisted_cash_sale(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $this->openCashierShiftFor($officer);

        $expectedQuote = app(QuoteOrderAction::class)->handle($destination->id, [
            [
                'ticket_type_id' => $adult->id,
                'quantity' => 2,
                'visit_date' => '2026-09-13',
            ],
        ]);

        Livewire::actingAs($officer)
            ->test(SaleWizard::class)
            ->set('destinationId', $destination->id)
            ->set('visitDate', '2026-09-13')
            ->call('setQuantity', $adult->id, 2)
            ->assertSet('quote.grand_total', $expectedQuote['grand_total'])
            ->call('goToSummary')
            ->assertSet('step', 'summary')
            ->call('createAndCollectCash')
            ->assertSet('step', 'pay')
            ->assertSet('showCashModal', true)
            ->assertSet('paidAmount', $expectedQuote['grand_total'])
            ->call('confirmCashReceived')
            ->assertSet('step', 'done')
            ->assertSet('paymentStatus', PaymentStatus::Paid->value)
            ->assertSee('Pembayaran berhasil');

        $order = Order::query()->first();
        $this->assertNotNull($order);
        $this->assertSame(Channel::Assisted, $order->channel);
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame($expectedQuote['grand_total'], $order->grand_total);
        $this->assertSame($officer->id, $order->created_by_user_id);

        $payment = Payment::query()->where('order_id', $order->id)->first();
        $this->assertNotNull($payment);
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertSame($expectedQuote['grand_total'], $payment->amount);
        $this->assertSame($officer->id, $payment->collected_by_user_id);

        $this->assertSame(2, Ticket::query()->where('order_id', $order->id)->count());
        $this->assertTrue(
            Ticket::query()->where('order_id', $order->id)->get()->every(
                fn (Ticket $ticket): bool => $ticket->channel === Channel::Assisted
            )
        );

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'order.created',
            'entity_id' => $order->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'payment.paid',
            'actor_id' => $officer->id,
        ]);
    }

    public function test_gate_officer_check_in_allows_then_denies_already_used(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $this->openCashierShiftFor($officer);

        Livewire::actingAs($officer)
            ->test(SaleWizard::class)
            ->set('destinationId', $destination->id)
            ->set('visitDate', '2026-09-13')
            ->call('setQuantity', $adult->id, 1)
            ->call('goToSummary')
            ->call('createAndCollectCash')
            ->call('confirmCashReceived')
            ->assertSet('step', 'done');

        $ticket = Ticket::query()->firstOrFail();
        $this->assertContains($ticket->status, [TicketStatus::Active, TicketStatus::Issued]);

        $gate = Gate::factory()->create([
            'destination_id' => $destination->id,
            'is_active' => true,
        ]);
        $gateOfficer = $this->userWithRole(RoleName::GateOfficer);

        Livewire::actingAs($gateOfficer)
            ->test(CheckInPanel::class)
            ->set('destinationId', $destination->id)
            ->set('gateId', $gate->id)
            ->set('qrPayload', $ticket->qr_payload)
            ->call('submitCheckIn')
            ->assertSet('result', TicketValidateResult::Allow->value)
            ->assertSet('reasonCode', '')
            ->assertSet('ticketStatus', TicketStatus::Used->value);

        $this->assertSame(TicketStatus::Used, $ticket->fresh()->status);

        Livewire::actingAs($gateOfficer)
            ->test(CheckInPanel::class)
            ->set('destinationId', $destination->id)
            ->set('gateId', $gate->id)
            ->set('qrPayload', $ticket->qr_payload)
            ->call('submitCheckIn')
            ->assertSet('result', TicketValidateResult::Deny->value)
            ->assertSet('reasonCode', TicketDenyCode::AlreadyUsed->value);
    }

    public function test_ticket_officer_cannot_refund_and_gate_officer_cannot_open_sale(): void
    {
        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $gateOfficer = $this->userWithRole(RoleName::GateOfficer);

        $this->assertFalse(Authorizer::check($officer, PermissionName::PaymentsRefund));
        $this->assertFalse($officer->can('refund', Payment::factory()->create()));

        $this->actingAs($officer)
            ->get(route('dashboard.assisted-sale'))
            ->assertOk();

        $this->actingAs($officer)
            ->get(route('dashboard.check-in'))
            ->assertForbidden();

        $this->actingAs($gateOfficer)
            ->get(route('dashboard.assisted-sale'))
            ->assertForbidden();

        $this->actingAs($gateOfficer)
            ->get(route('dashboard.check-in'))
            ->assertOk();

        Livewire::actingAs($officer)
            ->test(SaleWizard::class)
            ->assertDontSee('Refund')
            ->assertDontSee('refund');
    }

    public function test_assisted_sale_quote_matches_pricing_action_not_client_math(): void
    {
        ['destination' => $destination, 'adult' => $adult, 'child' => $child] = $this->sellableCatalog();
        $officer = $this->userWithRole(RoleName::TicketOfficer);

        $serverQuote = app(QuoteOrderAction::class)->handle($destination->id, [
            ['ticket_type_id' => $adult->id, 'quantity' => 1, 'visit_date' => '2026-09-13'],
            ['ticket_type_id' => $child->id, 'quantity' => 2, 'visit_date' => '2026-09-13'],
        ]);

        // Deliberately wrong client-side sum of unit prices alone (missing tax/fees).
        $naiveClientTotal = $adult->unit_price + ($child->unit_price * 2);
        $this->assertNotSame($naiveClientTotal, $serverQuote['grand_total']);

        Livewire::actingAs($officer)
            ->test(SaleWizard::class)
            ->set('destinationId', $destination->id)
            ->set('visitDate', '2026-09-13')
            ->call('setQuantity', $adult->id, 1)
            ->call('setQuantity', $child->id, 2)
            ->assertSet('quote.grand_total', $serverQuote['grand_total'])
            ->assertSet('quote.tax_total', $serverQuote['tax_total'])
            ->assertSet('quote.service_fee_total', $serverQuote['service_fee_total']);
    }
}

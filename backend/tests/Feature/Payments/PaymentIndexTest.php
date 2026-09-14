<?php

namespace Tests\Feature\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Enums\TicketStatus;
use App\Livewire\Payments\PaymentIndex;
use App\Models\Destination;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class PaymentIndexTest extends TestCase
{
    use InteractsWithRbac;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_finance_can_open_payments_page(): void
    {
        $finance = $this->userWithRole(RoleName::Finance);

        $this->actingAs($finance)
            ->get(route('dashboard.payments'))
            ->assertOk()
            ->assertSee('Pembayaran')
            ->assertDontSee('belum diluncurkan');
    }

    public function test_gate_officer_cannot_open_payments_page(): void
    {
        $gate = $this->userWithRole(RoleName::GateOfficer);

        $this->actingAs($gate)
            ->get(route('dashboard.payments'))
            ->assertForbidden();
    }

    public function test_payments_list_filters_and_shows_detail(): void
    {
        $finance = $this->userWithRole(RoleName::Finance);
        $destination = Destination::factory()->create(['name' => 'Desa Bayar']);

        $matchOrder = Order::factory()->create([
            'order_number' => 'ORDPAYMATCH',
            'destination_id' => $destination->id,
            'status' => OrderStatus::Paid,
        ]);

        $match = Payment::factory()->cash()->paid()->create([
            'payment_number' => 'PAYFILTER01',
            'order_id' => $matchOrder->id,
            'amount' => 88000,
            'provider_reference' => 'REF-MATCH',
        ]);

        $otherOrder = Order::factory()->create([
            'order_number' => 'ORDOTHER',
            'destination_id' => $destination->id,
        ]);

        Payment::factory()->create([
            'payment_number' => 'PAYOTHER99',
            'order_id' => $otherOrder->id,
            'status' => PaymentStatus::Pending,
            'method' => PaymentMethod::EWallet,
        ]);

        Livewire::actingAs($finance)
            ->test(PaymentIndex::class)
            ->set('paymentNumber', 'PAYFILTER')
            ->assertSee('PAYFILTER01')
            ->assertDontSee('PAYOTHER99')
            ->call('selectPayment', $match->id)
            ->assertSee('Detail PAYFILTER01')
            ->assertSee('ORDPAYMATCH')
            ->assertSee('Rp 88.000')
            ->assertSee('REF-MATCH');
    }

    public function test_finance_can_refund_eligible_payment_from_detail(): void
    {
        $finance = $this->userWithRole(RoleName::Finance);
        $destination = Destination::factory()->create();

        $order = Order::factory()->create([
            'destination_id' => $destination->id,
            'status' => OrderStatus::Paid,
            'grand_total' => 50000,
        ]);

        $payment = Payment::factory()->cash()->paid()->create([
            'order_id' => $order->id,
            'amount' => 50000,
            'payment_number' => 'PAYREFUND01',
        ]);

        $item = OrderItem::factory()->create(['order_id' => $order->id]);

        Ticket::factory()->create([
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'destination_id' => $destination->id,
            'payment_id' => $payment->id,
            'status' => TicketStatus::Issued,
        ]);

        Livewire::actingAs($finance)
            ->test(PaymentIndex::class)
            ->call('selectPayment', $payment->id)
            ->assertSee('Proses refund')
            ->set('refundReason', 'Pengunjung batal')
            ->call('refundSelected')
            ->assertSee('Pembayaran direfund.');

        $this->assertSame(PaymentStatus::Refunded, $payment->fresh()->status);
        $this->assertSame(OrderStatus::Refunded, $order->fresh()->status);
        $this->assertSame(TicketStatus::Refunded, Ticket::query()->where('payment_id', $payment->id)->firstOrFail()->status);
    }

    public function test_ticket_officer_cannot_refund(): void
    {
        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $destination = Destination::factory()->create();

        $order = Order::factory()->create([
            'destination_id' => $destination->id,
            'status' => OrderStatus::Paid,
        ]);

        $payment = Payment::factory()->cash()->paid()->create([
            'order_id' => $order->id,
            'amount' => 50000,
        ]);

        Livewire::actingAs($officer)
            ->test(PaymentIndex::class)
            ->call('selectPayment', $payment->id)
            ->assertDontSee('Proses refund')
            ->call('refundSelected')
            ->assertForbidden();

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
    }
}

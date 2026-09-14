<?php

namespace Tests\Feature\Orders;

use App\Enums\Channel;
use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Livewire\Orders\OrderIndex;
use App\Models\Destination;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class OrderIndexTest extends TestCase
{
    use InteractsWithRbac;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_ticket_officer_can_open_orders_page(): void
    {
        $officer = $this->userWithRole(RoleName::TicketOfficer);

        $this->actingAs($officer)
            ->get(route('dashboard.orders'))
            ->assertOk()
            ->assertSee('Pesanan')
            ->assertDontSee('belum diluncurkan');
    }

    public function test_gate_officer_cannot_open_orders_page(): void
    {
        $gate = $this->userWithRole(RoleName::GateOfficer);

        $this->actingAs($gate)
            ->get(route('dashboard.orders'))
            ->assertForbidden();
    }

    public function test_orders_list_filters_by_order_number_and_shows_detail(): void
    {
        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $destination = Destination::factory()->create(['name' => 'Desa Uji']);

        $match = Order::factory()->create([
            'order_number' => 'ORDFILTER01',
            'destination_id' => $destination->id,
            'channel' => Channel::Assisted,
            'status' => OrderStatus::PendingPayment,
            'grand_total' => 75000,
            'created_by_user_id' => $officer->id,
        ]);

        OrderItem::factory()->create([
            'order_id' => $match->id,
            'ticket_type_name' => 'Dewasa',
            'ticket_type_code' => 'ADULT',
            'quantity' => 1,
            'unit_price' => 75000,
            'line_grand_total' => 75000,
        ]);

        Order::factory()->create([
            'order_number' => 'ORDOTHER99',
            'destination_id' => $destination->id,
        ]);

        Livewire::actingAs($officer)
            ->test(OrderIndex::class)
            ->set('orderNumber', 'ORDFILTER')
            ->assertSee('ORDFILTER01')
            ->assertDontSee('ORDOTHER99')
            ->call('selectOrder', $match->id)
            ->assertSee('Detail ORDFILTER01')
            ->assertSee('Dewasa')
            ->assertSee('Rp 75.000');
    }

    public function test_staff_can_cancel_pending_order_from_detail(): void
    {
        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $destination = Destination::factory()->create();

        $order = Order::factory()->create([
            'order_number' => 'ORDCANCEL01',
            'destination_id' => $destination->id,
            'status' => OrderStatus::PendingPayment,
            'created_by_user_id' => $officer->id,
        ]);

        Livewire::actingAs($officer)
            ->test(OrderIndex::class)
            ->call('selectOrder', $order->id)
            ->set('cancelReason', 'Pengunjung batal')
            ->call('cancelSelected')
            ->assertSee('Pesanan dibatalkan.');

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
    }
}

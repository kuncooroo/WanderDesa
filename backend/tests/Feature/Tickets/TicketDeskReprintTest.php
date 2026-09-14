<?php

namespace Tests\Feature\Tickets;

use App\Enums\PaymentMethod;
use App\Enums\RoleName;
use App\Livewire\AssistedSale\SaleWizard;
use App\Livewire\Tickets\TicketDesk;
use App\Models\AuditLog;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithCashierShifts;
use Tests\Concerns\InteractsWithOrders;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class TicketDeskReprintTest extends TestCase
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

    public function test_ticket_officer_reprint_does_not_mint_ticket(): void
    {
        $ticket = $this->issuedAssistedTicket();
        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $count = Ticket::query()->count();
        $status = $ticket->status;

        $this->actingAs($officer)
            ->get(route('dashboard.tickets'))
            ->assertOk()
            ->assertSee('Tiket')
            ->assertDontSee('belum diluncurkan');

        Livewire::actingAs($officer)
            ->test(TicketDesk::class)
            ->set('ticketCode', $ticket->ticket_code)
            ->call('searchTicket')
            ->assertSet('ticket.ticket_code', $ticket->ticket_code)
            ->call('loadPrintPayload')
            ->assertSet('printPayload.ticket_code', $ticket->ticket_code)
            ->call('recordPrintSuccess')
            ->assertSet('alreadyPrinted', true)
            ->call('recordPrintSuccess')
            ->assertSee('tidak diterbitkan ulang');

        $this->assertSame($count, Ticket::query()->count());
        $this->assertSame($status, $ticket->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ticket.printed',
            'entity_id' => $ticket->id,
            'actor_id' => $officer->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ticket.reprinted',
            'entity_id' => $ticket->id,
        ]);
    }

    public function test_auditor_can_view_desk_but_cannot_reprint(): void
    {
        $ticket = $this->issuedAssistedTicket();
        $auditor = $this->userWithRole(RoleName::Auditor);

        $this->actingAs($auditor)
            ->get(route('dashboard.tickets'))
            ->assertOk();

        Livewire::actingAs($auditor)
            ->test(TicketDesk::class)
            ->set('ticketCode', $ticket->ticket_code)
            ->call('searchTicket')
            ->assertSet('ticket.ticket_code', $ticket->ticket_code)
            ->call('recordPrintSuccess')
            ->assertForbidden();
    }

    public function test_assisted_sale_print_ack_reuses_issued_tickets(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $this->openCashierShiftFor($officer);

        $component = Livewire::actingAs($officer)
            ->test(SaleWizard::class)
            ->set('destinationId', $destination->id)
            ->set('visitDate', '2026-09-13')
            ->call('setQuantity', $adult->id, 1)
            ->call('goToSummary')
            ->call('createAndCollectCash')
            ->call('confirmCashReceived')
            ->assertSet('step', 'done')
            ->call('loadPrintPayloads')
            ->call('recordPrintSuccess');

        $ticket = Ticket::query()->firstOrFail();
        $this->assertSame(1, Ticket::query()->count());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ticket.printed',
            'entity_id' => $ticket->id,
        ]);

        $component->call('recordPrintSuccess');
        $this->assertSame(1, Ticket::query()->count());
        $this->assertTrue(
            AuditLog::query()->where('action', 'ticket.reprinted')->where('entity_id', $ticket->id)->exists()
        );
    }

    private function issuedAssistedTicket(): Ticket
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $this->openCashierShiftFor($officer);
        $token = $officer->createToken('phpunit')->plainTextToken;
        $order = $this->createPendingOrder($token, $destination, $adult, 'desk-'.uniqid());
        $paymentId = $this->withIdempotency($token, 'desk-init-'.uniqid())
            ->postJson('/api/v1/orders/'.$order['id'].'/payments', [
                'method' => PaymentMethod::Cash->value,
            ])
            ->json('data.payment.id');
        $this->withIdempotency($token, 'desk-pay-'.uniqid())
            ->postJson("/api/v1/payments/{$paymentId}/confirm-cash", ['received' => true])
            ->assertOk();

        return Ticket::query()->where('order_id', $order['id'])->firstOrFail();
    }
}

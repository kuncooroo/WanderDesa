<?php

namespace Tests\Feature\Tickets;

use App\Enums\PaymentMethod;
use App\Enums\PrintAttemptResult;
use App\Enums\RoleName;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketPrintLog;
use App\Support\DeviceAbilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithCashierShifts;
use Tests\Concerns\InteractsWithOrders;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class TicketPrintAckTest extends TestCase
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

    public function test_device_print_ack_success_does_not_mint_a_ticket(): void
    {
        $ticket = $this->issuedKioskTicket();
        $count = Ticket::query()->count();
        $status = $ticket['model']->status;

        $ack = $this->withToken($ticket['token'])
            ->postJson('/api/v1/tickets/'.$ticket['code'].'/print-ack', [
                'result' => PrintAttemptResult::Success->value,
            ]);

        $ack->assertCreated()
            ->assertJsonPath('data.ticket_code', $ticket['code'])
            ->assertJsonPath('data.audit_action', 'ticket.printed')
            ->assertJsonPath('data.ticket_status', $status instanceof TicketStatus ? $status->value : $status);

        $this->assertSame($count, Ticket::query()->count());
        $this->assertSame($status, $ticket['model']->fresh()->status);
        $this->assertDatabaseHas('ticket_print_logs', [
            'ticket_id' => $ticket['model']->id,
            'result' => PrintAttemptResult::Success->value,
            'actor_type' => 'device',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ticket.printed',
            'entity_type' => 'ticket',
            'entity_id' => $ticket['model']->id,
        ]);
    }

    public function test_second_success_ack_is_reprint_not_a_new_ticket(): void
    {
        $ticket = $this->issuedKioskTicket();

        $this->withToken($ticket['token'])
            ->postJson('/api/v1/tickets/'.$ticket['code'].'/print-ack', [
                'result' => 'success',
            ])
            ->assertCreated();

        $reprint = $this->withToken($ticket['token'])
            ->postJson('/api/v1/tickets/'.$ticket['code'].'/print-ack', [
                'result' => 'success',
            ]);

        $reprint->assertCreated()
            ->assertJsonPath('data.audit_action', 'ticket.reprinted');

        $this->assertSame(1, Ticket::query()->where('ticket_code', $ticket['code'])->count());
        $this->assertSame(2, TicketPrintLog::query()->where('ticket_id', $ticket['model']->id)->count());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ticket.reprinted',
            'entity_id' => $ticket['model']->id,
        ]);
    }

    public function test_failed_ack_keeps_issued_ticket(): void
    {
        $ticket = $this->issuedKioskTicket();
        $status = $ticket['model']->status;

        $this->withToken($ticket['token'])
            ->postJson('/api/v1/tickets/'.$ticket['code'].'/print-ack', [
                'result' => 'failed',
                'message' => 'printer.jam',
            ])
            ->assertCreated()
            ->assertJsonPath('data.audit_action', 'ticket.print_failed');

        $this->assertSame($status, $ticket['model']->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ticket.print_failed',
            'entity_id' => $ticket['model']->id,
        ]);
    }

    public function test_device_cannot_ack_another_devices_ticket(): void
    {
        $ticket = $this->issuedKioskTicket();
        ['destination' => $destination] = $this->sellableCatalog();
        [, $other] = $this->activeDeviceToken($destination);

        $this->withToken($other)
            ->postJson('/api/v1/tickets/'.$ticket['code'].'/print-ack', [
                'result' => 'success',
            ])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'resource.not_found');
    }

    public function test_auditor_cannot_print_ack(): void
    {
        $ticket = $this->issuedAssistedTicket();
        $auditor = $this->userWithRole(RoleName::Auditor);
        $token = $auditor->createToken('phpunit')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/tickets/'.$ticket->ticket_code.'/print-ack', [
                'result' => 'success',
            ])
            ->assertForbidden();
    }

    public function test_ticket_officer_reprint_does_not_change_status(): void
    {
        $ticket = $this->issuedAssistedTicket();
        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $token = $officer->createToken('phpunit')->plainTextToken;
        $before = $ticket->status;

        $this->withToken($token)
            ->postJson('/api/v1/tickets/'.$ticket->ticket_code.'/print-ack', [
                'result' => 'success',
            ])
            ->assertCreated()
            ->assertJsonPath('data.audit_action', 'ticket.printed');

        $this->withToken($token)
            ->postJson('/api/v1/tickets/'.$ticket->ticket_code.'/print-ack', [
                'result' => 'success',
            ])
            ->assertCreated()
            ->assertJsonPath('data.audit_action', 'ticket.reprinted');

        $this->assertSame($before, $ticket->fresh()->status);
        $this->assertSame(1, Ticket::query()->where('ticket_code', $ticket->ticket_code)->count());
    }

    public function test_print_ack_rejects_client_money_fields(): void
    {
        $ticket = $this->issuedAssistedTicket();
        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $token = $officer->createToken('phpunit')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/tickets/'.$ticket->ticket_code.'/print-ack', [
                'result' => 'success',
                'amount' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'money.client_values_forbidden');
    }

    public function test_device_without_ticket_ability_cannot_ack(): void
    {
        $ticket = $this->issuedAssistedTicket();
        ['destination' => $destination] = $this->sellableCatalog();
        [, $token] = $this->activeDeviceToken($destination, [DeviceAbilities::HEARTBEAT]);

        $this->withToken($token)
            ->postJson('/api/v1/tickets/'.$ticket->ticket_code.'/print-ack', [
                'result' => 'success',
            ])
            ->assertForbidden();
    }

    /**
     * @return array{model: Ticket, code: string, token: string}
     */
    private function issuedKioskTicket(): array
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $this->openCashierShiftFor($officer);
        $staffToken = $officer->createToken('phpunit')->plainTextToken;
        [$device, $deviceToken] = $this->activeDeviceToken($destination);
        $order = $this->createPendingOrder($deviceToken, $destination, $adult, 'print-kiosk');
        $paymentId = $this->withIdempotency($staffToken, 'print-kiosk-init')
            ->postJson('/api/v1/orders/'.$order['id'].'/payments', [
                'method' => PaymentMethod::Cash->value,
            ])
            ->json('data.payment.id');
        $this->withIdempotency($staffToken, 'print-kiosk-pay')
            ->postJson("/api/v1/payments/{$paymentId}/confirm-cash", ['received' => true])
            ->assertOk();

        $model = Ticket::query()->where('order_id', $order['id'])->firstOrFail();
        $this->assertSame($device->id, $model->issued_by_device_id);

        return [
            'model' => $model,
            'code' => $model->ticket_code,
            'token' => $deviceToken,
        ];
    }

    private function issuedAssistedTicket(): Ticket
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $this->openCashierShiftFor($officer);
        $token = $officer->createToken('phpunit')->plainTextToken;
        $order = $this->createPendingOrder($token, $destination, $adult, 'print-staff-'.uniqid());
        $paymentId = $this->withIdempotency($token, 'print-staff-init-'.uniqid())
            ->postJson('/api/v1/orders/'.$order['id'].'/payments', [
                'method' => PaymentMethod::Cash->value,
            ])
            ->json('data.payment.id');
        $this->withIdempotency($token, 'print-staff-pay-'.uniqid())
            ->postJson("/api/v1/payments/{$paymentId}/confirm-cash", ['received' => true])
            ->assertOk();

        return Ticket::query()->where('order_id', $order['id'])->firstOrFail();
    }
}

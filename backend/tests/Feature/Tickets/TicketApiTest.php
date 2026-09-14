<?php

namespace Tests\Feature\Tickets;

use App\Enums\PaymentMethod;
use App\Enums\RoleName;
use App\Models\Ticket;
use App\Support\DeviceAbilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithCashierShifts;
use Tests\Concerns\InteractsWithOrders;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class TicketApiTest extends TestCase
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

    public function test_device_can_read_own_order_tickets_and_print_payload(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $this->openCashierShiftFor($officer);
        $staffToken = $officer->createToken('phpunit')->plainTextToken;

        [$device, $deviceToken] = $this->activeDeviceToken($destination);
        $order = $this->createPendingOrder($deviceToken, $destination, $adult, 'kiosk-tix');
        $paymentId = $this->withIdempotency($staffToken, 'kiosk-tix-init')
            ->postJson('/api/v1/orders/'.$order['id'].'/payments', [
                'method' => PaymentMethod::Cash->value,
            ])
            ->json('data.payment.id');

        $this->withIdempotency($staffToken, 'kiosk-tix-pay')
            ->postJson("/api/v1/payments/{$paymentId}/confirm-cash", ['received' => true])
            ->assertOk();

        $list = $this->withToken($deviceToken)
            ->getJson('/api/v1/orders/'.$order['id'].'/tickets');
        $list->assertOk()
            ->assertJsonPath('success', true);
        $this->assertCount(2, $list->json('data'));
        $code = $list->json('data.0.ticket_code');
        $this->assertNotEmpty($list->json('data.0.qr_payload'));

        $this->withToken($deviceToken)
            ->getJson('/api/v1/tickets/'.$code)
            ->assertOk()
            ->assertJsonPath('data.ticket_code', $code)
            ->assertJsonPath('data.order_id', $order['id']);

        $print = $this->withToken($deviceToken)
            ->getJson('/api/v1/tickets/'.$code.'/print-payload');
        $print->assertOk()
            ->assertJsonPath('data.ticket_code', $code)
            ->assertJsonPath('data.destination.id', $destination->id);
        $this->assertNotEmpty($print->json('data.qr_payload'));
        $this->assertSame($device->id, Ticket::query()->where('ticket_code', $code)->first()?->issued_by_device_id);
    }

    public function test_device_cannot_read_another_devices_ticket(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $this->openCashierShiftFor($officer);
        $staffToken = $officer->createToken('phpunit')->plainTextToken;
        [, $ownerToken] = $this->activeDeviceToken($destination);
        $order = $this->createPendingOrder($ownerToken, $destination, $adult, 'idor-tix');
        $paymentId = $this->withIdempotency($staffToken, 'idor-init')
            ->postJson('/api/v1/orders/'.$order['id'].'/payments', [
                'method' => PaymentMethod::Cash->value,
            ])
            ->json('data.payment.id');
        $this->withIdempotency($staffToken, 'idor-pay')
            ->postJson("/api/v1/payments/{$paymentId}/confirm-cash", ['received' => true])
            ->assertOk();

        $code = Ticket::query()->where('order_id', $order['id'])->value('ticket_code');
        [, $otherToken] = $this->activeDeviceToken($destination);

        $this->withToken($otherToken)
            ->getJson('/api/v1/tickets/'.$code)
            ->assertNotFound()
            ->assertJsonPath('error.code', 'resource.not_found');

        $this->withToken($otherToken)
            ->getJson('/api/v1/orders/'.$order['id'].'/tickets')
            ->assertNotFound();
    }

    public function test_auditor_can_view_ticket_without_qr_payload(): void
    {
        $ticket = $this->issuedAssistedTicket();
        $auditor = $this->userWithRole(RoleName::Auditor);
        $token = $auditor->createToken('phpunit')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/tickets/'.$ticket->ticket_code)
            ->assertOk()
            ->assertJsonPath('data.ticket_code', $ticket->ticket_code)
            ->assertJsonMissingPath('data.qr_payload');

        $this->withToken($token)
            ->getJson('/api/v1/tickets/'.$ticket->ticket_code.'/print-payload')
            ->assertForbidden();
    }

    public function test_ticket_officer_print_payload_includes_qr(): void
    {
        $ticket = $this->issuedAssistedTicket();
        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $token = $officer->createToken('phpunit')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/tickets/'.$ticket->ticket_code.'/print-payload')
            ->assertOk()
            ->assertJsonPath('data.ticket_code', $ticket->ticket_code)
            ->assertJsonPath('data.qr_payload', $ticket->qr_payload);
    }

    public function test_unknown_ticket_is_not_found(): void
    {
        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $token = $officer->createToken('phpunit')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/tickets/TCK-DOESNOTEXIST')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'resource.not_found');
    }

    public function test_there_is_no_public_ticket_mint_endpoint(): void
    {
        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $token = $officer->createToken('phpunit')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/tickets', ['order_id' => 1])
            ->assertNotFound();
    }

    public function test_device_without_ticket_ability_cannot_read(): void
    {
        $ticket = $this->issuedAssistedTicket();
        ['destination' => $destination] = $this->sellableCatalog();
        [, $token] = $this->activeDeviceToken($destination, [DeviceAbilities::HEARTBEAT]);

        $this->withToken($token)
            ->getJson('/api/v1/tickets/'.$ticket->ticket_code)
            ->assertForbidden();
    }

    private function issuedAssistedTicket(): Ticket
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $this->openCashierShiftFor($officer);
        $token = $officer->createToken('phpunit')->plainTextToken;
        $order = $this->createPendingOrder($token, $destination, $adult, 'staff-tix-'.uniqid());
        $paymentId = $this->withIdempotency($token, 'staff-tix-init-'.uniqid())
            ->postJson('/api/v1/orders/'.$order['id'].'/payments', [
                'method' => PaymentMethod::Cash->value,
            ])
            ->json('data.payment.id');
        $this->withIdempotency($token, 'staff-tix-pay-'.uniqid())
            ->postJson("/api/v1/payments/{$paymentId}/confirm-cash", ['received' => true])
            ->assertOk();

        return Ticket::query()->where('order_id', $order['id'])->firstOrFail();
    }
}

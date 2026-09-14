<?php

namespace Tests\Feature\Tickets;

use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Enums\TicketDenyCode;
use App\Enums\TicketStatus;
use App\Enums\TicketValidateResult;
use App\Models\Destination;
use App\Models\Gate;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Ticket;
use App\Services\Qr\QrPayloadGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithOrders;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class ValidateTicketTest extends TestCase
{
    use InteractsWithOrders;
    use InteractsWithRbac;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        config(['tickets.qr_secret' => 'test-ticket-qr-secret', 'tickets.qr_kid' => 'v1']);
    }

    public function test_valid_active_ticket_allows_without_status_change(): void
    {
        $ticket = $this->authenticTicket();
        $token = $this->gateToken();

        $this->withToken($token)
            ->postJson('/api/v1/tickets/validate', [
                'qr_payload' => $ticket->qr_payload,
                'destination_id' => $ticket->destination_id,
            ])
            ->assertOk()
            ->assertJsonPath('data.result', TicketValidateResult::Allow->value)
            ->assertJsonPath('data.reason_code', null)
            ->assertJsonPath('data.ticket.ticket_code', $ticket->ticket_code)
            ->assertJsonPath('data.ticket.status', TicketStatus::Active->value)
            ->assertJsonMissingPath('data.ticket.qr_payload');

        $this->assertSame(TicketStatus::Active, $ticket->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ticket.validated',
            'entity_id' => $ticket->id,
        ]);
    }

    public function test_forged_malformed_payload_is_invalid_auth(): void
    {
        $ticket = $this->authenticTicket();

        $this->withToken($this->gateToken())
            ->postJson('/api/v1/tickets/validate', [
                'qr_payload' => 'not-a-real-qr',
                'destination_id' => $ticket->destination_id,
            ])
            ->assertOk()
            ->assertJsonPath('data.result', TicketValidateResult::Deny->value)
            ->assertJsonPath('data.reason_code', TicketDenyCode::InvalidAuth->value)
            ->assertJsonPath('data.ticket', null);

        $this->assertSame(TicketStatus::Active, $ticket->fresh()->status);
    }

    public function test_unknown_well_formed_payload_is_not_found(): void
    {
        $ticket = $this->authenticTicket();

        $this->withToken($this->gateToken())
            ->postJson('/api/v1/tickets/validate', [
                'qr_payload' => 'WD1.v1.'.str_repeat('ab', 16).'.'.str_repeat('cd', 16),
                'destination_id' => $ticket->destination_id,
            ])
            ->assertOk()
            ->assertJsonPath('data.reason_code', TicketDenyCode::NotFound->value)
            ->assertJsonPath('data.ticket', null);
    }

    public function test_hmac_mismatch_is_invalid_auth(): void
    {
        $ticket = $this->authenticTicket();
        $ticket->forceFill(['ticket_code' => 'TCK-TAMPEREDCODE123'])->save();

        $this->withToken($this->gateToken())
            ->postJson('/api/v1/tickets/validate', [
                'qr_payload' => $ticket->qr_payload,
                'destination_id' => $ticket->destination_id,
            ])
            ->assertOk()
            ->assertJsonPath('data.reason_code', TicketDenyCode::InvalidAuth->value)
            ->assertJsonPath('data.ticket', null);
    }

    public function test_wrong_destination_is_denied(): void
    {
        $ticket = $this->authenticTicket();
        $other = Destination::factory()->create();

        $this->withToken($this->gateToken())
            ->postJson('/api/v1/tickets/validate', [
                'qr_payload' => $ticket->qr_payload,
                'destination_id' => $other->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.reason_code', TicketDenyCode::WrongDestination->value)
            ->assertJsonPath('data.ticket.ticket_code', $ticket->ticket_code);
    }

    public function test_unpaid_payment_is_denied(): void
    {
        $ticket = $this->authenticTicket(paid: false);

        $this->withToken($this->gateToken())
            ->postJson('/api/v1/tickets/validate', $this->payload($ticket))
            ->assertOk()
            ->assertJsonPath('data.reason_code', TicketDenyCode::NotPaid->value);

        $this->assertSame(TicketStatus::Active, $ticket->fresh()->status);
    }

    public function test_issued_ticket_is_not_active(): void
    {
        $ticket = $this->authenticTicket([
            'status' => TicketStatus::Issued,
            'activated_at' => null,
            'valid_start_at' => now()->addHour(),
            'valid_end_at' => now()->addHours(8),
        ]);

        $this->withToken($this->gateToken())
            ->postJson('/api/v1/tickets/validate', $this->payload($ticket))
            ->assertOk()
            ->assertJsonPath('data.reason_code', TicketDenyCode::NotActive->value);
    }

    public function test_clock_past_valid_end_is_expired(): void
    {
        $ticket = $this->authenticTicket([
            'status' => TicketStatus::Active,
            'valid_start_at' => now()->subDays(2),
            'valid_end_at' => now()->subSecond(),
        ]);

        $this->withToken($this->gateToken())
            ->postJson('/api/v1/tickets/validate', $this->payload($ticket))
            ->assertOk()
            ->assertJsonPath('data.reason_code', TicketDenyCode::Expired->value);

        $this->assertSame(TicketStatus::Expired, $ticket->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ticket.expired',
            'entity_id' => $ticket->id,
        ]);
    }

    public function test_cancelled_ticket_is_denied(): void
    {
        $ticket = $this->authenticTicket([
            'status' => TicketStatus::Cancelled,
            'cancelled_at' => now(),
        ]);

        $this->withToken($this->gateToken())
            ->postJson('/api/v1/tickets/validate', $this->payload($ticket))
            ->assertOk()
            ->assertJsonPath('data.reason_code', TicketDenyCode::Cancelled->value);
    }

    public function test_refunded_ticket_is_denied(): void
    {
        $ticket = $this->authenticTicket([
            'status' => TicketStatus::Refunded,
            'refunded_at' => now(),
        ]);

        $this->withToken($this->gateToken())
            ->postJson('/api/v1/tickets/validate', $this->payload($ticket))
            ->assertOk()
            ->assertJsonPath('data.reason_code', TicketDenyCode::Refunded->value);
    }

    public function test_used_ticket_is_already_used(): void
    {
        $ticket = $this->authenticTicket([
            'status' => TicketStatus::Used,
            'used_at' => now(),
        ]);

        $this->withToken($this->gateToken())
            ->postJson('/api/v1/tickets/validate', $this->payload($ticket))
            ->assertOk()
            ->assertJsonPath('data.reason_code', TicketDenyCode::AlreadyUsed->value);

        $this->assertSame(TicketStatus::Used, $ticket->fresh()->status);
    }

    public function test_inactive_gate_is_unauthorized(): void
    {
        $ticket = $this->authenticTicket();
        $gate = Gate::factory()->create([
            'destination_id' => $ticket->destination_id,
            'is_active' => false,
        ]);

        $this->withToken($this->gateToken())
            ->postJson('/api/v1/tickets/validate', [
                'qr_payload' => $ticket->qr_payload,
                'destination_id' => $ticket->destination_id,
                'gate_id' => $gate->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.reason_code', TicketDenyCode::Unauthorized->value);
    }

    public function test_ticket_officer_cannot_validate(): void
    {
        $ticket = $this->authenticTicket();
        $token = $this->userWithRole(RoleName::TicketOfficer)->createToken('phpunit')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/tickets/validate', $this->payload($ticket))
            ->assertForbidden()
            ->assertJsonPath('error.code', 'auth.forbidden');
    }

    public function test_device_cannot_validate(): void
    {
        $ticket = $this->authenticTicket();
        ['destination' => $destination] = $this->sellableCatalog();
        [, $token] = $this->activeDeviceToken($destination);

        $this->withToken($token)
            ->postJson('/api/v1/tickets/validate', $this->payload($ticket))
            ->assertForbidden();
    }

    public function test_unauthenticated_cannot_validate(): void
    {
        $ticket = $this->authenticTicket();

        $this->postJson('/api/v1/tickets/validate', $this->payload($ticket))
            ->assertUnauthorized();
    }

    public function test_client_ticket_status_is_rejected(): void
    {
        $ticket = $this->authenticTicket();

        $this->withToken($this->gateToken())
            ->postJson('/api/v1/tickets/validate', [
                ...$this->payload($ticket),
                'ticket_status' => 'active',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'money.client_values_forbidden');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function authenticTicket(array $overrides = [], bool $paid = true): Ticket
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        $order = Order::factory()->create([
            'destination_id' => $destination->id,
            'grand_total' => $adult->unit_price,
        ]);
        $item = $this->attachOrderLine($order, $adult);
        $payment = Payment::factory()->state([
            'order_id' => $order->id,
            'amount' => $order->grand_total,
            'status' => $paid ? PaymentStatus::Paid : PaymentStatus::Processing,
            'paid_at' => $paid ? now() : null,
        ])->create();

        $code = 'TCK-'.Str::ulid()->toString();
        $qr = $this->app->make(QrPayloadGenerator::class)->generate($code);

        return Ticket::factory()->create(array_merge([
            'ticket_code' => $code,
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'destination_id' => $destination->id,
            'ticket_type_id' => $adult->id,
            'payment_id' => $payment->id,
            'status' => TicketStatus::Active,
            'valid_start_at' => now()->subHour(),
            'valid_end_at' => now()->addHours(6),
            'issued_at' => now()->subHour(),
            'activated_at' => now()->subHour(),
            'qr_payload' => $qr->payload,
            'qr_payload_hash' => $qr->hash,
            'qr_version' => $qr->version,
            'qr_secret_hint' => $qr->secretHint,
        ], $overrides));
    }

    /**
     * @return array{qr_payload: string, destination_id: int}
     */
    private function payload(Ticket $ticket): array
    {
        return [
            'qr_payload' => $ticket->qr_payload,
            'destination_id' => $ticket->destination_id,
        ];
    }

    private function gateToken(): string
    {
        return $this->userWithRole(RoleName::GateOfficer)->createToken('phpunit')->plainTextToken;
    }
}

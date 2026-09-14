<?php

namespace Tests\Feature\CheckIns;

use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Enums\TicketDenyCode;
use App\Enums\TicketStatus;
use App\Enums\TicketValidateResult;
use App\Integrations\Gate\GateController;
use App\Models\CheckIn;
use App\Models\Destination;
use App\Models\Gate;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Ticket;
use App\Services\Qr\QrPayloadGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\InteractsWithOrders;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

/**
 * Sequential HTTP cannot emulate OS-level parallelism. Uniqueness is enforced by
 * lockForUpdate plus the check_ins unique index; test_unique_constraint_race_maps_to_already_used
 * is the stable concurrency stand-in on SQLite PHPUnit.
 */
#[Group('critical')]
class DoubleCheckInTest extends TestCase
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

    public function test_first_check_in_allows_and_sets_used(): void
    {
        $ticket = $this->authenticTicket();
        $gate = Gate::factory()->create([
            'destination_id' => $ticket->destination_id,
            'is_active' => true,
        ]);

        $gateOpen = Mockery::mock(GateController::class);
        $gateOpen->shouldReceive('open')->once()->with(
            Mockery::on(fn ($g) => $g instanceof Gate && (int) $g->id === (int) $gate->id),
            Mockery::type(CheckIn::class),
        );
        $this->app->instance(GateController::class, $gateOpen);

        $this->withToken($this->gateToken())
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/check-ins', [
                'qr_payload' => $ticket->qr_payload,
                'destination_id' => $ticket->destination_id,
                'gate_id' => $gate->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.result', TicketValidateResult::Allow->value)
            ->assertJsonPath('data.reason_code', null)
            ->assertJsonPath('data.ticket.code', $ticket->ticket_code)
            ->assertJsonPath('data.ticket.status', TicketStatus::Used->value)
            ->assertJsonPath('data.check_in.id', fn ($id) => is_int($id) && $id > 0);

        $fresh = $ticket->fresh();
        $this->assertSame(TicketStatus::Used, $fresh->status);
        $this->assertNotNull($fresh->used_at);
        $this->assertDatabaseHas('check_ins', [
            'ticket_id' => $ticket->id,
            'destination_id' => $ticket->destination_id,
            'gate_id' => $gate->id,
            'result' => 'allow',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ticket.checked_in',
            'entity_id' => $ticket->id,
        ]);
    }

    public function test_second_sequential_check_in_is_already_used(): void
    {
        $ticket = $this->authenticTicket();
        $token = $this->gateToken();
        $payload = $this->payload($ticket);

        $this->withToken($token)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/check-ins', $payload)
            ->assertOk()
            ->assertJsonPath('data.result', TicketValidateResult::Allow->value);

        $this->withToken($token)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/check-ins', $payload)
            ->assertOk()
            ->assertJsonPath('data.result', TicketValidateResult::Deny->value)
            ->assertJsonPath('data.reason_code', TicketDenyCode::AlreadyUsed->value)
            ->assertJsonPath('data.check_in', null);

        $this->assertSame(1, CheckIn::query()->where('ticket_id', $ticket->id)->count());
        $this->assertSame(TicketStatus::Used, $ticket->fresh()->status);
    }

    public function test_two_distinct_idempotency_keys_allow_exactly_once(): void
    {
        $ticket = $this->authenticTicket();
        $token = $this->gateToken();
        $payload = $this->payload($ticket);

        $first = $this->withToken($token)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/check-ins', $payload);

        $second = $this->withToken($token)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/check-ins', $payload);

        $results = [
            $first->json('data.result'),
            $second->json('data.result'),
        ];

        $this->assertSame(1, collect($results)->filter(fn ($r) => $r === TicketValidateResult::Allow->value)->count());
        $this->assertContains(TicketValidateResult::Deny->value, $results);
        $this->assertSame(1, CheckIn::query()->where('ticket_id', $ticket->id)->count());
        $this->assertSame(TicketStatus::Used, $ticket->fresh()->status);
    }

    public function test_unique_constraint_race_maps_to_already_used(): void
    {
        $ticket = $this->authenticTicket();

        CheckIn::factory()->create([
            'ticket_id' => $ticket->id,
            'destination_id' => $ticket->destination_id,
            'result' => 'allow',
            'checked_in_at' => now(),
        ]);

        $this->assertSame(TicketStatus::Active, $ticket->fresh()->status);

        $this->withToken($this->gateToken())
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/check-ins', $this->payload($ticket))
            ->assertOk()
            ->assertJsonPath('data.result', TicketValidateResult::Deny->value)
            ->assertJsonPath('data.reason_code', TicketDenyCode::AlreadyUsed->value);

        $this->assertSame(1, CheckIn::query()->where('ticket_id', $ticket->id)->count());
        $this->assertSame(TicketStatus::Active, $ticket->fresh()->status);
    }

    public function test_idempotent_replay_returns_same_allow(): void
    {
        $ticket = $this->authenticTicket();
        $key = (string) Str::uuid();
        $token = $this->gateToken();
        $payload = $this->payload($ticket);

        $first = $this->withToken($token)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/check-ins', $payload)
            ->assertOk()
            ->assertJsonPath('data.result', TicketValidateResult::Allow->value);

        $checkInId = $first->json('data.check_in.id');

        $this->withToken($token)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/check-ins', $payload)
            ->assertOk()
            ->assertJsonPath('data.result', TicketValidateResult::Allow->value)
            ->assertJsonPath('data.check_in.id', $checkInId)
            ->assertJsonPath('data.ticket.status', TicketStatus::Used->value);

        $this->assertSame(1, CheckIn::query()->where('ticket_id', $ticket->id)->count());
    }

    public function test_missing_idempotency_key_is_rejected(): void
    {
        $ticket = $this->authenticTicket();

        $this->withToken($this->gateToken())
            ->postJson('/api/v1/check-ins', $this->payload($ticket))
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation.failed');
    }

    public function test_ticket_officer_cannot_check_in(): void
    {
        $ticket = $this->authenticTicket();
        $token = $this->userWithRole(RoleName::TicketOfficer)->createToken('phpunit')->plainTextToken;

        $this->withToken($token)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/check-ins', $this->payload($ticket))
            ->assertForbidden()
            ->assertJsonPath('error.code', 'auth.forbidden');

        $this->assertSame(TicketStatus::Active, $ticket->fresh()->status);
        $this->assertDatabaseMissing('check_ins', ['ticket_id' => $ticket->id]);
    }

    public function test_device_cannot_check_in(): void
    {
        $ticket = $this->authenticTicket();
        ['destination' => $destination] = $this->sellableCatalog();
        [, $token] = $this->activeDeviceToken($destination);

        $this->withToken($token)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/check-ins', $this->payload($ticket))
            ->assertForbidden();
    }

    public function test_expired_ticket_is_denied_without_check_in(): void
    {
        $ticket = $this->authenticTicket([
            'status' => TicketStatus::Active,
            'valid_start_at' => now()->subDays(2),
            'valid_end_at' => now()->subSecond(),
        ]);

        $this->withToken($this->gateToken())
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/check-ins', $this->payload($ticket))
            ->assertOk()
            ->assertJsonPath('data.reason_code', TicketDenyCode::Expired->value)
            ->assertJsonPath('data.check_in', null);

        $this->assertSame(TicketStatus::Expired, $ticket->fresh()->status);
        $this->assertDatabaseMissing('check_ins', ['ticket_id' => $ticket->id]);
    }

    public function test_wrong_destination_is_denied(): void
    {
        $ticket = $this->authenticTicket();
        $other = Destination::factory()->create();

        $this->withToken($this->gateToken())
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/check-ins', [
                'qr_payload' => $ticket->qr_payload,
                'destination_id' => $other->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.reason_code', TicketDenyCode::WrongDestination->value);

        $this->assertDatabaseMissing('check_ins', ['ticket_id' => $ticket->id]);
    }

    public function test_client_ticket_status_is_rejected(): void
    {
        $ticket = $this->authenticTicket();

        $this->withToken($this->gateToken())
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/check-ins', [
                ...$this->payload($ticket),
                'ticket_status' => 'used',
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

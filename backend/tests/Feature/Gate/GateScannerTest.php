<?php

namespace Tests\Feature\Gate;

use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Enums\TicketDenyCode;
use App\Enums\TicketStatus;
use App\Enums\TicketValidateResult;
use App\Livewire\Gate\CheckInPanel;
use App\Models\CheckIn;
use App\Models\Gate;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Ticket;
use App\Services\Qr\QrPayloadGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\InteractsWithOrders;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

#[Group('critical')]
class GateScannerTest extends TestCase
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

    public function test_hid_scan_submits_payload_and_allows_once(): void
    {
        $ticket = $this->activeTicket();
        $gate = Gate::factory()->create([
            'destination_id' => $ticket->destination_id,
            'is_active' => true,
        ]);
        $officer = $this->userWithRole(RoleName::GateOfficer);

        $this->actingAs($officer)
            ->get(route('dashboard.check-in'))
            ->assertOk()
            ->assertSee('Scan QR')
            ->assertSee('Scanner tidak siap')
            ->assertDontSee('Buka gerbang', false);

        Livewire::actingAs($officer)
            ->test(CheckInPanel::class)
            ->assertSee('id="gate-scan-input"', false)
            ->set('destinationId', $ticket->destination_id)
            ->set('gateId', $gate->id)
            ->set('qrPayload', $ticket->qr_payload."\r")
            ->call('submitCheckIn')
            ->assertSet('result', TicketValidateResult::Allow->value)
            ->assertSet('reasonCode', '')
            ->assertSet('qrPayload', '')
            ->assertSee('ALLOW')
            ->assertSee('Check-in berhasil');

        $this->assertSame(1, CheckIn::query()->where('ticket_id', $ticket->id)->count());
        $this->assertSame(TicketStatus::Used, $ticket->fresh()->status);
    }

    public function test_manual_ticket_code_works_when_scanner_unavailable(): void
    {
        $ticket = $this->activeTicket();
        $officer = $this->userWithRole(RoleName::GateOfficer);

        Livewire::actingAs($officer)
            ->test(CheckInPanel::class)
            ->call('useManualEntry')
            ->assertSet('entryMode', 'manual')
            ->assertSee('Kode tiket')
            ->set('destinationId', $ticket->destination_id)
            ->set('qrPayload', strtolower($ticket->ticket_code))
            ->call('submitCheckIn')
            ->assertSet('result', TicketValidateResult::Allow->value)
            ->assertSet('ticketCode', $ticket->ticket_code);

        $this->assertSame(1, CheckIn::query()->where('ticket_id', $ticket->id)->count());
    }

    public function test_rapid_double_scan_yields_single_use(): void
    {
        $ticket = $this->activeTicket();
        $officer = $this->userWithRole(RoleName::GateOfficer);
        $payload = $ticket->qr_payload;

        Livewire::actingAs($officer)
            ->test(CheckInPanel::class)
            ->set('destinationId', $ticket->destination_id)
            ->set('qrPayload', $payload)
            ->call('submitCheckIn')
            ->assertSet('result', TicketValidateResult::Allow->value)
            ->set('qrPayload', $payload)
            ->call('submitCheckIn')
            ->assertSet('result', TicketValidateResult::Deny->value)
            ->assertSet('reasonCode', TicketDenyCode::AlreadyUsed->value)
            ->assertSee('Tiket sudah digunakan sebelumnya.');

        $this->assertSame(1, CheckIn::query()->where('ticket_id', $ticket->id)->count());
        $this->assertSame(TicketStatus::Used, $ticket->fresh()->status);
    }

    public function test_in_flight_lock_ignores_second_submit(): void
    {
        $ticket = $this->activeTicket();
        $officer = $this->userWithRole(RoleName::GateOfficer);

        Livewire::actingAs($officer)
            ->test(CheckInPanel::class)
            ->set('destinationId', $ticket->destination_id)
            ->set('qrPayload', $ticket->qr_payload)
            ->set('busy', true)
            ->call('submitCheckIn')
            ->assertSet('result', '');

        $this->assertSame(0, CheckIn::query()->count());
        $this->assertSame(TicketStatus::Active, $ticket->fresh()->status);
    }

    public function test_gibberish_scan_is_server_deny_not_local_allow(): void
    {
        $ticket = $this->activeTicket();
        $officer = $this->userWithRole(RoleName::GateOfficer);

        Livewire::actingAs($officer)
            ->test(CheckInPanel::class)
            ->set('destinationId', $ticket->destination_id)
            ->set('qrPayload', '%%%not-a-real-qr%%%')
            ->call('submitCheckIn')
            ->assertSet('result', TicketValidateResult::Deny->value)
            ->assertSet('reasonCode', TicketDenyCode::InvalidAuth->value)
            ->assertSee('DENY')
            ->assertDontSee('Check-in berhasil');

        $this->assertSame(0, CheckIn::query()->count());
        $this->assertSame(TicketStatus::Active, $ticket->fresh()->status);
    }

    public function test_missing_destination_does_not_check_in(): void
    {
        $ticket = $this->activeTicket();
        $officer = $this->userWithRole(RoleName::GateOfficer);

        Livewire::actingAs($officer)
            ->test(CheckInPanel::class)
            ->set('qrPayload', $ticket->qr_payload)
            ->call('submitCheckIn')
            ->assertSet('result', '')
            ->assertSee('Pilih destinasi');

        $this->assertSame(0, CheckIn::query()->count());
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function activeTicket(array $overrides = []): Ticket
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        $order = Order::factory()->create([
            'destination_id' => $destination->id,
            'grand_total' => $adult->unit_price,
        ]);
        $item = $this->attachOrderLine($order, $adult);
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => $order->grand_total,
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);
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
}

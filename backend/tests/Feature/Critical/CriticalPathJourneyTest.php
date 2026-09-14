<?php

namespace Tests\Feature\Critical;

use App\Actions\Tickets\IssueTickets;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Enums\TicketDenyCode;
use App\Enums\TicketStatus;
use App\Enums\TicketValidateResult;
use App\Models\CheckIn;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\AssertsApiEnvelope;
use Tests\Concerns\InteractsWithOrders;
use Tests\Concerns\InteractsWithPaymentWebhooks;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

/**
 * TASK-029 regression journey: money authority, idempotency, tickets, check-in, refund, IDOR.
 * Laravel remains the only ledger; this test only asserts server results.
 */
#[Group('critical')]
class CriticalPathJourneyTest extends TestCase
{
    use AssertsApiEnvelope;
    use InteractsWithOrders;
    use InteractsWithPaymentWebhooks;
    use InteractsWithRbac;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        config([
            'payments.webhook_secret' => $this->webhookSecret(),
            'tickets.qr_secret' => 'test-ticket-qr-secret',
            'tickets.qr_kid' => 'v1',
        ]);
    }

    public function test_quote_order_pay_issue_checkin_refund_and_idor_invariants(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        [$device, $token] = $this->activeDeviceToken($destination);
        $payload = $this->twoAdultPayload($destination, $adult);
        $officerToken = $this->userWithRole(RoleName::TicketOfficer)->createToken('phpunit')->plainTextToken;

        $deviceQuote = $this->withToken($token)->postJson('/api/v1/pricing/quote', $payload);
        $this->assertSuccessEnvelope($deviceQuote, 200);
        $deviceQuote->assertJsonPath('data.grand_total', 103000)
            ->assertJsonPath('data.currency', 'IDR');

        $officerQuote = $this->withToken($officerToken)->postJson('/api/v1/pricing/quote', $payload);
        $this->assertSuccessEnvelope($officerQuote, 200);
        $this->assertSame(
            $deviceQuote->json('data.grand_total'),
            $officerQuote->json('data.grand_total'),
        );

        $this->assertErrorEnvelope(
            $this->withToken($token)->postJson('/api/v1/pricing/quote', [
                ...$payload,
                'grand_total' => 1,
            ]),
            422,
            'money.client_values_forbidden',
        );

        $order = $this->withIdempotency($token, 'crit-order')
            ->postJson('/api/v1/orders', $payload);
        $this->assertSuccessEnvelope($order, 201);
        $orderId = (int) $order->json('data.id');
        $grandTotal = (int) $order->json('data.grand_total');
        $this->assertSame(103000, $grandTotal);

        $replay = $this->withIdempotency($token, 'crit-order')
            ->postJson('/api/v1/orders', $payload);
        $this->assertSuccessEnvelope($replay, 201);
        $this->assertSame($orderId, (int) $replay->json('data.id'));
        $this->assertSame(1, Order::query()->count());

        $this->assertErrorEnvelope(
            $this->withIdempotency($token, 'crit-pay-money')
                ->postJson("/api/v1/orders/{$orderId}/payments", [
                    'method' => PaymentMethod::EWallet->value,
                    'amount' => 1,
                ]),
            422,
            'money.client_values_forbidden',
        );
        $this->assertSame(0, Payment::query()->count());

        $pay = $this->withIdempotency($token, 'crit-pay')
            ->postJson("/api/v1/orders/{$orderId}/payments", [
                'method' => PaymentMethod::EWallet->value,
            ]);
        $this->assertSuccessEnvelope($pay, 201);
        $pay->assertJsonPath('data.payment.amount', $grandTotal)
            ->assertJsonPath('data.payment.status', PaymentStatus::Processing->value);

        $paymentId = (int) $pay->json('data.payment.id');
        $paymentNumber = (string) $pay->json('data.payment.payment_number');

        [, $foreignToken] = $this->activeDeviceToken($destination);
        $this->assertErrorEnvelope(
            $this->withToken($foreignToken)->getJson("/api/v1/orders/{$orderId}"),
            404,
            'resource.not_found',
        );
        $this->assertSame($device->id, Order::query()->findOrFail($orderId)->device_id);

        $webhook = [
            'event_id' => 'evt_crit_paid',
            'type' => 'payment.paid',
            'payment_number' => $paymentNumber,
            'provider_payment_id' => Payment::query()->findOrFail($paymentId)->provider_payment_id,
        ];
        $this->postSandboxWebhook($webhook)->assertOk()->assertJsonPath('success', true);
        $this->postSandboxWebhook($webhook)->assertOk()->assertJsonPath('success', true);

        $this->assertSame(1, PaymentWebhookEvent::query()->count());
        $this->assertSame(PaymentStatus::Paid, Payment::query()->findOrFail($paymentId)->status);
        $this->assertSame(OrderStatus::Paid, Order::query()->findOrFail($orderId)->status);
        $this->assertSame(2, Ticket::query()->where('order_id', $orderId)->count());

        $issuedAgain = $this->app->make(IssueTickets::class)
            ->handle(Payment::query()->findOrFail($paymentId));
        $this->assertCount(2, $issuedAgain);
        $this->assertSame(2, Ticket::query()->where('order_id', $orderId)->count());

        $tickets = $this->withToken($token)->getJson("/api/v1/orders/{$orderId}/tickets");
        $this->assertSuccessEnvelope($tickets, 200);
        $tickets->assertJsonCount(2, 'data');
        $ticketId = (int) $tickets->json('data.0.id');
        $qr = (string) Ticket::query()->findOrFail($ticketId)->qr_payload;

        $gateToken = $this->userWithRole(RoleName::GateOfficer)->createToken('phpunit')->plainTextToken;
        $checkInBody = [
            'qr_payload' => $qr,
            'destination_id' => $destination->id,
        ];

        $this->withToken($gateToken)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/check-ins', $checkInBody)
            ->assertOk()
            ->assertJsonPath('data.result', TicketValidateResult::Allow->value);

        $this->withToken($gateToken)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/check-ins', $checkInBody)
            ->assertOk()
            ->assertJsonPath('data.result', TicketValidateResult::Deny->value)
            ->assertJsonPath('data.reason_code', TicketDenyCode::AlreadyUsed->value);

        $ticketId = (int) $tickets->json('data.0.id');
        $this->assertSame(1, CheckIn::query()->where('ticket_id', $ticketId)->count());
        $this->assertSame(TicketStatus::Used, Ticket::query()->findOrFail($ticketId)->status);

        $financeToken = $this->userWithRole(RoleName::Finance)->createToken('phpunit')->plainTextToken;
        $this->assertErrorEnvelope(
            $this->withIdempotency($financeToken, 'crit-refund-used')
                ->postJson("/api/v1/payments/{$paymentId}/refund", [
                    'reason' => 'Too late after entry',
                ]),
            422,
            'refund.ineligible',
        );
        $this->assertSame(PaymentStatus::Paid, Payment::query()->findOrFail($paymentId)->status);

        $this->withIdempotency($officerToken, 'crit-refund-deny')
            ->postJson("/api/v1/payments/{$paymentId}/refund", [
                'reason' => 'Officer must not refund',
            ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'auth.forbidden');
    }
}

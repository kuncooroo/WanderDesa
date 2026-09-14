<?php

namespace Tests\Feature\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\WebhookProcessStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithOrders;
use Tests\Concerns\InteractsWithPaymentWebhooks;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    use InteractsWithOrders;
    use InteractsWithPaymentWebhooks;
    use InteractsWithRbac;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        config(['payments.webhook_secret' => $this->webhookSecret()]);
    }

    public function test_valid_paid_webhook_marks_payment_and_order_paid_once(): void
    {
        $payment = $this->processingDigitalPayment();

        $payload = [
            'event_id' => 'evt_paid_1',
            'type' => 'payment.paid',
            'payment_number' => $payment->payment_number,
            'provider_payment_id' => $payment->provider_payment_id,
        ];

        $this->postSandboxWebhook($payload)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.received', true);

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame(OrderStatus::Paid, $payment->order()->first()?->status);
        $this->assertSame(1, Payment::query()->where('status', PaymentStatus::Paid->value)->count());
        $this->assertSame(2, Ticket::query()->count());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'payment.paid',
            'actor_type' => 'system',
            'entity_id' => $payment->id,
        ]);
        $this->assertDatabaseHas('payment_webhook_events', [
            'event_id' => 'evt_paid_1',
            'process_status' => WebhookProcessStatus::Processed->value,
            'payment_id' => $payment->id,
        ]);
    }

    public function test_duplicate_event_id_is_safe(): void
    {
        $payment = $this->processingDigitalPayment();
        $payload = [
            'event_id' => 'evt_dup_1',
            'type' => 'payment.paid',
            'payment_number' => $payment->payment_number,
        ];

        $this->postSandboxWebhook($payload)->assertOk();
        $this->postSandboxWebhook($payload)
            ->assertOk()
            ->assertJsonPath('data.received', true);

        $this->assertSame(1, PaymentWebhookEvent::query()->count());
        $this->assertSame(1, Payment::query()->where('status', PaymentStatus::Paid->value)->count());
        $this->assertSame(1, Order::query()->where('status', OrderStatus::Paid->value)->count());
        $this->assertSame(2, Ticket::query()->count());
        $this->assertSame(1, PaymentWebhookEvent::query()->where('process_status', WebhookProcessStatus::Processed->value)->count());
    }

    public function test_duplicate_event_id_with_different_payload_conflicts(): void
    {
        $payment = $this->processingDigitalPayment();

        $this->postSandboxWebhook([
            'event_id' => 'evt_conflict',
            'type' => 'payment.paid',
            'payment_number' => $payment->payment_number,
        ])->assertOk();

        $this->postSandboxWebhook([
            'event_id' => 'evt_conflict',
            'type' => 'payment.failed',
            'payment_number' => $payment->payment_number,
        ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'idempotency.payload_conflict');

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $payment = $this->processingDigitalPayment();

        $this->postSandboxWebhook(
            [
                'event_id' => 'evt_bad_sig',
                'type' => 'payment.paid',
                'payment_number' => $payment->payment_number,
            ],
            signature: 'deadbeef',
        )
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'webhook.signature_invalid');

        $this->assertSame(PaymentStatus::Processing, $payment->fresh()->status);
        $this->assertSame(0, PaymentWebhookEvent::query()->count());
    }

    public function test_missing_signature_is_rejected(): void
    {
        $this->postJson('/api/v1/webhooks/payments/sandbox', [
            'event_id' => 'evt_none',
            'type' => 'payment.paid',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'webhook.signature_invalid');
    }

    public function test_empty_webhook_secret_fails_closed(): void
    {
        config(['payments.webhook_secret' => '']);
        $payment = $this->processingDigitalPayment();

        $this->postSandboxWebhook([
            'event_id' => 'evt_nosecret',
            'type' => 'payment.paid',
            'payment_number' => $payment->payment_number,
        ], signature: 'anything')
            ->assertUnauthorized();

        $this->assertSame(PaymentStatus::Processing, $payment->fresh()->status);
    }

    public function test_unknown_payment_is_ignored(): void
    {
        $this->postSandboxWebhook([
            'event_id' => 'evt_unknown',
            'type' => 'payment.paid',
            'payment_number' => 'PAY-does-not-exist',
        ])
            ->assertOk()
            ->assertJsonPath('data.received', true);

        $this->assertDatabaseHas('payment_webhook_events', [
            'event_id' => 'evt_unknown',
            'process_status' => WebhookProcessStatus::Ignored->value,
        ]);
        $this->assertSame(0, Payment::query()->where('status', PaymentStatus::Paid->value)->count());
    }

    public function test_failed_webhook_marks_payment_failed_without_paying_order(): void
    {
        $payment = $this->processingDigitalPayment();

        $this->postSandboxWebhook([
            'event_id' => 'evt_fail',
            'type' => 'payment.failed',
            'payment_number' => $payment->payment_number,
        ])->assertOk();

        $this->assertSame(PaymentStatus::Failed, $payment->fresh()->status);
        $this->assertSame(OrderStatus::PendingPayment, $payment->order()->first()?->status);
        $this->assertSame(0, Ticket::query()->count());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'payment.failed',
            'entity_id' => $payment->id,
        ]);
    }

    public function test_unknown_provider_is_not_found(): void
    {
        $raw = json_encode(['event_id' => 'evt_x', 'type' => 'payment.paid'], JSON_THROW_ON_ERROR);

        $this->withHeaders([
            'X-WanderDesa-Webhook-Signature' => hash_hmac('sha256', $raw, $this->webhookSecret()),
        ])->postJson('/api/v1/webhooks/payments/midtrans', [
            'event_id' => 'evt_x',
            'type' => 'payment.paid',
        ])->assertNotFound();
    }

    public function test_webhook_does_not_require_bearer_token(): void
    {
        $payment = $this->processingDigitalPayment();

        $this->postSandboxWebhook([
            'event_id' => 'evt_no_auth',
            'type' => 'payment.paid',
            'payment_number' => $payment->payment_number,
        ])->assertOk();

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
    }

    public function test_reported_amount_mismatch_does_not_mark_paid(): void
    {
        $payment = $this->processingDigitalPayment();

        $this->postSandboxWebhook([
            'event_id' => 'evt_amt',
            'type' => 'payment.paid',
            'payment_number' => $payment->payment_number,
            'amount' => 1,
        ])->assertOk();

        $this->assertSame(PaymentStatus::Processing, $payment->fresh()->status);
    }

    private function processingDigitalPayment(): Payment
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        $order = Order::factory()->create([
            'destination_id' => $destination->id,
            'status' => OrderStatus::PendingPayment,
            'grand_total' => 103000,
        ]);
        $this->attachOrderLine($order, $adult, 2);

        return Payment::factory()->processing()->create([
            'order_id' => $order->id,
            'method' => PaymentMethod::EWallet,
            'provider' => 'sandbox',
            'amount' => 103000,
            'provider_payment_id' => 'sandbox-PAY-TEST1',
            'payment_number' => 'PAY-TEST1',
        ]);
    }
}

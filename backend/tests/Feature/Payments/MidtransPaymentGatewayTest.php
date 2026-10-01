<?php

namespace Tests\Feature\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Enums\TicketStatus;
use App\Enums\WebhookProcessStatus;
use App\Integrations\Payments\MidtransPaymentGateway;
use App\Integrations\Payments\PaymentGateway;
use App\Models\Payment;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithOrders;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class MidtransPaymentGatewayTest extends TestCase
{
    use InteractsWithOrders;
    use InteractsWithRbac;
    use RefreshDatabase;

    private string $serverKey = 'SB-Mid-server-testkey';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();

        config([
            'payments.gateway' => 'midtrans',
            'payments.midtrans.server_key' => $this->serverKey,
            'payments.midtrans.client_key' => 'SB-Mid-client-test',
            'payments.midtrans.is_production' => false,
        ]);

        $this->app->forgetInstance(PaymentGateway::class);
        $this->app->bind(PaymentGateway::class, MidtransPaymentGateway::class);
    }

    public function test_digital_initiate_charges_midtrans_qris_and_returns_qr_string(): void
    {
        Http::fake([
            'api.sandbox.midtrans.com/v2/charge' => Http::response([
                'status_code' => '201',
                'status_message' => 'QRIS transaction is created',
                'transaction_id' => 'txn-midtrans-1',
                'order_id' => 'will-be-overwritten',
                'gross_amount' => '103000.00',
                'payment_type' => 'qris',
                'transaction_status' => 'pending',
                'qr_string' => '00020101021226620014COM.GO-JEK.WWW0119TEST',
                'expiry_time' => '2026-09-29 22:30:00',
            ], 201),
        ]);

        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        [, $token] = $this->activeDeviceToken($destination);
        $order = $this->createPendingOrder($token, $destination, $adult);

        $response = $this->withIdempotency($token, 'midtrans-pay-1')
            ->postJson('/api/v1/orders/'.$order['id'].'/payments', [
                'method' => PaymentMethod::Qris->value,
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payment.status', PaymentStatus::Processing->value)
            ->assertJsonPath('data.payment.provider', 'midtrans')
            ->assertJsonPath('data.next_action.type', 'display_qr')
            ->assertJsonPath('data.next_action.qr_content', '00020101021226620014COM.GO-JEK.WWW0119TEST');

        $paymentNumber = $response->json('data.payment.payment_number');

        $this->assertDatabaseHas('payments', [
            'order_id' => $order['id'],
            'provider' => 'midtrans',
            'provider_payment_id' => 'txn-midtrans-1',
            'provider_reference' => $paymentNumber,
            'status' => PaymentStatus::Processing->value,
        ]);

        Http::assertSent(function ($request) use ($paymentNumber): bool {
            $data = $request->data();

            return $request->url() === 'https://api.sandbox.midtrans.com/v2/charge'
                && ($data['payment_type'] ?? null) === 'qris'
                && ($data['transaction_details']['order_id'] ?? null) === $paymentNumber
                && (int) ($data['transaction_details']['gross_amount'] ?? 0) === 103000;
        });
    }

    public function test_valid_settlement_webhook_marks_payment_paid(): void
    {
        $payment = $this->seedProcessingMidtransPayment();

        $payload = [
            'transaction_time' => '2026-09-29 21:00:00',
            'transaction_status' => 'settlement',
            'transaction_id' => $payment->provider_payment_id,
            'status_message' => 'midtrans payment notification',
            'status_code' => '200',
            'payment_type' => 'qris',
            'order_id' => $payment->payment_number,
            'merchant_id' => 'G000',
            'gross_amount' => '103000.00',
            'currency' => 'IDR',
            'fraud_status' => 'accept',
        ];
        $payload['signature_key'] = $this->midtransSignature($payload);

        $this->postJson('/api/v1/webhooks/payments/midtrans', $payload)
            ->assertOk()
            ->assertJsonPath('data.received', true);

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame(OrderStatus::Paid, $payment->order()->first()?->status);
        $this->assertSame(2, Ticket::query()->count());
        $this->assertDatabaseHas('payment_webhook_events', [
            'provider' => 'midtrans',
            'process_status' => WebhookProcessStatus::Processed->value,
            'payment_id' => $payment->id,
        ]);
    }

    public function test_invalid_midtrans_signature_is_rejected(): void
    {
        $payment = $this->seedProcessingMidtransPayment();

        $payload = [
            'transaction_status' => 'settlement',
            'transaction_id' => $payment->provider_payment_id,
            'status_code' => '200',
            'order_id' => $payment->payment_number,
            'gross_amount' => '103000.00',
            'signature_key' => 'deadbeef',
        ];

        $this->postJson('/api/v1/webhooks/payments/midtrans', $payload)
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'webhook.signature_invalid');

        $this->assertSame(PaymentStatus::Processing, $payment->fresh()->status);
        $this->assertDatabaseHas('payment_webhook_events', [
            'provider' => 'midtrans',
            'process_status' => WebhookProcessStatus::Rejected->value,
        ]);
    }

    public function test_legacy_digital_is_stored_as_qris(): void
    {
        Http::fake([
            'api.sandbox.midtrans.com/v2/charge' => Http::response([
                'status_code' => '201',
                'transaction_id' => 'txn-digital-1',
                'qr_string' => '00020101021226620014COM.GO-JEK.WWW0119DIGITAL',
                'transaction_status' => 'pending',
            ], 201),
        ]);

        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        [, $token] = $this->activeDeviceToken($destination);
        $order = $this->createPendingOrder($token, $destination, $adult);

        $this->withIdempotency($token, 'midtrans-digital-1')
            ->postJson('/api/v1/orders/'.$order['id'].'/payments', [
                'method' => 'digital',
            ])
            ->assertCreated()
            ->assertJsonPath('data.payment.method', PaymentMethod::Qris->value);

        Http::assertSent(fn ($request): bool => ($request->data()['payment_type'] ?? null) === 'qris');
    }

    public function test_midtrans_rejects_debit_without_charging_qris(): void
    {
        Http::fake();

        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        [, $token] = $this->activeDeviceToken($destination);
        $order = $this->createPendingOrder($token, $destination, $adult);

        $this->withIdempotency($token, 'midtrans-debit-1')
            ->postJson('/api/v1/orders/'.$order['id'].'/payments', [
                'method' => PaymentMethod::Debit->value,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'payment.method_unavailable');

        $this->assertDatabaseMissing('payments', [
            'order_id' => $order['id'],
        ]);
        Http::assertNothingSent();
    }

    public function test_refresh_queries_midtrans_and_marks_paid(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        [, $token] = $this->activeDeviceToken($destination);
        $orderPayload = $this->createPendingOrder($token, $destination, $adult);

        Http::fake([
            'api.sandbox.midtrans.com/v2/charge' => Http::response([
                'status_code' => '201',
                'transaction_id' => 'txn-refresh-1',
                'qr_string' => '00020101021226620014COM.GO-JEK.WWW0119REFRESH',
                'transaction_status' => 'pending',
            ], 201),
        ]);

        $created = $this->withIdempotency($token, 'midtrans-refresh-seed')
            ->postJson('/api/v1/orders/'.$orderPayload['id'].'/payments', [
                'method' => PaymentMethod::Qris->value,
            ])
            ->assertCreated();

        $paymentId = $created->json('data.payment.id');
        $paymentNumber = $created->json('data.payment.payment_number');

        Http::fake([
            'api.sandbox.midtrans.com/v2/'.$paymentNumber.'/status' => Http::response([
                'status_code' => '200',
                'transaction_status' => 'settlement',
                'fraud_status' => 'accept',
                'gross_amount' => '103000.00',
                'order_id' => $paymentNumber,
            ], 200),
        ]);

        $this->withToken($token)
            ->postJson('/api/v1/payments/'.$paymentId.'/refresh')
            ->assertOk()
            ->assertJsonPath('data.payment.status', PaymentStatus::Paid->value);

        $this->assertSame(PaymentStatus::Paid, Payment::query()->findOrFail($paymentId)->status);
    }

    public function test_charge_failure_marks_payment_failed(): void
    {
        Http::fake([
            'api.sandbox.midtrans.com/v2/charge' => Http::response([
                'status_code' => '500',
                'status_message' => 'Internal Server Error',
            ], 500),
        ]);

        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        [, $token] = $this->activeDeviceToken($destination);
        $order = $this->createPendingOrder($token, $destination, $adult);

        $this->withIdempotency($token, 'midtrans-fail-1')
            ->postJson('/api/v1/orders/'.$order['id'].'/payments', [
                'method' => PaymentMethod::Qris->value,
            ])
            ->assertStatus(502);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order['id'],
            'status' => PaymentStatus::Failed->value,
            'failure_code' => 'upstream.payment_provider_unavailable',
        ]);
    }

    public function test_cancel_voids_midtrans_charge_before_local_cancel(): void
    {
        ['payment' => $payment, 'token' => $token, 'orderId' => $orderId] = $this->seedOpenMidtransPayment('midtrans-cancel-1');

        Http::fake([
            'api.sandbox.midtrans.com/v2/'.$payment->payment_number.'/cancel' => Http::response([
                'status_code' => '200',
                'transaction_status' => 'cancel',
                'order_id' => $payment->payment_number,
            ], 200),
        ]);

        $this->withToken($token)
            ->postJson('/api/v1/orders/'.$orderId.'/cancel', ['reason' => 'visitor left'])
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::Cancelled->value);

        $this->assertSame(PaymentStatus::Cancelled, $payment->fresh()->status);
        Http::assertSent(function ($request) use ($payment): bool {
            return $request->method() === 'POST'
                && $request->url() === 'https://api.sandbox.midtrans.com/v2/'.$payment->payment_number.'/cancel';
        });
    }

    public function test_cancel_after_settlement_marks_paid_instead_of_dropping_the_charge(): void
    {
        ['payment' => $payment, 'token' => $token, 'orderId' => $orderId] = $this->seedOpenMidtransPayment('midtrans-cancel-paid');

        Http::fake([
            'api.sandbox.midtrans.com/v2/'.$payment->payment_number.'/cancel' => Http::response([
                'status_code' => '412',
                'status_message' => 'Transaction status cannot be updated.',
            ], 200),
            'api.sandbox.midtrans.com/v2/'.$payment->payment_number.'/status' => Http::response([
                'status_code' => '200',
                'transaction_status' => 'settlement',
                'fraud_status' => 'accept',
                'gross_amount' => '103000.00',
                'order_id' => $payment->payment_number,
            ], 200),
        ]);

        $this->withToken($token)
            ->postJson('/api/v1/orders/'.$orderId.'/cancel')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'order.state_conflict');

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame(OrderStatus::Paid, $payment->order()->first()?->status);
        $this->assertSame(2, Ticket::query()->count());
    }

    public function test_capture_denied_by_fraud_check_is_not_marked_paid(): void
    {
        $payment = $this->seedProcessingMidtransPayment();
        $payload = [
            'transaction_status' => 'capture',
            'fraud_status' => 'deny',
            'transaction_id' => $payment->provider_payment_id,
            'status_code' => '202',
            'order_id' => $payment->payment_number,
            'gross_amount' => '103000.00',
        ];
        $payload['signature_key'] = $this->midtransSignature($payload);

        $this->postJson('/api/v1/webhooks/payments/midtrans', $payload)
            ->assertOk();

        $this->assertSame(PaymentStatus::Failed, $payment->fresh()->status);
        $this->assertSame(0, Ticket::query()->count());
    }

    public function test_http_200_refund_rejection_does_not_void_tickets(): void
    {
        $payment = $this->settleMidtransPayment($this->seedProcessingMidtransPayment());
        $this->assertSame(2, Ticket::query()->count());

        Http::fake([
            'api.sandbox.midtrans.com/*' => Http::response([
                'status_code' => '412',
                'status_message' => 'Merchant cannot modify the status of the transaction',
            ], 200),
        ]);

        $finance = $this->userWithRole(RoleName::Finance);
        $token = $finance->createToken('phpunit')->plainTextToken;

        $this->withIdempotency($token, 'midtrans-refund-rejected')
            ->postJson('/api/v1/payments/'.$payment->id.'/refund', [
                'reason' => 'Customer left',
            ])
            ->assertStatus(502)
            ->assertJsonPath('error.code', 'upstream.payment_provider_unavailable');

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame(0, Ticket::query()->where('status', TicketStatus::Refunded)->count());
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function midtransSignature(array $payload): string
    {
        return hash(
            'sha512',
            (string) $payload['order_id']
            .(string) $payload['status_code']
            .(string) $payload['gross_amount']
            .$this->serverKey,
        );
    }

    private function seedProcessingMidtransPayment(): Payment
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        [, $token] = $this->activeDeviceToken($destination);
        $orderPayload = $this->createPendingOrder($token, $destination, $adult);

        Http::fake([
            'api.sandbox.midtrans.com/v2/charge' => Http::response([
                'status_code' => '201',
                'transaction_id' => 'txn-webhook-1',
                'qr_string' => '00020101021226620014COM.GO-JEK.WWW0119WEBHOOK',
                'transaction_status' => 'pending',
            ], 201),
        ]);

        $this->withIdempotency($token, 'midtrans-seed-'.uniqid())
            ->postJson('/api/v1/orders/'.$orderPayload['id'].'/payments', [
                'method' => PaymentMethod::Qris->value,
            ])
            ->assertCreated();

        return Payment::query()->where('order_id', $orderPayload['id'])->firstOrFail();
    }

    /**
     * @return array{payment: Payment, token: string, orderId: int}
     */
    private function seedOpenMidtransPayment(string $idempotencyKey): array
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        [, $token] = $this->activeDeviceToken($destination);
        $orderPayload = $this->createPendingOrder($token, $destination, $adult);

        Http::fake([
            'api.sandbox.midtrans.com/v2/charge' => Http::response([
                'status_code' => '201',
                'transaction_id' => 'txn-'.$idempotencyKey,
                'qr_string' => '00020101021226620014COM.GO-JEK.WWW0119CANCEL',
                'transaction_status' => 'pending',
            ], 201),
        ]);

        $this->withIdempotency($token, $idempotencyKey)
            ->postJson('/api/v1/orders/'.$orderPayload['id'].'/payments', [
                'method' => PaymentMethod::Qris->value,
            ])
            ->assertCreated();

        return [
            'payment' => Payment::query()->where('order_id', $orderPayload['id'])->firstOrFail(),
            'token' => $token,
            'orderId' => (int) $orderPayload['id'],
        ];
    }

    private function settleMidtransPayment(Payment $payment): Payment
    {
        $payload = [
            'transaction_time' => '2026-09-29 21:00:00',
            'transaction_status' => 'settlement',
            'transaction_id' => $payment->provider_payment_id,
            'status_message' => 'midtrans payment notification',
            'status_code' => '200',
            'payment_type' => 'qris',
            'order_id' => $payment->payment_number,
            'gross_amount' => '103000.00',
            'fraud_status' => 'accept',
        ];
        $payload['signature_key'] = $this->midtransSignature($payload);

        $this->postJson('/api/v1/webhooks/payments/midtrans', $payload)->assertOk();

        return $payment->fresh() ?? $payment;
    }
}

<?php

namespace Tests\Feature\Kiosks;

use App\Enums\Channel;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\TicketStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\AssertsApiEnvelope;
use Tests\Concerns\InteractsWithOrders;
use Tests\Concerns\InteractsWithPaymentWebhooks;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

/**
 * TASK-020 contract smoke: device token quote → order → digital pay → webhook → tickets.
 * Freezes Resource shapes Flutter checkout will consume.
 */
#[Group('critical')]
class KioskApiContractSmokeTest extends TestCase
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
        config(['payments.webhook_secret' => $this->webhookSecret()]);
    }

    public function test_device_checkout_contract_smoke_quote_order_pay_tickets(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        [$device, $token] = $this->activeDeviceToken($destination);

        $config = $this->withToken($token)->getJson('/api/v1/kiosks/me/config');
        $this->assertSuccessEnvelope($config, 200);
        $config->assertJsonPath('data.currency', 'IDR')
            ->assertJsonPath('data.destination.id', $destination->id)
            ->assertJsonPath('data.features.print', true)
            ->assertJsonStructure(['data' => ['locale_default', 'payment_ttl_seconds']]);

        $catalog = $this->withToken($token)->getJson('/api/v1/destinations?active=1');
        $this->assertSuccessEnvelope($catalog, 200);
        $this->assertTrue(collect($catalog->json('data'))->contains(
            fn (array $row): bool => (int) $row['id'] === $destination->id,
        ));

        $types = $this->withToken($token)
            ->getJson("/api/v1/destinations/{$destination->id}/ticket-types");
        $this->assertSuccessEnvelope($types, 200);
        $types->assertJsonFragment(['id' => $adult->id, 'code' => 'ADULT']);

        $quotePayload = $this->twoAdultPayload($destination, $adult);
        $quote = $this->withToken($token)->postJson('/api/v1/pricing/quote', $quotePayload);
        $this->assertSuccessEnvelope($quote, 200);
        $quote->assertJsonPath('data.currency', 'IDR')
            ->assertJsonPath('data.grand_total', 103000)
            ->assertJsonStructure([
                'data' => [
                    'items',
                    'subtotal',
                    'discount_total',
                    'tax_total',
                    'service_fee_total',
                    'grand_total',
                ],
            ]);

        $orderResponse = $this->withIdempotency($token, 'smoke-order')
            ->postJson('/api/v1/orders', $quotePayload);
        $this->assertSuccessEnvelope($orderResponse, 201);
        $orderResponse->assertJsonPath('data.channel', Channel::Kiosk->value)
            ->assertJsonPath('data.status', OrderStatus::PendingPayment->value)
            ->assertJsonPath('data.currency', 'IDR')
            ->assertJsonPath('data.grand_total', 103000)
            ->assertJsonPath('data.device_id', $device->id)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'order_number',
                    'expires_at',
                    'items',
                    'subtotal',
                    'tax_total',
                    'service_fee_total',
                    'grand_total',
                ],
            ]);

        $orderId = (int) $orderResponse->json('data.id');
        $grandTotal = (int) $orderResponse->json('data.grand_total');

        $showOrder = $this->withToken($token)->getJson("/api/v1/orders/{$orderId}");
        $this->assertSuccessEnvelope($showOrder, 200);
        $showOrder->assertJsonPath('data.id', $orderId)
            ->assertJsonPath('data.grand_total', $grandTotal);

        $pay = $this->withIdempotency($token, 'smoke-pay')
            ->postJson("/api/v1/orders/{$orderId}/payments", [
                'method' => PaymentMethod::EWallet->value,
            ]);
        $this->assertSuccessEnvelope($pay, 201);
        $pay->assertJsonPath('data.payment.status', PaymentStatus::Processing->value)
            ->assertJsonPath('data.payment.amount', $grandTotal)
            ->assertJsonPath('data.payment.currency', 'IDR')
            ->assertJsonPath('data.payment.tickets_issued', false)
            ->assertJsonPath('data.next_action.type', 'display_qr')
            ->assertJsonStructure([
                'data' => [
                    'payment' => ['id', 'payment_number', 'status', 'method', 'amount'],
                    'next_action' => ['type', 'qr_content'],
                ],
            ]);

        $paymentId = (int) $pay->json('data.payment.id');
        $paymentNumber = (string) $pay->json('data.payment.payment_number');

        $pollPending = $this->withToken($token)->getJson("/api/v1/payments/{$paymentId}");
        $this->assertSuccessEnvelope($pollPending, 200);
        $pollPending->assertJsonPath('data.status', PaymentStatus::Processing->value)
            ->assertJsonPath('data.tickets_issued', false);

        $this->postSandboxWebhook([
            'event_id' => 'evt_smoke_paid',
            'type' => 'payment.paid',
            'payment_number' => $paymentNumber,
            'provider_payment_id' => Payment::query()->findOrFail($paymentId)->provider_payment_id,
        ])->assertOk()->assertJsonPath('success', true);

        $pollPaid = $this->withToken($token)->getJson("/api/v1/payments/{$paymentId}");
        $this->assertSuccessEnvelope($pollPaid, 200);
        $pollPaid->assertJsonPath('data.status', PaymentStatus::Paid->value)
            ->assertJsonPath('data.tickets_issued', true)
            ->assertJsonPath('data.order_status', OrderStatus::Paid->value);

        $this->assertSame(OrderStatus::Paid, Order::query()->findOrFail($orderId)->status);
        $this->assertSame(2, Ticket::query()->where('order_id', $orderId)->count());

        $tickets = $this->withToken($token)->getJson("/api/v1/orders/{$orderId}/tickets");
        $this->assertSuccessEnvelope($tickets, 200);
        $tickets->assertJsonCount(2, 'data');
        $firstCode = (string) $tickets->json('data.0.ticket_code');
        $tickets->assertJsonPath('data.0.status', TicketStatus::Active->value)
            ->assertJsonStructure([
                'data' => [
                    ['id', 'ticket_code', 'order_id', 'status', 'qr_payload', 'valid_start_at', 'valid_end_at'],
                ],
            ]);

        $ticketShow = $this->withToken($token)->getJson("/api/v1/tickets/{$firstCode}");
        $this->assertSuccessEnvelope($ticketShow, 200);
        $ticketShow->assertJsonPath('data.ticket_code', $firstCode)
            ->assertJsonPath('data.status', TicketStatus::Active->value);

        $print = $this->withToken($token)->getJson("/api/v1/tickets/{$firstCode}/print-payload");
        $this->assertSuccessEnvelope($print, 200);
        $print->assertJsonStructure([
            'data' => [
                'ticket_code',
                'qr_payload',
            ],
        ]);

        $ack = $this->withToken($token)->postJson("/api/v1/tickets/{$firstCode}/print-ack", [
            'result' => 'success',
        ]);
        $this->assertSuccessEnvelope($ack, 201);
        $ack->assertJsonPath('data.audit_action', 'ticket.printed')
            ->assertJsonPath('data.ticket_code', $firstCode);

        $heartbeat = $this->withToken($token)->postJson('/api/v1/kiosks/heartbeat', [
            'software_version' => 'smoke-1.0.0',
            'printer_ok' => true,
        ]);
        $this->assertSuccessEnvelope($heartbeat, 200);
        $heartbeat->assertJsonPath('data.device_status', 'active')
            ->assertJsonStructure(['data' => ['server_time', 'commands']]);
    }

    public function test_expired_order_cannot_initiate_payment(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        [, $token] = $this->activeDeviceToken($destination);
        $order = $this->createPendingOrder($token, $destination, $adult, 'expired-order');

        Order::query()->whereKey($order['id'])->update([
            'expires_at' => now()->subMinute(),
        ]);

        $response = $this->withIdempotency($token, 'pay-expired')
            ->postJson('/api/v1/orders/'.$order['id'].'/payments', [
                'method' => PaymentMethod::EWallet->value,
            ]);

        $this->assertErrorEnvelope($response, 409, 'order.state_conflict');
    }
}

<?php

namespace Tests\Feature\Payments;

use App\Actions\Payments\MarkPaymentPaid;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Enums\TicketStatus;
use App\Integrations\Payments\PaymentGateway;
use App\Integrations\Payments\PaymentGatewayException;
use App\Integrations\Payments\PaymentInitiation;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithCashierShifts;
use Tests\Concerns\InteractsWithOrders;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class RefundPaymentTest extends TestCase
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

    public function test_finance_can_refund_eligible_paid_payment_and_voids_tickets(): void
    {
        $paymentId = $this->paidCashPaymentId('refund-ok');
        $finance = $this->userWithRole(RoleName::Finance);
        $token = $finance->createToken('phpunit')->plainTextToken;

        $response = $this->withIdempotency($token, 'refund-1')
            ->postJson("/api/v1/payments/{$paymentId}/refund", [
                'reason' => 'Customer cancelled visit',
            ]);

        $payment = Payment::query()->findOrFail($paymentId);
        $order = Order::query()->findOrFail($payment->order_id);

        $response->assertOk()
            ->assertJsonPath('data.status', PaymentStatus::Refunded->value)
            ->assertJsonPath('data.order_status', OrderStatus::Refunded->value)
            ->assertJsonPath('data.refund_amount', $payment->amount)
            ->assertJsonPath('data.amount', $payment->amount);

        $this->assertSame(PaymentStatus::Refunded, $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->refunded_at);
        $this->assertSame(OrderStatus::Refunded, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->refunded_at);

        $this->assertSame(
            2,
            Ticket::query()
                ->where('payment_id', $paymentId)
                ->where('status', TicketStatus::Refunded->value)
                ->count(),
        );

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'refund.created',
            'actor_type' => 'user',
            'actor_id' => $finance->id,
            'entity_id' => $paymentId,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ticket.refunded',
            'actor_id' => $finance->id,
        ]);
    }

    public function test_used_tickets_make_refund_ineligible(): void
    {
        $paymentId = $this->paidCashPaymentId('refund-used');
        Ticket::query()->where('payment_id', $paymentId)->update([
            'status' => TicketStatus::Used->value,
            'used_at' => now(),
        ]);

        $finance = $this->userWithRole(RoleName::Finance);
        $token = $finance->createToken('phpunit')->plainTextToken;

        $this->withIdempotency($token, 'refund-used-1')
            ->postJson("/api/v1/payments/{$paymentId}/refund", [
                'reason' => 'Too late',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'refund.ineligible');

        $this->assertSame(PaymentStatus::Paid, Payment::query()->findOrFail($paymentId)->status);
        $this->assertSame(OrderStatus::Paid, Order::query()->whereKey(
            Payment::query()->findOrFail($paymentId)->order_id,
        )->firstOrFail()->status);
    }

    public function test_ticket_officer_cannot_refund(): void
    {
        $paymentId = $this->paidCashPaymentId('refund-deny');
        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $token = $officer->createToken('phpunit')->plainTextToken;

        $this->withIdempotency($token, 'refund-deny-1')
            ->postJson("/api/v1/payments/{$paymentId}/refund", [
                'reason' => 'Should fail',
            ])
            ->assertForbidden();

        $this->assertSame(PaymentStatus::Paid, Payment::query()->findOrFail($paymentId)->status);
    }

    public function test_refund_is_idempotent(): void
    {
        $paymentId = $this->paidCashPaymentId('refund-idemp');
        $finance = $this->userWithRole(RoleName::Finance);
        $token = $finance->createToken('phpunit')->plainTextToken;

        $first = $this->withIdempotency($token, 'refund-same')
            ->postJson("/api/v1/payments/{$paymentId}/refund", [
                'reason' => 'Duplicate safe',
            ]);
        $second = $this->withIdempotency($token, 'refund-same')
            ->postJson("/api/v1/payments/{$paymentId}/refund", [
                'reason' => 'Duplicate safe',
            ]);

        $first->assertOk();
        $second->assertOk()->assertJsonPath('data.id', $first->json('data.id'));

        $this->assertSame(1, Payment::query()->where('status', PaymentStatus::Refunded->value)->count());
        $this->assertSame(2, Ticket::query()->where('status', TicketStatus::Refunded->value)->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'refund.created')->count());
    }

    public function test_client_refund_amount_is_rejected(): void
    {
        $paymentId = $this->paidCashPaymentId('refund-money');
        $finance = $this->userWithRole(RoleName::Finance);
        $token = $finance->createToken('phpunit')->plainTextToken;

        $this->withIdempotency($token, 'refund-money-1')
            ->postJson("/api/v1/payments/{$paymentId}/refund", [
                'reason' => 'Partial attempt',
                'refund_amount' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'money.client_values_forbidden');
    }

    public function test_digital_provider_refund_failure_keeps_paid(): void
    {
        $paymentId = $this->paidDigitalPaymentId('refund-provider-fail');

        $this->app->bind(PaymentGateway::class, fn () => new class implements PaymentGateway
        {
            public function initiate(Payment $payment, Order $order): PaymentInitiation
            {
                throw new PaymentGatewayException;
            }

            public function refund(Payment $payment): void
            {
                throw new PaymentGatewayException('Provider refund failed.');
            }
        });

        $finance = $this->userWithRole(RoleName::Finance);
        $token = $finance->createToken('phpunit')->plainTextToken;

        $this->withIdempotency($token, 'refund-fail-1')
            ->postJson("/api/v1/payments/{$paymentId}/refund", [
                'reason' => 'Provider down',
            ])
            ->assertStatus(502)
            ->assertJsonPath('error.code', 'upstream.payment_provider_unavailable');

        $this->assertSame(PaymentStatus::Paid, Payment::query()->findOrFail($paymentId)->status);
        $this->assertSame(
            OrderStatus::Paid,
            Order::query()->findOrFail(Payment::query()->findOrFail($paymentId)->order_id)->status,
        );
        $this->assertSame(
            0,
            Ticket::query()->where('payment_id', $paymentId)->where('status', TicketStatus::Refunded->value)->count(),
        );
    }

    public function test_idempotency_key_required_for_refund(): void
    {
        $paymentId = $this->paidCashPaymentId('refund-no-key');
        $finance = $this->userWithRole(RoleName::Finance);
        $token = $finance->createToken('phpunit')->plainTextToken;

        $this->flushHeaders();

        $this->withToken($token)
            ->postJson("/api/v1/payments/{$paymentId}/refund", [
                'reason' => 'Missing key',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation.failed');
    }

    private function paidCashPaymentId(string $keyPrefix): int
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $this->openCashierShiftFor($officer);
        $token = $officer->createToken('phpunit')->plainTextToken;
        $order = $this->createPendingOrder($token, $destination, $adult, $keyPrefix.'-order');

        $paymentId = $this->withIdempotency($token, $keyPrefix.'-init')
            ->postJson('/api/v1/orders/'.$order['id'].'/payments', [
                'method' => PaymentMethod::Cash->value,
            ])
            ->json('data.payment.id');

        $this->withIdempotency($token, $keyPrefix.'-confirm')
            ->postJson("/api/v1/payments/{$paymentId}/confirm-cash", [
                'received' => true,
            ])
            ->assertOk();

        return (int) $paymentId;
    }

    private function paidDigitalPaymentId(string $keyPrefix): int
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $token = $officer->createToken('phpunit')->plainTextToken;
        $order = $this->createPendingOrder($token, $destination, $adult, $keyPrefix.'-order');

        $paymentId = $this->withIdempotency($token, $keyPrefix.'-init')
            ->postJson('/api/v1/orders/'.$order['id'].'/payments', [
                'method' => PaymentMethod::EWallet->value,
            ])
            ->json('data.payment.id');

        $payment = Payment::query()->findOrFail($paymentId);
        app(MarkPaymentPaid::class)->handle($payment, $officer, null, 'test');

        return (int) $paymentId;
    }
}

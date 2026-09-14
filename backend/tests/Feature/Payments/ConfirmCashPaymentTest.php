<?php

namespace Tests\Feature\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithCashierShifts;
use Tests\Concerns\InteractsWithOrders;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class ConfirmCashPaymentTest extends TestCase
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

    public function test_ticket_officer_can_confirm_cash_and_marks_order_paid(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $this->openCashierShiftFor($officer);
        $token = $officer->createToken('phpunit')->plainTextToken;
        $order = $this->createPendingOrder($token, $destination, $adult, 'cash-order');

        $initiate = $this->withIdempotency($token, 'cash-init')
            ->postJson('/api/v1/orders/'.$order['id'].'/payments', [
                'method' => PaymentMethod::Cash->value,
            ]);
        $initiate->assertCreated()
            ->assertJsonPath('data.payment.status', PaymentStatus::Processing->value)
            ->assertJsonPath('data.next_action', null);

        $paymentId = $initiate->json('data.payment.id');

        $confirm = $this->withIdempotency($token, 'cash-confirm')
            ->postJson("/api/v1/payments/{$paymentId}/confirm-cash", [
                'received' => true,
            ]);

        $confirm->assertOk()
            ->assertJsonPath('data.status', PaymentStatus::Paid->value)
            ->assertJsonPath('data.amount', $order['grand_total'])
            ->assertJsonPath('data.order_status', OrderStatus::Paid->value)
            ->assertJsonPath('data.tickets_issued', true);

        $this->assertCount(2, $confirm->json('data.tickets'));
        $this->assertSame(2, Ticket::query()->where('order_id', $order['id'])->count());

        $this->assertSame(OrderStatus::Paid, Order::query()->find($order['id'])?->status);
        $this->assertNotNull(Payment::query()->find($paymentId)?->paid_at);
        $this->assertSame($officer->id, Payment::query()->find($paymentId)?->collected_by_user_id);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'payment.paid',
            'actor_type' => 'user',
            'actor_id' => $officer->id,
            'entity_id' => $paymentId,
        ]);
    }

    public function test_confirm_cash_is_idempotent(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $this->openCashierShiftFor($officer);
        $token = $officer->createToken('phpunit')->plainTextToken;
        $order = $this->createPendingOrder($token, $destination, $adult, 'cash-idemp-order');

        $paymentId = $this->withIdempotency($token, 'cash-idemp-init')
            ->postJson('/api/v1/orders/'.$order['id'].'/payments', [
                'method' => PaymentMethod::Cash->value,
            ])
            ->json('data.payment.id');

        $first = $this->withIdempotency($token, 'cash-idemp-confirm')
            ->postJson("/api/v1/payments/{$paymentId}/confirm-cash", ['received' => true]);
        $second = $this->withIdempotency($token, 'cash-idemp-confirm')
            ->postJson("/api/v1/payments/{$paymentId}/confirm-cash", ['received' => true]);

        $first->assertOk();
        $second->assertOk()->assertJsonPath('data.id', $first->json('data.id'));
        $this->assertSame(1, Payment::query()->where('status', PaymentStatus::Paid->value)->count());
        $this->assertSame(1, Order::query()->where('status', OrderStatus::Paid->value)->count());
        $this->assertSame(2, Ticket::query()->where('order_id', $order['id'])->count());
    }

    public function test_auditor_cannot_confirm_cash(): void
    {
        ['destination' => $destination] = $this->sellableCatalog();
        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $order = Order::factory()->create([
            'destination_id' => $destination->id,
            'created_by_user_id' => $officer->id,
        ]);
        $payment = Payment::factory()->cash()->processing()->create([
            'order_id' => $order->id,
            'amount' => $order->grand_total,
            'collected_by_user_id' => $officer->id,
        ]);

        $auditor = $this->userWithRole(RoleName::Auditor);
        $token = $auditor->createToken('phpunit')->plainTextToken;

        $this->withIdempotency($token, 'auditor-cash')
            ->postJson("/api/v1/payments/{$payment->id}/confirm-cash", ['received' => true])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'auth.forbidden');

        $this->assertSame(PaymentStatus::Processing, $payment->fresh()->status);
    }

    public function test_device_cannot_confirm_cash(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $this->openCashierShiftFor($officer);
        $staffToken = $officer->createToken('phpunit')->plainTextToken;
        $order = $this->createPendingOrder($staffToken, $destination, $adult, 'dev-cash-order');
        $paymentId = $this->withIdempotency($staffToken, 'dev-cash-init')
            ->postJson('/api/v1/orders/'.$order['id'].'/payments', [
                'method' => PaymentMethod::Cash->value,
            ])
            ->json('data.payment.id');

        [, $deviceToken] = $this->activeDeviceToken($destination);

        $this->withIdempotency($deviceToken, 'dev-cash-confirm')
            ->postJson("/api/v1/payments/{$paymentId}/confirm-cash", ['received' => true])
            ->assertForbidden();
    }

    public function test_cash_confirm_on_digital_payment_is_conflict(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $this->openCashierShiftFor($officer);
        $token = $officer->createToken('phpunit')->plainTextToken;
        $order = $this->createPendingOrder($token, $destination, $adult, 'digital-order');
        $paymentId = $this->withIdempotency($token, 'digital-init')
            ->postJson('/api/v1/orders/'.$order['id'].'/payments', [
                'method' => PaymentMethod::EWallet->value,
            ])
            ->json('data.payment.id');

        $this->withIdempotency($token, 'digital-as-cash')
            ->postJson("/api/v1/payments/{$paymentId}/confirm-cash", ['received' => true])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'order.state_conflict');
    }

    public function test_received_false_is_rejected(): void
    {
        ['destination' => $destination] = $this->sellableCatalog();
        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $token = $officer->createToken('phpunit')->plainTextToken;
        $order = Order::factory()->create([
            'destination_id' => $destination->id,
            'created_by_user_id' => $officer->id,
        ]);
        $payment = Payment::factory()->cash()->processing()->create([
            'order_id' => $order->id,
            'amount' => $order->grand_total,
        ]);

        $this->withIdempotency($token, 'received-false')
            ->postJson("/api/v1/payments/{$payment->id}/confirm-cash", ['received' => false])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation.failed');
    }

    public function test_client_cannot_force_paid_on_confirm(): void
    {
        ['destination' => $destination] = $this->sellableCatalog();
        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $token = $officer->createToken('phpunit')->plainTextToken;
        $order = Order::factory()->create([
            'destination_id' => $destination->id,
            'created_by_user_id' => $officer->id,
        ]);
        $payment = Payment::factory()->cash()->processing()->create([
            'order_id' => $order->id,
            'amount' => $order->grand_total,
        ]);

        $this->withIdempotency($token, 'force-paid')
            ->postJson("/api/v1/payments/{$payment->id}/confirm-cash", [
                'received' => true,
                'payment_status' => 'paid',
                'amount' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'money.client_values_forbidden');
    }
}

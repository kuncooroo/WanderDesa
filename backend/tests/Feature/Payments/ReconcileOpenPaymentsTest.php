<?php

namespace Tests\Feature\Payments;

use App\Actions\Payments\ApplyProviderPaymentEvent;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Integrations\Payments\PaymentGateway;
use App\Integrations\Payments\PaymentGatewayException;
use App\Integrations\Payments\PaymentInitiation;
use App\Integrations\Payments\QueriesRemotePaymentStatus;
use App\Integrations\Payments\RemotePaymentStatus;
use App\Jobs\ReconcileOpenPayments;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithOrders;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class ReconcileOpenPaymentsTest extends TestCase
{
    use InteractsWithOrders;
    use InteractsWithRbac;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_reconcile_marks_paid_when_provider_paid(): void
    {
        $payment = $this->openDigitalPayment();

        $this->app->bind(PaymentGateway::class, fn () => new class implements PaymentGateway, QueriesRemotePaymentStatus
        {
            public function initiate(Payment $payment, Order $order): PaymentInitiation
            {
                throw new PaymentGatewayException;
            }

            public function refund(Payment $payment): void {}

            public function fetchRemoteStatus(Payment $payment): RemotePaymentStatus
            {
                return RemotePaymentStatus::Paid;
            }
        });

        (new ReconcileOpenPayments)->handle(
            $this->app->make(PaymentGateway::class),
            $this->app->make(ApplyProviderPaymentEvent::class),
        );

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame(OrderStatus::Paid, $payment->order()->first()?->status);
        $this->assertSame(1, Ticket::query()->count());
    }

    public function test_reconcile_is_safe_to_rerun(): void
    {
        $payment = $this->openDigitalPayment();

        $this->app->bind(PaymentGateway::class, fn () => new class implements PaymentGateway, QueriesRemotePaymentStatus
        {
            public function initiate(Payment $payment, Order $order): PaymentInitiation
            {
                throw new PaymentGatewayException;
            }

            public function refund(Payment $payment): void {}

            public function fetchRemoteStatus(Payment $payment): RemotePaymentStatus
            {
                return RemotePaymentStatus::Paid;
            }
        });

        $job = new ReconcileOpenPayments;
        $apply = $this->app->make(ApplyProviderPaymentEvent::class);
        $gateway = $this->app->make(PaymentGateway::class);

        $job->handle($gateway, $apply);
        $job->handle($gateway, $apply);

        $this->assertSame(1, Payment::query()->where('status', PaymentStatus::Paid->value)->count());
        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame(1, Ticket::query()->count());
    }

    public function test_reconcile_marks_failed_when_provider_failed(): void
    {
        $payment = $this->openDigitalPayment();

        $this->app->bind(PaymentGateway::class, fn () => new class implements PaymentGateway, QueriesRemotePaymentStatus
        {
            public function initiate(Payment $payment, Order $order): PaymentInitiation
            {
                throw new PaymentGatewayException;
            }

            public function refund(Payment $payment): void {}

            public function fetchRemoteStatus(Payment $payment): RemotePaymentStatus
            {
                return RemotePaymentStatus::Failed;
            }
        });

        (new ReconcileOpenPayments)->handle(
            $this->app->make(PaymentGateway::class),
            $this->app->make(ApplyProviderPaymentEvent::class),
        );

        $this->assertSame(PaymentStatus::Failed, $payment->fresh()->status);
        $this->assertSame(OrderStatus::PendingPayment, $payment->order()->first()?->status);
    }

    public function test_reconcile_skips_unknown_remote_status(): void
    {
        $payment = $this->openDigitalPayment();

        (new ReconcileOpenPayments)->handle(
            $this->app->make(PaymentGateway::class),
            $this->app->make(ApplyProviderPaymentEvent::class),
        );

        $this->assertSame(PaymentStatus::Processing, $payment->fresh()->status);
    }

    private function openDigitalPayment(): Payment
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        $order = Order::factory()->create([
            'destination_id' => $destination->id,
            'status' => OrderStatus::PendingPayment,
            'grand_total' => 50000,
        ]);
        $this->attachOrderLine($order, $adult, 1);

        return Payment::factory()->processing()->create([
            'order_id' => $order->id,
            'method' => PaymentMethod::EWallet,
            'provider' => 'sandbox',
            'amount' => 50000,
            'provider_payment_id' => 'sandbox-PAY-RECON',
        ]);
    }
}

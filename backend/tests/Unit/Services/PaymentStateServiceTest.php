<?php

namespace Tests\Unit\Services;

use App\Enums\PaymentStatus;
use App\Exceptions\DomainException;
use App\Models\Payment;
use App\Services\Payments\PaymentStateService;
use Tests\TestCase;

class PaymentStateServiceTest extends TestCase
{
    public function test_processing_can_move_to_paid(): void
    {
        $payment = new Payment(['status' => PaymentStatus::Processing]);
        $service = new PaymentStateService;

        $service->transition($payment, PaymentStatus::Paid);

        $this->assertSame(PaymentStatus::Paid, $payment->status);
    }

    public function test_paid_cannot_move_to_processing(): void
    {
        $payment = new Payment(['status' => PaymentStatus::Paid]);
        $service = new PaymentStateService;

        $this->expectException(DomainException::class);

        $service->transition($payment, PaymentStatus::Processing);
    }

    public function test_expired_can_move_to_paid_for_late_provider_recovery(): void
    {
        $payment = new Payment(['status' => PaymentStatus::Expired]);
        $service = new PaymentStateService;

        $this->assertTrue($service->canTransition($payment, PaymentStatus::Paid));
        $service->transition($payment, PaymentStatus::Paid);
        $this->assertSame(PaymentStatus::Paid, $payment->status);
    }

    public function test_failed_cannot_move_to_paid(): void
    {
        $payment = new Payment(['status' => PaymentStatus::Failed]);
        $service = new PaymentStateService;

        $this->assertFalse($service->canTransition($payment, PaymentStatus::Paid));
    }

    public function test_paid_can_move_to_refunded(): void
    {
        $payment = new Payment(['status' => PaymentStatus::Paid]);
        $service = new PaymentStateService;

        $service->transition($payment, PaymentStatus::Refunded);

        $this->assertSame(PaymentStatus::Refunded, $payment->status);
    }
}

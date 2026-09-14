<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Exceptions\DomainException;
use App\Models\Payment;

/**
 * Authoritative payment status transitions for kiosk and assisted channels.
 */
final class PaymentStateService
{
    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED = [
        PaymentStatus::Pending->value => [
            PaymentStatus::Processing->value,
            PaymentStatus::Paid->value,
            PaymentStatus::Failed->value,
            PaymentStatus::Cancelled->value,
            PaymentStatus::Expired->value,
        ],
        PaymentStatus::Processing->value => [
            PaymentStatus::Paid->value,
            PaymentStatus::Failed->value,
            PaymentStatus::Cancelled->value,
            PaymentStatus::Expired->value,
        ],
        PaymentStatus::Paid->value => [
            PaymentStatus::Refunded->value,
        ],
        PaymentStatus::Expired->value => [
            PaymentStatus::Paid->value,
        ],
    ];

    public function canTransition(Payment $payment, PaymentStatus $to): bool
    {
        $from = $payment->status instanceof PaymentStatus
            ? $payment->status->value
            : (string) $payment->status;

        if ($from === $to->value) {
            return true;
        }

        return in_array($to->value, self::ALLOWED[$from] ?? [], true);
    }

    public function transition(Payment $payment, PaymentStatus $to): Payment
    {
        if ($payment->status === $to) {
            return $payment;
        }

        if (! $this->canTransition($payment, $to)) {
            throw new DomainException(
                'order.state_conflict',
                "Payment cannot move from {$payment->status->value} to {$to->value}.",
                409,
            );
        }

        $payment->status = $to;

        return $payment;
    }
}

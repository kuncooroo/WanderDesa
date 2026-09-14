<?php

namespace App\Enums;

enum IdempotencyScope: string
{
    case OrderCreate = 'order.create';
    case PaymentInitiate = 'payment.initiate';
    case PaymentConfirmCash = 'payment.confirm_cash';
    case PaymentRefund = 'payment.refund';
    case CheckInCreate = 'checkin.create';

    /**
     * Critical POSTs that must send `Idempotency-Key` (docs/08).
     *
     * @return list<array{method: string, path: string, scope: string}>
     */
    public static function requiredEndpoints(): array
    {
        return [
            [
                'method' => 'POST',
                'path' => '/api/v1/orders',
                'scope' => self::OrderCreate->value,
            ],
            [
                'method' => 'POST',
                'path' => '/api/v1/orders/{order_id}/payments',
                'scope' => self::PaymentInitiate->value,
            ],
            [
                'method' => 'POST',
                'path' => '/api/v1/payments/{id}/confirm-cash',
                'scope' => self::PaymentConfirmCash->value,
            ],
            [
                'method' => 'POST',
                'path' => '/api/v1/payments/{id}/refund',
                'scope' => self::PaymentRefund->value,
            ],
            [
                'method' => 'POST',
                'path' => '/api/v1/check-ins',
                'scope' => self::CheckInCreate->value,
            ],
        ];
    }
}

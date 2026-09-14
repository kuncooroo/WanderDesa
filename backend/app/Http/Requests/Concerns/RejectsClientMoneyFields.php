<?php

namespace App\Http\Requests\Concerns;

use App\Exceptions\DomainException;

trait RejectsClientMoneyFields
{
    /**
     * Authoritative money / commerce-state keys clients must never supply (docs/08 §1.5, §25).
     *
     * @var list<string>
     */
    private const FORBIDDEN_MONEY_KEYS = [
        'unit_price',
        'subtotal',
        'discount',
        'discount_total',
        'tax',
        'tax_total',
        'tax_amount',
        'service_fee',
        'service_fee_total',
        'service_fee_amount',
        'grand_total',
        'amount',
        'refund_amount',
        'line_subtotal',
        'line_tax_total',
        'line_service_fee_total',
        'line_grand_total',
        'client_price',
        'client_total',
        'payment_status',
        'ticket_status',
        'client_payment_status',
        'client_ticket_status',
    ];

    protected function rejectClientMoneyFields(): void
    {
        $found = $this->findForbiddenMoneyKeys($this->all());

        if ($found === []) {
            return;
        }

        $details = array_map(
            static fn (string $field): array => [
                'field' => $field,
                'code' => 'money.client_values_forbidden',
                'message' => 'Authoritative money fields must not be supplied by the client.',
            ],
            $found,
        );

        throw new DomainException(
            'money.client_values_forbidden',
            'Client authoritative money fields are not allowed.',
            422,
            $details,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function findForbiddenMoneyKeys(array $payload, string $prefix = ''): array
    {
        $found = [];

        foreach ($payload as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (in_array((string) $key, self::FORBIDDEN_MONEY_KEYS, true)) {
                $found[] = $path;
            }

            if (is_array($value)) {
                $found = array_merge($found, $this->findForbiddenMoneyKeys($value, $path));
            }
        }

        return array_values(array_unique($found));
    }
}

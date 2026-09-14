<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Qris = 'qris';
    case Debit = 'debit';
    case EWallet = 'e_wallet';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Qris => 'QRIS',
            self::Debit => 'Debit',
            self::EWallet => 'E-Wallet',
        };
    }

    public function isCash(): bool
    {
        return $this === self::Cash;
    }

    /**
     * Provider-backed instruments (not staff cash confirm).
     */
    public function isProviderBacked(): bool
    {
        return ! $this->isCash();
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Normalize legacy API value `digital` → e_wallet.
     */
    public static function fromClient(string $value): self
    {
        if ($value === 'digital') {
            return self::EWallet;
        }

        return self::from($value);
    }
}

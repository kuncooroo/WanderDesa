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
     * Methods the assisted counter may offer. Debit and e-wallet stay in the
     * catalog for stored rows, but Midtrans MVP only charges QRIS.
     *
     * @return list<self>
     */
    public static function assistedChoices(): array
    {
        return [self::Cash, self::Qris];
    }

    /**
     * Legacy kiosk value `digital` is the QRIS instrument. It is not an e-wallet.
     */
    public static function fromClient(string $value): self
    {
        if ($value === 'digital') {
            return self::Qris;
        }

        return self::from($value);
    }
}

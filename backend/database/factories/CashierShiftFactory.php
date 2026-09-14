<?php

namespace Database\Factories;

use App\Enums\CashierShiftStatus;
use App\Models\CashierShift;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashierShift>
 */
class CashierShiftFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $initial = fake()->numberBetween(50_000, 500_000);

        return [
            'user_id' => User::factory(),
            'opened_at' => now(),
            'closed_at' => null,
            'initial_cash' => $initial,
            'total_cash_sales' => 0,
            'total_cash_refund' => 0,
            'expected_cash' => $initial,
            'actual_cash' => null,
            'difference' => null,
            'status' => CashierShiftStatus::Open,
            'notes' => null,
        ];
    }

    public function closed(int $actualCash): static
    {
        return $this->state(function (array $attributes) use ($actualCash): array {
            $expected = (int) $attributes['initial_cash']
                + (int) ($attributes['total_cash_sales'] ?? 0)
                - (int) ($attributes['total_cash_refund'] ?? 0);

            return [
                'status' => CashierShiftStatus::Closed,
                'closed_at' => now(),
                'expected_cash' => $expected,
                'actual_cash' => $actualCash,
                'difference' => $actualCash - $expected,
            ];
        });
    }
}

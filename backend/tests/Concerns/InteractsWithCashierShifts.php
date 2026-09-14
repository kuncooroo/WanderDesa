<?php

namespace Tests\Concerns;

use App\Actions\CashierShifts\OpenCashierShift;
use App\Models\CashierShift;
use App\Models\User;

trait InteractsWithCashierShifts
{
    protected function openCashierShiftFor(User $user, int $initialCash = 100_000): CashierShift
    {
        return app(OpenCashierShift::class)->handle($user, [
            'initial_cash' => $initialCash,
        ]);
    }
}

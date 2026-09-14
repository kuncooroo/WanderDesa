<?php

namespace App\Livewire\CashierShifts;

use App\Models\CashierShift;
use App\Services\CashierShifts\CashierShiftLedger;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Detail shift')]
class ShiftShow extends Component
{
    use AuthorizesRequests;

    public CashierShift $shift;

    public function mount(CashierShift $shift): void
    {
        $this->authorize('view', $shift);
        $this->shift = $shift->load('user');
    }

    public function render(CashierShiftLedger $ledger)
    {
        $totals = $this->shift->isOpen()
            ? $ledger->recalculate($this->shift)
            : [
                'total_cash_sales' => (int) $this->shift->total_cash_sales,
                'total_cash_refund' => (int) $this->shift->total_cash_refund,
                'expected_cash' => (int) $this->shift->expected_cash,
            ];

        $sales = $this->shift->cashSales()
            ->with('order')
            ->orderByDesc('paid_at')
            ->limit(50)
            ->get();

        $refunds = $this->shift->cashRefunds()
            ->with('order')
            ->orderByDesc('refunded_at')
            ->limit(50)
            ->get();

        return view('livewire.cashier-shifts.shift-show', [
            'totals' => $totals,
            'sales' => $sales,
            'refunds' => $refunds,
        ]);
    }
}

<?php

namespace Tests\Feature\CashierShifts;

use App\Actions\CashierShifts\CloseCashierShift;
use App\Actions\CashierShifts\OpenCashierShift;
use App\Enums\CashierShiftStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Exceptions\DomainException;
use App\Livewire\AssistedSale\SaleWizard;
use App\Livewire\CashierShifts\ShiftIndex;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithCashierShifts;
use Tests\Concerns\InteractsWithOrders;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class CashierShiftTest extends TestCase
{
    use InteractsWithCashierShifts;
    use InteractsWithOrders;
    use InteractsWithRbac;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_open_close_shift_computes_expected_and_difference(): void
    {
        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $shift = app(OpenCashierShift::class)->handle($officer, [
            'initial_cash' => 100_000,
        ]);

        $this->assertSame(CashierShiftStatus::Open, $shift->status);
        $this->assertSame(100_000, (int) $shift->expected_cash);

        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        $token = $officer->createToken('phpunit')->plainTextToken;
        $order = $this->createPendingOrder($token, $destination, $adult, 'shift-sale');
        $paymentId = $this->withIdempotency($token, 'shift-init')
            ->postJson('/api/v1/orders/'.$order['id'].'/payments', [
                'method' => PaymentMethod::Cash->value,
            ])
            ->json('data.payment.id');

        $this->withIdempotency($token, 'shift-confirm')
            ->postJson("/api/v1/payments/{$paymentId}/confirm-cash", ['received' => true])
            ->assertOk();

        $payment = Payment::query()->findOrFail($paymentId);
        $this->assertSame($shift->id, (int) $payment->cashier_shift_id);

        $shift->refresh();
        $expected = 100_000 + (int) $payment->amount;

        $closed = app(CloseCashierShift::class)->handle($officer, $shift, [
            'actual_cash' => $expected,
        ]);

        $this->assertSame(CashierShiftStatus::Closed, $closed->status);
        $this->assertSame($expected, (int) $closed->expected_cash);
        $this->assertSame(0, (int) $closed->difference);
        $this->assertSame((int) $payment->amount, (int) $closed->total_cash_sales);
    }

    public function test_cash_confirm_requires_open_shift(): void
    {
        ['destination' => $destination, 'adult' => $adult] = $this->sellableCatalog();
        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $token = $officer->createToken('phpunit')->plainTextToken;
        $order = $this->createPendingOrder($token, $destination, $adult, 'no-shift');

        $paymentId = $this->withIdempotency($token, 'no-shift-init')
            ->postJson('/api/v1/orders/'.$order['id'].'/payments', [
                'method' => PaymentMethod::Cash->value,
            ])
            ->json('data.payment.id');

        $this->withIdempotency($token, 'no-shift-confirm')
            ->postJson("/api/v1/payments/{$paymentId}/confirm-cash", ['received' => true])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'cashier_shift.required');

        $this->assertSame(PaymentStatus::Processing, Payment::query()->findOrFail($paymentId)->status);
    }

    public function test_assisted_sale_blocked_without_open_shift(): void
    {
        $officer = $this->userWithRole(RoleName::TicketOfficer);

        Livewire::actingAs($officer)
            ->test(SaleWizard::class)
            ->assertSet('hasOpenShift', false)
            ->assertSee('Shift belum dibuka');
    }

    public function test_shift_index_open_modal_creates_shift(): void
    {
        $officer = $this->userWithRole(RoleName::TicketOfficer);

        Livewire::actingAs($officer)
            ->test(ShiftIndex::class)
            ->call('openCreateModal')
            ->set('initialCash', '150000')
            ->call('submitOpenShift')
            ->assertSet('showOpenModal', false);

        $this->assertDatabaseHas('cashier_shifts', [
            'user_id' => $officer->id,
            'initial_cash' => 150_000,
            'status' => CashierShiftStatus::Open->value,
        ]);
    }

    public function test_cannot_open_second_shift_while_open(): void
    {
        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $this->openCashierShiftFor($officer, 50_000);

        $this->expectException(DomainException::class);

        app(OpenCashierShift::class)->handle($officer, [
            'initial_cash' => 10_000,
        ]);
    }

    public function test_close_modal_shows_mismatch_indicator(): void
    {
        $officer = $this->userWithRole(RoleName::TicketOfficer);
        $shift = $this->openCashierShiftFor($officer, 100_000);

        Livewire::actingAs($officer)
            ->test(ShiftIndex::class)
            ->call('openCloseModal', $shift->id)
            ->assertSet('showCloseModal', true)
            ->assertSet('closePreview.expected_cash', 100_000)
            ->set('actualCash', '90000')
            ->assertSee('Selisih Rp -10.000');
    }
}

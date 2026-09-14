<?php

use App\Http\Controllers\Web\Auth\SessionAuthController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\ProfileController;
use App\Livewire\AssistedSale\SaleWizard;
use App\Livewire\Audit\AuditLogIndex;
use App\Livewire\CashierShifts\ShiftIndex;
use App\Livewire\CashierShifts\ShiftShow;
use App\Livewire\Catalog\CatalogBoard;
use App\Livewire\Gate\CheckInPanel;
use App\Livewire\Kiosks\KioskIndex;
use App\Livewire\Orders\OrderIndex;
use App\Livewire\Payments\PaymentIndex;
use App\Livewire\Reports\ReportBoard;
use App\Livewire\Roles\RoleBoard;
use App\Livewire\Settings\SettingsBoard;
use App\Livewire\Tickets\TicketDesk;
use App\Livewire\Users\UserIndex;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [SessionAuthController::class, 'create'])->name('login');
    Route::post('/login', [SessionAuthController::class, 'store'])
        ->middleware('throttle:auth');
});

Route::middleware(['auth', 'active'])->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/dashboard/profile', ProfileController::class)->name('dashboard.profile');

    Route::livewire('/dashboard/assisted-sale', SaleWizard::class)->name('dashboard.assisted-sale');
    Route::livewire('/dashboard/orders', OrderIndex::class)->name('dashboard.orders');
    Route::livewire('/dashboard/payments', PaymentIndex::class)->name('dashboard.payments');
    Route::livewire('/dashboard/cashier-shifts', ShiftIndex::class)->name('dashboard.cashier-shifts');
    Route::livewire('/dashboard/cashier-shifts/{shift}', ShiftShow::class)->name('dashboard.cashier-shifts.show');
    Route::livewire('/dashboard/catalog', CatalogBoard::class)->name('dashboard.catalog');
    Route::livewire('/dashboard/users', UserIndex::class)->name('dashboard.users');
    Route::livewire('/dashboard/roles', RoleBoard::class)->name('dashboard.roles');
    Route::livewire('/dashboard/settings', SettingsBoard::class)->name('dashboard.settings');
    Route::livewire('/dashboard/check-in', CheckInPanel::class)->name('dashboard.check-in');
    Route::livewire('/dashboard/audit-logs', AuditLogIndex::class)->name('dashboard.audit-logs');
    Route::livewire('/dashboard/kiosks', KioskIndex::class)->name('dashboard.kiosks');
    Route::livewire('/dashboard/tickets', TicketDesk::class)->name('dashboard.tickets');
    Route::livewire('/dashboard/reports', ReportBoard::class)->name('dashboard.reports');

    Route::post('/logout', [SessionAuthController::class, 'destroy'])->name('logout');
});

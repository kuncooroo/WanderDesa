<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\CheckInController;
use App\Http\Controllers\Api\V1\DestinationController;
use App\Http\Controllers\Api\V1\Kiosk\KioskController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PaymentRefundController;
use App\Http\Controllers\Api\V1\PricingController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\TicketController;
use App\Http\Controllers\Api\V1\TicketTypeController;
use App\Http\Controllers\Api\V1\TicketValidationController;
use App\Http\Controllers\Api\V1\Webhooks\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes
|--------------------------------------------------------------------------
|
| Versioned machine/kiosk contract lives under /api/v1 (docs/08-API-CONTRACT.md).
| Controllers stay thin. Domain work belongs in Actions/Services.
|
*/

Route::prefix('v1')->group(function (): void {
    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:auth');

    Route::post('/webhooks/payments/{provider}', [PaymentWebhookController::class, 'store'])
        ->middleware('throttle:webhooks');

    Route::post('/kiosks/activate', [KioskController::class, 'exchangeActivation'])
        ->middleware('throttle:auth');

    Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        Route::middleware('throttle:catalog')->group(function (): void {
            Route::get('/notifications', [NotificationController::class, 'index']);
            Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);

            Route::get('/destinations', [DestinationController::class, 'index']);
            Route::post('/destinations', [DestinationController::class, 'store']);
            Route::patch('/destinations/{destination}', [DestinationController::class, 'update']);

            Route::get('/destinations/{destination}/ticket-types', [TicketTypeController::class, 'index']);
            Route::post('/ticket-types', [TicketTypeController::class, 'store']);
            Route::patch('/ticket-types/{ticket_type}', [TicketTypeController::class, 'update']);

            Route::post('/pricing/quote', [PricingController::class, 'quote']);
            Route::get('/orders/{order}', [OrderController::class, 'show']);
            Route::get('/orders/{order}/tickets', [TicketController::class, 'indexForOrder']);
            Route::get('/payments/{payment}', [PaymentController::class, 'show']);
            Route::get('/tickets/{ticket_code}', [TicketController::class, 'show']);
            Route::get('/tickets/{ticket_code}/print-payload', [TicketController::class, 'printPayload']);

            Route::get('/kiosks/me', [KioskController::class, 'me']);
            Route::get('/kiosks/me/config', [KioskController::class, 'config']);
            Route::get('/kiosks/health', [KioskController::class, 'health']);
        });

        Route::middleware('throttle:commerce')->group(function (): void {
            Route::post('/orders', [OrderController::class, 'store'])
                ->middleware('idempotency');
            Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);
            Route::post('/orders/{order}/payments', [PaymentController::class, 'store'])
                ->middleware('idempotency');
            Route::post('/payments/{payment}/confirm-cash', [PaymentController::class, 'confirmCash'])
                ->middleware('idempotency');
            Route::post('/payments/{payment}/refund', [PaymentRefundController::class, 'store'])
                ->middleware('idempotency');
            Route::post('/tickets/{ticket_code}/print-ack', [TicketController::class, 'printAck']);

            Route::post('/kiosks', [KioskController::class, 'store']);
            Route::post('/kiosks/{device}/activate', [KioskController::class, 'issueActivation']);
            Route::patch('/kiosks/{device}', [KioskController::class, 'update']);
            Route::post('/kiosks/{device}/maintenance', [KioskController::class, 'maintenance']);
            Route::post('/kiosks/{device}/deactivate', [KioskController::class, 'deactivate']);
        });

        Route::middleware('throttle:heartbeat')->group(function (): void {
            Route::post('/kiosks/heartbeat', [KioskController::class, 'heartbeat']);
        });

        Route::middleware('throttle:access')->group(function (): void {
            Route::post('/tickets/validate', [TicketValidationController::class, 'store']);
            Route::post('/check-ins', [CheckInController::class, 'store'])
                ->middleware('idempotency');
        });

        Route::middleware('throttle:reports')->group(function (): void {
            Route::get('/reports/sales/daily', [ReportController::class, 'dailySales']);
            Route::get('/reports/sales/daily/export', [ReportController::class, 'exportDailySales']);
            Route::get('/reports/payments', [ReportController::class, 'payments']);
            Route::get('/reports/tickets/usage', [ReportController::class, 'ticketUsage']);
        });
    });
});

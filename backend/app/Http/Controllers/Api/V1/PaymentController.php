<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Payments\ConfirmCashPayment;
use App\Actions\Payments\InitiatePayment;
use App\Actions\Payments\RefreshProviderPaymentStatus;
use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Payments\ConfirmCashPaymentRequest;
use App\Http\Requests\Api\V1\Payments\InitiatePaymentRequest;
use App\Http\Resources\Api\V1\PaymentResource;
use App\Models\Device;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\DeviceAbilities;
use App\Support\Idempotency\IdempotencyManager;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function store(
        InitiatePaymentRequest $request,
        Order $order,
        InitiatePayment $initiate,
        IdempotencyManager $idempotency,
    ): JsonResponse {
        $principal = $this->requirePrincipal($request);

        if ($principal instanceof User) {
            $this->authorize('create', Payment::class);
        }

        $session = $initiate->handle(
            $principal,
            $order,
            $request->paymentMethod(),
            $idempotency->keyFromRequest($request),
            $request,
        );

        if ($session['status'] === 502) {
            return ApiResponse::error(
                'upstream.payment_provider_unavailable',
                'Payment provider is unavailable.',
                502,
            );
        }

        return ApiResponse::success(
            [
                'payment' => (new PaymentResource($session['payment']))->resolve($request),
                'next_action' => $session['next_action'],
            ],
            status: 201,
        );
    }

    public function show(Request $request, Payment $payment): JsonResponse
    {
        $this->authorizeView($request, $payment);

        $payment->loadMissing(['order', 'tickets']);

        return ApiResponse::success(
            (new PaymentResource($payment))->resolve($request),
        );
    }

    public function refresh(
        Request $request,
        Payment $payment,
        RefreshProviderPaymentStatus $refresh,
    ): JsonResponse {
        $this->authorizeView($request, $payment);

        $payment = $refresh->handle($payment);

        return ApiResponse::success([
            'payment' => (new PaymentResource($payment))->resolve($request),
            'next_action' => $payment->nextAction(),
        ]);
    }

    public function confirmCash(
        ConfirmCashPaymentRequest $request,
        Payment $payment,
        ConfirmCashPayment $confirm,
        IdempotencyManager $idempotency,
    ): JsonResponse {
        $principal = $confirm->assertStaffPrincipal($request->user());
        $this->authorize('confirmCash', $payment);

        $paid = $confirm->handle(
            $principal,
            $payment,
            $idempotency->keyFromRequest($request),
            $request,
        );

        return ApiResponse::success(
            (new PaymentResource($paid))->resolve($request),
        );
    }

    private function authorizeView(Request $request, Payment $payment): void
    {
        $principal = $request->user();

        if ($principal instanceof Device) {
            if (
                ! $principal->tokenCan(DeviceAbilities::PAYMENTS_POLL)
                && ! $principal->tokenCan(DeviceAbilities::PAYMENTS_INITIATE)
            ) {
                throw new AuthorizationException('Missing device ability: payments:poll');
            }

            $order = $payment->order;
            $ownsPayment = (int) $payment->device_id === (int) $principal->getKey();
            $ownsOrder = $order !== null && (int) $order->device_id === (int) $principal->getKey();

            if (! $ownsPayment && ! $ownsOrder) {
                throw new DomainException(
                    'resource.not_found',
                    'Payment not found.',
                    404,
                );
            }

            return;
        }

        if ($principal instanceof User) {
            $this->authorize('view', $payment);

            return;
        }

        throw new AuthorizationException('Unauthenticated payment principal.');
    }

    private function requirePrincipal(Request $request): Device|User
    {
        $principal = $request->user();

        if ($principal instanceof Device || $principal instanceof User) {
            return $principal;
        }

        throw new AuthorizationException('Unauthenticated payment principal.');
    }
}

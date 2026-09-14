<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Payments\RefundPayment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Payments\RefundPaymentRequest;
use App\Http\Resources\Api\V1\PaymentResource;
use App\Models\Payment;
use App\Support\ApiResponse;
use App\Support\Idempotency\IdempotencyManager;
use Illuminate\Http\JsonResponse;

class PaymentRefundController extends Controller
{
    public function store(
        RefundPaymentRequest $request,
        Payment $payment,
        RefundPayment $refund,
        IdempotencyManager $idempotency,
    ): JsonResponse {
        $principal = $refund->assertStaffPrincipal($request->user());
        $this->authorize('refund', $payment);

        $refunded = $refund->handle(
            $principal,
            $payment,
            $idempotency->keyFromRequest($request),
            $request->reason(),
            $request,
        );

        return ApiResponse::success(
            (new PaymentResource($refunded))->resolve($request),
        );
    }
}

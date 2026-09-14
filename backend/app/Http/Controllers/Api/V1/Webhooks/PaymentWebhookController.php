<?php

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Actions\Payments\AcceptPaymentWebhook;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentWebhookController extends Controller
{
    public function store(
        Request $request,
        string $provider,
        AcceptPaymentWebhook $accept,
    ): JsonResponse {
        $accept->handle($provider, $request);

        return ApiResponse::success([
            'received' => true,
        ]);
    }
}

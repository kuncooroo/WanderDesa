<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CheckIns\CheckInTicket;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CheckIns\StoreCheckInRequest;
use App\Models\CheckIn;
use App\Models\Device;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\Idempotency\IdempotencyManager;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

class CheckInController extends Controller
{
    public function store(
        StoreCheckInRequest $request,
        CheckInTicket $checkIn,
        IdempotencyManager $idempotency,
    ): JsonResponse {
        $principal = $request->user();

        if (! $principal instanceof User) {
            throw new AuthorizationException(
                $principal instanceof Device
                    ? 'Devices cannot perform check-in.'
                    : 'Unauthenticated check-in principal.',
            );
        }

        $this->authorize('create', CheckIn::class);

        $result = $checkIn->handle(
            $principal,
            $request->qrPayload(),
            $request->destinationId(),
            $idempotency->keyFromRequest($request),
            $request->gateId(),
            $request,
        );

        return ApiResponse::success($result->toArray());
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tickets\ValidateTicket;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Tickets\ValidateTicketRequest;
use App\Models\Device;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

class TicketValidationController extends Controller
{
    public function store(ValidateTicketRequest $request, ValidateTicket $validate): JsonResponse
    {
        $principal = $request->user();

        if (! $principal instanceof User) {
            throw new AuthorizationException(
                $principal instanceof Device
                    ? 'Devices cannot validate tickets.'
                    : 'Unauthenticated ticket principal.',
            );
        }

        $result = $validate->handle(
            $principal,
            $request->qrPayload(),
            $request->destinationId(),
            $request->gateId(),
            $request,
        );

        return ApiResponse::success($result->toArray());
    }
}

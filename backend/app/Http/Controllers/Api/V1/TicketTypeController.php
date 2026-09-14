<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Catalog\TicketTypes\CreateTicketType;
use App\Actions\Catalog\TicketTypes\DeactivateTicketType;
use App\Actions\Catalog\TicketTypes\UpdateTicketType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Catalog\StoreTicketTypeRequest;
use App\Http\Requests\Api\V1\Catalog\UpdateTicketTypeRequest;
use App\Http\Resources\Api\V1\TicketTypeResource;
use App\Models\Destination;
use App\Models\Device;
use App\Models\TicketType;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\DeviceAbilities;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketTypeController extends Controller
{
    public function index(Request $request, Destination $destination): JsonResponse
    {
        $this->authorizeCatalogRead($request);

        $query = TicketType::query()
            ->where('destination_id', $destination->id)
            ->orderBy('name');

        $activeOnly = $request->boolean('active')
            || $request->user() instanceof Device;

        if ($activeOnly) {
            $query->active();
        }

        $ticketTypes = $query->get();

        return ApiResponse::success(
            TicketTypeResource::collection($ticketTypes)->resolve($request),
        );
    }

    public function store(StoreTicketTypeRequest $request, CreateTicketType $create): JsonResponse
    {
        $user = $this->requireStaffUser($request);
        $this->authorize('create', TicketType::class);

        $ticketType = $create->handle($user, $request->ticketTypePayload(), $request);

        return ApiResponse::success(
            (new TicketTypeResource($ticketType))->resolve($request),
            status: 201,
        );
    }

    public function update(
        UpdateTicketTypeRequest $request,
        TicketType $ticketType,
        UpdateTicketType $update,
        DeactivateTicketType $deactivate,
    ): JsonResponse {
        $user = $this->requireStaffUser($request);
        $this->authorize('update', $ticketType);

        $payload = $request->ticketTypePayload();

        if (
            array_key_exists('is_active', $payload)
            && $payload['is_active'] === false
            && count($payload) === 1
        ) {
            $ticketType = $deactivate->handle($user, $ticketType, $request);
        } else {
            $ticketType = $update->handle($user, $ticketType, $payload, $request);
        }

        return ApiResponse::success(
            (new TicketTypeResource($ticketType))->resolve($request),
        );
    }

    private function authorizeCatalogRead(Request $request): void
    {
        $principal = $request->user();

        if ($principal instanceof Device) {
            if (! $principal->tokenCan(DeviceAbilities::CATALOG_READ)) {
                throw new AuthorizationException('Missing device ability: catalog:read');
            }

            return;
        }

        if ($principal instanceof User) {
            $this->authorize('viewAny', TicketType::class);

            return;
        }

        throw new AuthorizationException('Unauthenticated catalog principal.');
    }

    private function requireStaffUser(Request $request): User
    {
        $principal = $request->user();

        if (! $principal instanceof User) {
            throw new AuthorizationException('Only staff users may manage ticket types.');
        }

        return $principal;
    }
}

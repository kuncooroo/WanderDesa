<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Catalog\CreateDestination;
use App\Actions\Catalog\DeactivateDestination;
use App\Actions\Catalog\UpdateDestination;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Catalog\StoreDestinationRequest;
use App\Http\Requests\Api\V1\Catalog\UpdateDestinationRequest;
use App\Http\Resources\Api\V1\DestinationResource;
use App\Models\Destination;
use App\Models\Device;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\DeviceAbilities;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DestinationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeCatalogRead($request);

        $query = Destination::query()->orderBy('name');

        $activeOnly = $request->boolean('active')
            || $request->user() instanceof Device;

        if ($activeOnly) {
            $query->active();
        }

        $destinations = $query->get();

        return ApiResponse::success(
            DestinationResource::collection($destinations)->resolve($request),
        );
    }

    public function store(StoreDestinationRequest $request, CreateDestination $create): JsonResponse
    {
        $user = $this->requireStaffUser($request);
        $this->authorize('create', Destination::class);

        $destination = $create->handle($user, $request->destinationPayload(), $request);

        return ApiResponse::success(
            (new DestinationResource($destination))->resolve($request),
            status: 201,
        );
    }

    public function update(
        UpdateDestinationRequest $request,
        Destination $destination,
        UpdateDestination $update,
        DeactivateDestination $deactivate,
    ): JsonResponse {
        $user = $this->requireStaffUser($request);
        $this->authorize('update', $destination);

        $payload = $request->destinationPayload();

        if (
            array_key_exists('is_active', $payload)
            && $payload['is_active'] === false
            && count($payload) === 1
        ) {
            $destination = $deactivate->handle($user, $destination, $request);
        } else {
            $destination = $update->handle($user, $destination, $payload, $request);
        }

        return ApiResponse::success(
            (new DestinationResource($destination))->resolve($request),
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
            $this->authorize('viewAny', Destination::class);

            return;
        }

        throw new AuthorizationException('Unauthenticated catalog principal.');
    }

    private function requireStaffUser(Request $request): User
    {
        $principal = $request->user();

        if (! $principal instanceof User) {
            throw new AuthorizationException('Only staff users may manage destinations.');
        }

        return $principal;
    }
}

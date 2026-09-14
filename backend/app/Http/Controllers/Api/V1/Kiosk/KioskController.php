<?php

namespace App\Http\Controllers\Api\V1\Kiosk;

use App\Actions\Devices\DeactivateDevice;
use App\Actions\Devices\ExchangeDeviceActivation;
use App\Actions\Devices\GetFleetHealth;
use App\Actions\Devices\IssueDeviceActivation;
use App\Actions\Devices\RecordDeviceHeartbeat;
use App\Actions\Devices\RegisterDevice;
use App\Actions\Devices\SetDeviceMaintenance;
use App\Actions\Devices\UpdateDevice;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Kiosk\ExchangeActivationRequest;
use App\Http\Requests\Api\V1\Kiosk\HeartbeatRequest;
use App\Http\Requests\Api\V1\Kiosk\IssueActivationRequest;
use App\Http\Requests\Api\V1\Kiosk\MaintenanceKioskRequest;
use App\Http\Requests\Api\V1\Kiosk\StoreKioskRequest;
use App\Http\Requests\Api\V1\Kiosk\UpdateKioskRequest;
use App\Http\Resources\Api\V1\DestinationResource;
use App\Http\Resources\Api\V1\DeviceResource;
use App\Models\Device;
use App\Models\Setting;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\ApiTimestamp;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KioskController extends Controller
{
    public function store(StoreKioskRequest $request, RegisterDevice $register): JsonResponse
    {
        $user = $this->requireStaff($request);
        $this->authorize('create', Device::class);

        $device = $register->handle($user, $request->payload(), $request);

        return ApiResponse::success(
            (new DeviceResource($device))->resolve($request),
            status: 201,
        );
    }

    public function issueActivation(
        IssueActivationRequest $request,
        Device $device,
        IssueDeviceActivation $issue,
    ): JsonResponse {
        $user = $this->requireStaff($request);
        $this->authorize('activate', $device);

        $result = $issue->handle($user, $device, $request->rotateSecret(), $request);

        return ApiResponse::success([
            'device' => (new DeviceResource($result['device']))->resolve($request),
            'activation_code' => $result['activation_code'],
            'expires_at' => ApiTimestamp::utc($result['expires_at']),
        ]);
    }

    public function exchangeActivation(
        ExchangeActivationRequest $request,
        ExchangeDeviceActivation $exchange,
    ): JsonResponse {
        $result = $exchange->handle($request->payload(), $request);

        return ApiResponse::success([
            'token' => $result['plain_text_token'],
            'token_type' => 'Bearer',
            'device' => (new DeviceResource($result['device']))->resolve($request),
        ]);
    }

    public function heartbeat(
        HeartbeatRequest $request,
        RecordDeviceHeartbeat $heartbeat,
    ): JsonResponse {
        $device = $this->requireDevice($request);

        $result = $heartbeat->handle($device, $request->payload(), $request);

        return ApiResponse::success([
            'server_time' => ApiTimestamp::utc($result['server_time']),
            'device_status' => $result['device']->status->value,
            'commands' => $result['commands'],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $device = $this->requireDevice($request);

        return ApiResponse::success(
            (new DeviceResource($device))->resolve($request) + [
                'online' => $device->isOnline(),
            ],
        );
    }

    public function config(Request $request): JsonResponse
    {
        $device = $this->requireDevice($request);
        $device->loadMissing('destination');

        if ($device->status->value === 'disabled' || ! $device->is_active) {
            throw new AuthorizationException('Disabled devices cannot read config.');
        }

        return ApiResponse::success([
            'destination' => $device->destination !== null
                ? (new DestinationResource($device->destination))->resolve($request)
                : null,
            'locale_default' => 'id',
            'currency' => 'IDR',
            'payment_ttl_seconds' => Setting::orderPaymentTtlMinutes() * 60,
            'features' => [
                'print' => true,
            ],
        ]);
    }

    public function update(
        UpdateKioskRequest $request,
        Device $device,
        UpdateDevice $update,
    ): JsonResponse {
        $user = $this->requireStaff($request);
        $this->authorize('update', $device);

        $updated = $update->handle($user, $device, $request->payload(), $request);

        return ApiResponse::success(
            (new DeviceResource($updated))->resolve($request),
        );
    }

    public function maintenance(
        MaintenanceKioskRequest $request,
        Device $device,
        SetDeviceMaintenance $setMaintenance,
    ): JsonResponse {
        $user = $this->requireStaff($request);
        $this->authorize('maintenance', $device);

        $updated = $setMaintenance->handle($user, $device, $request->enabled(), $request);

        return ApiResponse::success(
            (new DeviceResource($updated))->resolve($request),
        );
    }

    public function deactivate(
        Request $request,
        Device $device,
        DeactivateDevice $deactivate,
    ): JsonResponse {
        $user = $this->requireStaff($request);
        $this->authorize('deactivate', $device);

        $updated = $deactivate->handle($user, $device, $request);

        return ApiResponse::success(
            (new DeviceResource($updated))->resolve($request),
        );
    }

    public function health(Request $request, GetFleetHealth $fleetHealth): JsonResponse
    {
        $user = $this->requireStaff($request);
        $this->authorize('viewAny', Device::class);

        return ApiResponse::success(
            $fleetHealth->handle($user)->values()->all(),
        );
    }

    private function requireStaff(Request $request): User
    {
        $principal = $request->user();

        if (! $principal instanceof User) {
            throw new AuthorizationException('Only staff users may manage kiosks.');
        }

        return $principal;
    }

    private function requireDevice(Request $request): Device
    {
        $principal = $request->user();

        if (! $principal instanceof Device) {
            throw new AuthorizationException('Only device tokens may access this endpoint.');
        }

        return $principal;
    }
}

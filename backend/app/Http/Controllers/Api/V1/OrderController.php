<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Orders\CancelOrder;
use App\Actions\Orders\CreateOrder;
use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Orders\CancelOrderRequest;
use App\Http\Requests\Api\V1\Orders\StoreOrderRequest;
use App\Http\Resources\Api\V1\OrderResource;
use App\Models\Device;
use App\Models\Order;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\Idempotency\IdempotencyManager;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function store(
        StoreOrderRequest $request,
        CreateOrder $create,
        IdempotencyManager $idempotency,
    ): JsonResponse {
        $principal = $this->requirePrincipal($request);

        if ($principal instanceof User) {
            $this->authorize('create', Order::class);
        }

        $order = $create->handle(
            $principal,
            $request->orderPayload(),
            $idempotency->keyFromRequest($request),
            $request,
        );

        return ApiResponse::success(
            (new OrderResource($order))->resolve($request),
            status: 201,
        );
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $this->authorizeView($request, $order);

        $order->loadMissing(['items', 'payments', 'tickets.order']);

        return ApiResponse::success(
            (new OrderResource($order))->resolve($request),
        );
    }

    public function cancel(
        CancelOrderRequest $request,
        Order $order,
        CancelOrder $cancel,
    ): JsonResponse {
        $principal = $this->requirePrincipal($request);

        if ($principal instanceof User) {
            $this->authorize('cancel', $order);
        }

        $order = $cancel->handle(
            $principal,
            $order,
            $request->reason(),
            $request,
        );

        return ApiResponse::success(
            (new OrderResource($order))->resolve($request),
        );
    }

    private function authorizeView(Request $request, Order $order): void
    {
        $principal = $request->user();

        if ($principal instanceof Device) {
            if ((int) $order->device_id !== (int) $principal->getKey()) {
                throw new DomainException(
                    'resource.not_found',
                    'Order not found.',
                    404,
                );
            }

            return;
        }

        if ($principal instanceof User) {
            $this->authorize('view', $order);

            return;
        }

        throw new AuthorizationException('Unauthenticated order principal.');
    }

    private function requirePrincipal(Request $request): Device|User
    {
        $principal = $request->user();

        if ($principal instanceof Device || $principal instanceof User) {
            return $principal;
        }

        throw new AuthorizationException('Unauthenticated order principal.');
    }
}

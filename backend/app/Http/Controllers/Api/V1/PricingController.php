<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Pricing\QuoteOrderAction;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\QuoteRequest;
use App\Http\Resources\Api\V1\QuoteResource;
use App\Models\Device;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\Authorization\Authorizer;
use App\Support\DeviceAbilities;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PricingController extends Controller
{
    public function quote(QuoteRequest $request, QuoteOrderAction $quote): JsonResponse
    {
        $this->authorizeQuote($request);

        $payload = $request->quotePayload();
        $result = $quote->handle($payload['destination_id'], $payload['items']);

        return ApiResponse::success(
            (new QuoteResource($result))->resolve($request),
        );
    }

    private function authorizeQuote(Request $request): void
    {
        $principal = $request->user();

        if ($principal instanceof Device) {
            if (! $principal->tokenCan(DeviceAbilities::CATALOG_READ)) {
                throw new AuthorizationException('Missing device ability: catalog:read');
            }

            return;
        }

        if ($principal instanceof User) {
            $canCatalogRead = Authorizer::check($principal, PermissionName::TicketTypesView);
            $canAssistedSale = Authorizer::check($principal, PermissionName::OrdersCreate);

            if (! $canCatalogRead && ! $canAssistedSale) {
                throw new AuthorizationException('Missing permission for pricing quote.');
            }

            return;
        }

        throw new AuthorizationException('Unauthenticated quote principal.');
    }
}

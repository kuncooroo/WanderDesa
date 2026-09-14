<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use App\Support\Idempotency\IdempotencyManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureIdempotencyKey
{
    /**
     * Require a valid Idempotency-Key header on the routes this middleware is applied to.
     *
     * Apply to critical POSTs (docs/08, IdempotencyScope::requiredEndpoints):
     * orders, payments (initiate / confirm-cash / refund), and check-ins.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header(IdempotencyManager::HEADER);

        if (! is_string($header) || trim($header) === '') {
            return ApiResponse::error(
                'validation.failed',
                'The Idempotency-Key header is required.',
                422,
                [[
                    'field' => IdempotencyManager::HEADER,
                    'code' => 'validation.required',
                    'message' => 'The Idempotency-Key header is required.',
                ]],
            );
        }

        $key = trim($header);

        if (strlen($key) > IdempotencyManager::MAX_KEY_LENGTH) {
            return ApiResponse::error(
                'validation.failed',
                'The Idempotency-Key header may not be greater than '.IdempotencyManager::MAX_KEY_LENGTH.' characters.',
                422,
                [[
                    'field' => IdempotencyManager::HEADER,
                    'code' => 'validation.max',
                    'message' => 'The Idempotency-Key header may not be greater than '.IdempotencyManager::MAX_KEY_LENGTH.' characters.',
                ]],
            );
        }

        $request->attributes->set('idempotency_key', $key);

        return $next($request);
    }
}

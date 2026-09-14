<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

final class ApiResponse
{
    /**
     * @param  array<string, mixed>|list<mixed>  $data
     * @param  array<string, mixed>  $meta
     */
    public static function success(array $data = [], array $meta = [], int $status = 200): JsonResponse
    {
        $payload = [
            'success' => true,
            'data' => $data,
            'request_id' => self::requestId(),
        ];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    /**
     * @param  list<array{field?: string, code?: string, message?: string}>  $details
     */
    public static function error(
        string $code,
        string $message,
        int $status = 400,
        array $details = [],
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ],
            'request_id' => self::requestId(),
        ], $status);
    }

    private static function requestId(): string
    {
        $incoming = request()->header('X-Request-Id');

        if (is_string($incoming) && $incoming !== '' && strlen($incoming) <= 64) {
            return $incoming;
        }

        return 'req_'.Str::lower(Str::random(16));
    }
}

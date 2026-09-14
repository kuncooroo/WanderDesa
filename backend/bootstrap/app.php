<?php

use App\Exceptions\AuthException;
use App\Exceptions\DomainException;
use App\Http\Middleware\EnsureIdempotencyKey;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\SetSecurityHeaders;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'idempotency' => EnsureIdempotencyKey::class,
        ]);

        $middleware->append(SetSecurityHeaders::class);
        $middleware->trustProxies(at: '*');

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (AuthException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error($e->errorCode, $e->getMessage(), $e->statusCode);
            }
        });

        $exceptions->render(function (DomainException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error(
                    $e->errorCode,
                    $e->getMessage(),
                    $e->statusCode,
                    $e->details,
                );
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error('auth.unauthenticated', 'Unauthenticated.', 401);
            }
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error(
                    'auth.forbidden',
                    $e->getMessage() !== '' ? $e->getMessage() : 'This action is unauthorized.',
                    403,
                );
            }

            return response()->view('errors.403', [
                'exception' => $e,
            ], 403);
        });

        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error('auth.forbidden', 'This action is unauthorized.', 403);
            }

            return response()->view('errors.403', [
                'exception' => $e,
            ], 403);
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $details = [];
                foreach ($e->errors() as $field => $messages) {
                    foreach ($messages as $message) {
                        $details[] = [
                            'field' => $field,
                            'code' => 'validation.failed',
                            'message' => $message,
                        ];
                    }
                }

                return ApiResponse::error(
                    'validation.failed',
                    $e->getMessage(),
                    422,
                    $details,
                );
            }
        });

        $exceptions->render(function (TooManyRequestsHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error(
                    'rate_limit.exceeded',
                    'Too many attempts. Please try again later.',
                    429,
                );
            }
        });
    })->create();

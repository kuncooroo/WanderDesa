<?php

namespace App\Http\Middleware;

use App\Enums\DeviceStatus;
use App\Models\Device;
use App\Models\User;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class EnsureUserIsActive
{
    /**
     * Block inactive staff users (and inactive devices on API).
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $principal = $request->user();

        if ($principal instanceof User && ! $principal->is_active) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error(
                    'auth.user_inactive',
                    'This account is inactive.',
                    403,
                );
            }

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Akun ini tidak aktif.']);
        }

        if ($principal instanceof Device && $this->deviceBlocked($principal)) {
            return ApiResponse::error(
                'device.inactive',
                'This device is inactive.',
                403,
            );
        }

        return $next($request);
    }

    private function deviceBlocked(Device $device): bool
    {
        if (! $device->is_active) {
            return true;
        }

        $status = $device->status instanceof DeviceStatus
            ? $device->status
            : DeviceStatus::tryFrom((string) $device->status);

        return $status === DeviceStatus::Disabled;
    }
}

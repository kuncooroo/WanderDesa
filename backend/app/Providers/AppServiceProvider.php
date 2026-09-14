<?php

namespace App\Providers;

use App\Integrations\Gate\GateController;
use App\Integrations\Gate\NullGateController;
use App\Integrations\Payments\NullPaymentGateway;
use App\Integrations\Payments\PaymentGateway;
use App\Integrations\Payments\SandboxPaymentGateway;
use App\Models\Device;
use App\Models\Report;
use App\Models\User;
use App\Policies\ReportPolicy;
use App\Support\Authorization\PermissionCatalog;
use App\View\Composers\DashboardShellComposer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, function (): PaymentGateway {
            return match (config('payments.gateway')) {
                'null' => new NullPaymentGateway,
                default => new SandboxPaymentGateway,
            };
        });

        $this->app->bind(GateController::class, NullGateController::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('catalog', function (Request $request) {
            $principal = $request->user();

            if ($principal instanceof User || $principal instanceof Device) {
                return Limit::perMinute(120)->by($principal::class.'|'.$principal->getKey());
            }

            return Limit::perMinute(120)->by($request->ip());
        });

        RateLimiter::for('commerce', function (Request $request) {
            $principal = $request->user();

            if ($principal instanceof User || $principal instanceof Device) {
                return Limit::perMinute(30)->by($principal::class.'|'.$principal->getKey());
            }

            return Limit::perMinute(30)->by($request->ip());
        });

        RateLimiter::for('webhooks', function (Request $request) {
            return Limit::perMinute(300)->by($request->ip());
        });

        RateLimiter::for('access', function (Request $request) {
            $principal = $request->user();

            if ($principal instanceof User || $principal instanceof Device) {
                return Limit::perMinute(60)->by($principal::class.'|'.$principal->getKey());
            }

            return Limit::perMinute(60)->by($request->ip());
        });

        RateLimiter::for('heartbeat', function (Request $request) {
            $principal = $request->user();

            if ($principal instanceof Device) {
                return Limit::perMinute(120)->by('device|'.$principal->getKey());
            }

            return Limit::perMinute(120)->by($request->ip());
        });

        RateLimiter::for('reports', function (Request $request) {
            $principal = $request->user();

            if ($principal instanceof User) {
                return Limit::perMinute(30)->by('user|'.$principal->getKey());
            }

            return Limit::perMinute(30)->by($request->ip());
        });

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        /*
         * Permission string abilities (e.g. $user->can('orders.create')).
         * Unknown abilities fall through to Policies. Deny by default for
         * known catalog permissions the user does not hold.
         */
        Gate::policy(Report::class, ReportPolicy::class);

        Gate::before(function ($user, string $ability) {
            if (! $user instanceof User) {
                return null;
            }

            if (! PermissionCatalog::isKnown($ability)) {
                return null;
            }

            if (PermissionCatalog::isNeverGrantedToHumans($ability)) {
                return false;
            }

            return $user->hasPermission($ability);
        });

        View::composer('layouts.app', DashboardShellComposer::class);
    }
}

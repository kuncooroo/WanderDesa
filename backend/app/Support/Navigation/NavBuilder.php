<?php

namespace App\Support\Navigation;

use App\Models\User;
use App\Support\Authorization\NavPermissionMap;
use Illuminate\Support\Facades\Route;

/**
 * Permission-filtered dashboard navigation (UX only — Policies still enforce).
 *
 * @see docs/09-UI-UX.md shell layout + role matrix
 */
final class NavBuilder
{
    /**
     * @return list<array{group: string, items: list<array<string, mixed>>}>
     */
    public function grouped(User $user): array
    {
        $groups = [];

        foreach ($this->catalog() as $group => $items) {
            $visible = [];

            foreach ($items as $item) {
                if (! NavPermissionMap::canSee($user, $item['key'])) {
                    continue;
                }

                $visible[] = $this->hydrate($item);
            }

            if ($visible !== []) {
                $groups[] = [
                    'group' => $group,
                    'items' => $visible,
                ];
            }
        }

        return $groups;
    }

    /**
     * @return list<string>
     */
    public function visibleKeys(User $user): array
    {
        $keys = [];

        foreach ($this->grouped($user) as $group) {
            foreach ($group['items'] as $item) {
                $keys[] = $item['key'];
            }
        }

        return $keys;
    }

    /**
     * @return list<array{label: string, url: string|null}>
     */
    public function breadcrumbs(?string $routeName = null): array
    {
        $routeName ??= Route::currentRouteName();
        $trail = [
            ['label' => 'Beranda', 'url' => route('dashboard')],
        ];

        if ($routeName === null || $routeName === 'dashboard') {
            return $trail;
        }

        foreach ($this->catalog() as $items) {
            foreach ($items as $item) {
                if (($item['route'] ?? null) === $routeName) {
                    $trail[] = [
                        'label' => $item['label'],
                        'url' => null,
                    ];

                    return $trail;
                }
            }
        }

        if ($routeName === 'dashboard.profile') {
            $trail[] = ['label' => 'Profil', 'url' => null];
        }

        return $trail;
    }

    /**
     * @return array<string, list<array{key: string, label: string, route: string|null, stub?: bool}>>
     */
    private function catalog(): array
    {
        return [
            'Operasional' => [
                ['key' => 'home', 'label' => 'Beranda', 'route' => 'dashboard'],
                ['key' => 'assisted_sale', 'label' => 'Penjualan dibantu', 'route' => 'dashboard.assisted-sale'],
                ['key' => 'orders', 'label' => 'Pesanan', 'route' => 'dashboard.orders'],
                ['key' => 'tickets', 'label' => 'Tiket', 'route' => 'dashboard.tickets'],
                ['key' => 'checkin', 'label' => 'Check-in', 'route' => 'dashboard.check-in'],
            ],
            'Katalog' => [
                ['key' => 'catalog', 'label' => 'Destinasi & tiket', 'route' => 'dashboard.catalog'],
            ],
            'Perangkat' => [
                ['key' => 'kiosks', 'label' => 'Kiosk', 'route' => 'dashboard.kiosks'],
            ],
            'Keuangan' => [
                ['key' => 'payments', 'label' => 'Pembayaran', 'route' => 'dashboard.payments'],
                ['key' => 'cashier_shifts', 'label' => 'Shift & rekonsiliasi', 'route' => 'dashboard.cashier-shifts'],
            ],
            'Laporan' => [
                ['key' => 'reports', 'label' => 'Laporan', 'route' => 'dashboard.reports'],
            ],
            'Administrasi' => [
                ['key' => 'users', 'label' => 'Pengguna', 'route' => 'dashboard.users'],
                ['key' => 'roles', 'label' => 'Peran', 'route' => 'dashboard.roles'],
                ['key' => 'audit_logs', 'label' => 'Audit logs', 'route' => 'dashboard.audit-logs'],
            ],
            'Sistem' => [
                ['key' => 'settings', 'label' => 'Pengaturan', 'route' => 'dashboard.settings'],
            ],
        ];
    }

    /**
     * @param  array{key: string, label: string, route: string|null, stub?: bool}  $item
     * @return array<string, mixed>
     */
    private function hydrate(array $item): array
    {
        $route = $item['route'] ?? null;
        $url = null;
        $active = false;

        if (is_string($route) && Route::has($route)) {
            $url = route($route);
            $active = request()->routeIs($route)
                || ($route === 'dashboard' && request()->routeIs('dashboard'));
        }

        return [
            'key' => $item['key'],
            'label' => $item['label'],
            'route' => $route,
            'url' => $url,
            'active' => $active,
            'stub' => (bool) ($item['stub'] ?? false),
        ];
    }
}

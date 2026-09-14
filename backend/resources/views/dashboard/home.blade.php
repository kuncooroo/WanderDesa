@extends('layouts.app')

@section('title', 'Beranda')

@section('content')
    <div class="page space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="page-title">Halo, {{ $user->name }}</h1>
                <p class="page-desc">
                    Ringkasan operasional · {{ $snapshot['date'] }} ({{ $snapshot['timezone'] }})
                </p>
                @if ($user->last_login_at)
                    <p class="mt-1 text-sm text-wd-muted">
                        Login terakhir: {{ $user->last_login_at->timezone(config('app.timezone')) }}
                    </p>
                @endif
            </div>
            @if (count($snapshot['shortcuts']) > 0)
                <div class="flex flex-wrap gap-2">
                    @foreach ($snapshot['shortcuts'] as $shortcut)
                        <a
                            href="{{ $shortcut['url'] }}"
                            class="btn {{ $shortcut['primary'] ? 'btn-primary' : '' }}"
                        >{{ $shortcut['label'] }}</a>
                    @endforeach
                </div>
            @endif
        </div>

        @if (count($snapshot['widgets']) === 0 && count($snapshot['shortcuts']) === 0)
            <div class="alert alert-info">
                Tidak ada modul operasional yang diizinkan untuk peran Anda. Hubungi admin jika ini tidak benar.
            </div>
        @elseif (count($snapshot['widgets']) === 0)
            <div class="alert alert-info">
                Belum ada widget ringkasan untuk peran Anda. Gunakan pintasan di atas atau menu samping.
            </div>
        @endif

        @php
            $canSeeRevenueChart = \App\Support\Authorization\Authorizer::check(
                $user,
                \App\Enums\PermissionName::ReportsView,
            ) || \App\Support\Authorization\Authorizer::check(
                $user,
                \App\Enums\PermissionName::AnalyticsView,
            );
        @endphp
        @if ($canSeeRevenueChart)
            <livewire:dashboard.home-revenue-chart />
        @endif

        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ($snapshot['widgets'] as $widget)
                <div class="card space-y-4" wire:key="widget-{{ $widget['type'] }}">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-base font-semibold">{{ $widget['title'] }}</h2>
                        @if (! empty($widget['empty']))
                            <span class="text-xs text-wd-muted">Belum ada data</span>
                        @endif
                    </div>

                    @if (! empty($widget['stats']))
                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach ($widget['stats'] as $stat)
                                <div class="stat-card">
                                    <div class="stat-label">{{ $stat['label'] }}</div>
                                    <div class="stat-value text-base {{ ! empty($stat['money']) ? 'text-wd-accent tabular-nums' : '' }}">
                                        @if (! empty($stat['money']))
                                            Rp {{ number_format((int) $stat['value'], 0, ',', '.') }}
                                        @else
                                            {{ $stat['value'] }}
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if (($widget['type'] ?? '') === 'sales_today' && ! empty($widget['channels']))
                        <div class="table-wrap">
                            <table class="table min-w-0">
                                <thead>
                                    <tr>
                                        <th>Kanal</th>
                                        <th>Order</th>
                                        <th>Gross</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($widget['channels'] as $channel)
                                        <tr>
                                            <td>{{ $channel['channel'] }}</td>
                                            <td>{{ $channel['order_count'] }}</td>
                                            <td class="tabular-nums">Rp {{ number_format((int) $channel['gross_sales'], 0, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    @if (($widget['type'] ?? '') === 'sensitive_audit')
                        @if (! empty($widget['empty']))
                            <p class="text-sm text-wd-muted">Belum ada peristiwa sensitif.</p>
                        @else
                            <div class="table-wrap">
                                <table class="table min-w-0">
                                    <thead>
                                        <tr>
                                            <th>Aksi</th>
                                            <th>Aktor</th>
                                            <th>Waktu</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($widget['rows'] as $row)
                                            <tr>
                                                <td><code class="text-xs">{{ $row['action'] }}</code></td>
                                                <td class="text-sm text-wd-muted">{{ $row['actor'] }}</td>
                                                <td class="whitespace-nowrap text-sm text-wd-muted">{{ $row['at'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    @endif
                </div>
            @endforeach
        </div>
    </div>
@endsection

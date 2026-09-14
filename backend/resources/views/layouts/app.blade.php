<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>
        @hasSection('title')
            @yield('title')
        @else
            {{ $title ?? 'Dashboard' }}
        @endif
        — {{ config('app.name') }}
    </title>
    <script>
        (() => {
            try {
                const stored = localStorage.getItem('wd.theme');
                const dark = stored === 'dark' || (stored !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.classList.toggle('dark', dark);
            } catch (_) { /* ignore */ }
        })();
    </script>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    @livewireStyles
</head>
<body class="min-h-screen bg-wd-bg text-wd-ink">
@php
    $navGroups = $navGroups ?? [];
    $user = auth()->user();
@endphp
<div
    class="min-h-screen"
    x-data="{
        sidebarOpen: false,
        dark: document.documentElement.classList.contains('dark'),
        toggleTheme() {
            this.dark = ! this.dark;
            document.documentElement.classList.toggle('dark', this.dark);
            try { localStorage.setItem('wd.theme', this.dark ? 'dark' : 'light'); } catch (_) {}
        }
    }"
    @keydown.escape.window="sidebarOpen = false"
>
    {{-- Top bar: full width (desktop + mobile) --}}
    <header class="sticky top-0 z-40 border-b border-wd-border bg-wd-card">
        <div class="flex h-14 items-center justify-between gap-3 px-4 sm:px-6">
            <div class="flex min-w-0 items-center gap-3">
                <button
                    type="button"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-wd-border text-wd-ink hover:bg-wd-sidebar lg:hidden"
                    @click="sidebarOpen = true"
                    aria-label="Buka menu"
                >
                    <span class="text-lg leading-none" aria-hidden="true">☰</span>
                </button>
                <a href="{{ route('dashboard') }}" class="truncate text-base font-semibold text-wd-accent sm:text-lg">
                    {{ config('app.name') }}
                </a>
            </div>

            <div class="flex shrink-0 items-center gap-2 sm:gap-3">
                <button
                    type="button"
                    class="icon-btn"
                    @click="toggleTheme()"
                    :aria-pressed="dark ? 'true' : 'false'"
                    :aria-label="dark ? 'Aktifkan mode terang' : 'Aktifkan mode gelap'"
                    :title="dark ? 'Mode terang' : 'Mode gelap'"
                >
                    <svg x-show="!dark" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
                    </svg>
                    <svg x-cloak x-show="dark" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
                    </svg>
                </button>

                <livewire:ops.notification-inbox />

                <div
                    class="relative"
                    x-data="{ open: false, leaveTimer: null }"
                    @mouseenter="clearTimeout(leaveTimer); open = true"
                    @mouseleave="leaveTimer = setTimeout(() => open = false, 180)"
                >
                    <button
                        type="button"
                        class="icon-btn"
                        @click="open = !open"
                        :aria-expanded="open ? 'true' : 'false'"
                        aria-label="Menu profil"
                        title="{{ $user?->name ?? 'Profil' }}"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true" class="h-4 w-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                        </svg>
                    </button>
                    <div
                        x-show="open"
                        x-cloak
                        x-transition.opacity.duration.150ms
                        @click.outside="open = false"
                        class="absolute right-0 z-50 mt-2 w-48 overflow-hidden rounded-lg border border-wd-border bg-wd-card py-1 shadow-lg"
                        style="display: none;"
                    >
                        <div class="border-b border-wd-border px-3 py-2">
                            <div class="truncate text-sm font-medium text-wd-ink">{{ $user?->name ?? 'Profil' }}</div>
                            <div class="truncate text-xs text-wd-muted">{{ $user?->email }}</div>
                        </div>
                        <a href="{{ route('dashboard.profile') }}" class="block px-3 py-2 text-sm text-wd-ink hover:bg-wd-sidebar">Profil</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="block w-full px-3 py-2 text-left text-sm text-wd-ink hover:bg-wd-sidebar">Keluar</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="relative flex min-h-[calc(100vh-3.5rem)]">
        {{-- Mobile backdrop --}}
        <div
            x-show="sidebarOpen"
            x-transition.opacity
            class="fixed inset-0 z-40 bg-black/30 lg:hidden"
            @click="sidebarOpen = false"
            style="display: none;"
        ></div>

        <aside
            class="fixed inset-y-0 left-0 z-50 flex w-60 -translate-x-full flex-col border-r border-wd-border bg-wd-card transition-transform duration-200 lg:static lg:translate-x-0 lg:self-stretch"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
            aria-label="Navigasi utama"
        >
            <div class="flex h-14 items-center justify-between border-b border-wd-border px-4 lg:hidden">
                <span class="font-semibold text-wd-accent">{{ config('app.name') }}</span>
                <button
                    type="button"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-wd-border hover:bg-wd-sidebar"
                    @click="sidebarOpen = false"
                    aria-label="Tutup menu"
                >✕</button>
            </div>

            <div class="flex-1 overflow-y-auto px-3 py-4">
                @include('layouts.partials.sidebar')
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <main class="flex-1 px-4 py-6 sm:px-6" id="main">
                @isset($slot)
                    {{ $slot }}
                @else
                    @yield('content')
                @endisset
            </main>

            <footer class="px-4 py-4 text-xs text-wd-muted sm:px-6">
                {{ config('app.name') }} · {{ app()->environment() }} · v0.1 MVP
            </footer>
        </div>
    </div>
</div>
@livewireScripts
<script src="https://cdn.jsdelivr.net/npm/apexcharts@4.5.0"></script>
<script>
    window.WanderDesaCharts = (() => {
        const accent = '#1f5c45';
        const muted = '#6b7280';
        const palette = [accent, '#3d8b6e', '#c4a35a', '#5b7c99'];

        const formatIdr = (value) =>
            'Rp ' + new Intl.NumberFormat('id-ID').format(Number(value) || 0);

        const mount = (el, options) => {
            if (! el || typeof ApexCharts === 'undefined') {
                return null;
            }

            if (el._wdChart) {
                el._wdChart.destroy();
                el._wdChart = null;
            }

            const chart = new ApexCharts(el, options);
            el._wdChart = chart;
            chart.render();

            return chart;
        };

        const updateOrMount = (el, options, seriesOnly) => {
            if (el && el._wdChart) {
                if (seriesOnly) {
                    el._wdChart.updateSeries(options.series, true);
                    if (options.labels) {
                        el._wdChart.updateOptions({ labels: options.labels }, false, true);
                    }
                    if (options.xaxis) {
                        el._wdChart.updateOptions({ xaxis: options.xaxis }, false, true);
                    }

                    return el._wdChart;
                }

                el._wdChart.updateOptions(options, true, true);

                return el._wdChart;
            }

            return mount(el, options);
        };

        return {
            lineRevenue(el, categories, series, opts = {}) {
                const options = {
                    chart: {
                        type: 'line',
                        height: opts.height || 288,
                        toolbar: { show: false },
                        fontFamily: 'inherit',
                        animations: { enabled: true, speed: 400 },
                    },
                    colors: [accent],
                    stroke: { curve: 'smooth', width: 3 },
                    markers: { size: 3, strokeWidth: 0 },
                    dataLabels: { enabled: false },
                    grid: { borderColor: '#e5e7eb', strokeDashArray: 4 },
                    series: [{ name: 'Pendapatan', data: series }],
                    xaxis: {
                        categories,
                        labels: {
                            style: { colors: muted, fontSize: '11px' },
                            rotate: categories.length > 14 ? -45 : 0,
                            hideOverlappingLabels: true,
                        },
                    },
                    yaxis: {
                        labels: {
                            style: { colors: muted, fontSize: '11px' },
                            formatter: (v) => formatIdr(v),
                        },
                    },
                    tooltip: {
                        y: { formatter: (v) => formatIdr(v) },
                    },
                    noData: { text: 'Tidak ada data' },
                };

                return updateOrMount(el, options, Boolean(opts.update));
            },

            donutMethods(el, labels, series, opts = {}) {
                const options = {
                    chart: {
                        type: 'donut',
                        height: opts.height || 300,
                        fontFamily: 'inherit',
                        animations: { enabled: true, speed: 400 },
                    },
                    colors: palette,
                    labels,
                    series,
                    legend: { position: 'bottom' },
                    dataLabels: { enabled: true },
                    plotOptions: {
                        pie: {
                            donut: {
                                size: '62%',
                                labels: {
                                    show: true,
                                    total: {
                                        show: true,
                                        label: 'Total',
                                        formatter: (w) =>
                                            w.globals.seriesTotals.reduce((a, b) => a + b, 0),
                                    },
                                },
                            },
                        },
                    },
                    tooltip: {
                        y: { formatter: (v) => `${v} pembayaran` },
                    },
                    noData: { text: 'Tidak ada data' },
                };

                return updateOrMount(el, options, Boolean(opts.update));
            },

            barTopProducts(el, categories, series, opts = {}) {
                const options = {
                    chart: {
                        type: 'bar',
                        height: opts.height || 300,
                        toolbar: { show: false },
                        fontFamily: 'inherit',
                        animations: { enabled: true, speed: 400 },
                    },
                    colors: [accent],
                    plotOptions: {
                        bar: {
                            borderRadius: 4,
                            horizontal: false,
                            columnWidth: '48%',
                        },
                    },
                    dataLabels: { enabled: false },
                    grid: { borderColor: '#e5e7eb', strokeDashArray: 4 },
                    series: [{ name: 'Unit terjual', data: series }],
                    xaxis: {
                        categories,
                        labels: {
                            style: { colors: muted, fontSize: '11px' },
                            trim: true,
                        },
                    },
                    yaxis: {
                        labels: {
                            style: { colors: muted, fontSize: '11px' },
                            formatter: (v) => String(Math.round(v)),
                        },
                    },
                    tooltip: {
                        y: { formatter: (v) => `${v} unit` },
                    },
                    noData: { text: 'Tidak ada data' },
                };

                return updateOrMount(el, options, Boolean(opts.update));
            },
        };
    })();
</script>
</body>
</html>

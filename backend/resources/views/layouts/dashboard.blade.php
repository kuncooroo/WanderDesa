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
    x-data="{ sidebarOpen: false }"
    @keydown.escape.window="sidebarOpen = false"
>
    <header class="sticky top-0 z-40 border-b border-wd-border bg-white">
        <div class="flex h-14 items-center justify-between gap-3 px-4 sm:px-6">
            <div class="flex min-w-0 items-center gap-3">
                <button
                    type="button"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-wd-border text-wd-ink hover:bg-gray-50 lg:hidden"
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
                <livewire:ops.notification-inbox />

                <details class="relative">
                    <summary class="cursor-pointer list-none rounded-lg border border-wd-border bg-white px-3 py-1.5 text-sm font-medium text-wd-ink hover:bg-gray-50 [&::-webkit-details-marker]:hidden">
                        {{ $user?->name ?? 'Profil' }}
                    </summary>
                    <div class="absolute right-0 z-50 mt-2 w-44 overflow-hidden rounded-lg border border-wd-border bg-white py-1 shadow-lg">
                        <a href="{{ route('dashboard.profile') }}" class="block px-3 py-2 text-sm text-wd-ink hover:bg-gray-50">Profil</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="block w-full px-3 py-2 text-left text-sm text-wd-ink hover:bg-gray-50">Keluar</button>
                        </form>
                    </div>
                </details>
            </div>
        </div>
    </header>

    <div class="relative flex min-h-[calc(100vh-3.5rem)]">
        <div
            x-show="sidebarOpen"
            x-transition.opacity
            class="fixed inset-0 z-40 bg-black/30 lg:hidden"
            @click="sidebarOpen = false"
            style="display: none;"
        ></div>

        <aside
            class="fixed inset-y-0 left-0 z-50 flex w-60 -translate-x-full flex-col border-r border-wd-border bg-white transition-transform duration-200 lg:static lg:translate-x-0 lg:self-stretch"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
            aria-label="Navigasi utama"
        >
            <div class="flex h-14 items-center justify-between border-b border-wd-border px-4 lg:hidden">
                <span class="font-semibold text-wd-accent">{{ config('app.name') }}</span>
                <button
                    type="button"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-wd-border hover:bg-gray-50"
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
</body>
</html>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Masuk' }} — {{ config('app.name') }}</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>

<body class="min-h-screen antialiased text-white selection:bg-emerald-500 selection:text-white">
    <div class="relative min-h-screen overflow-hidden">
        {{-- Background Wisata --}}
        <div
            class="absolute inset-0 bg-cover bg-center bg-no-repeat"
            style="background-image: url('{{ asset('images/wisata-login.jpg') }}');">
        </div>

        {{-- Overlay Gradient Hitam Transparan --}}
        <div class="absolute inset-0 bg-gradient-to-b from-black/40 via-black/60 to-black/80"></div>

        {{-- Login Content Container --}}
        <main class="relative z-10 mx-auto flex min-h-screen max-w-md items-center justify-center px-4 py-10">
            {{-- Card Glassmorphism --}}
            <div class="w-full rounded-3xl border border-white/20 bg-white/10 p-8 shadow-2xl backdrop-blur-xl">
                @yield('content')
            </div>
        </main>
    </div>
</body>

</html>
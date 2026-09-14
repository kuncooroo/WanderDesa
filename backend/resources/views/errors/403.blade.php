<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tidak berwenang — {{ config('app.name') }}</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="grid min-h-screen place-items-center bg-wd-bg px-6 py-10 text-wd-ink antialiased">
    <main class="card max-w-md text-center">
        <h1 class="mt-0 text-xl font-semibold">Tidak berwenang</h1>
        <p class="muted mt-2">Anda tidak memiliki izin untuk membuka halaman ini.</p>
        <div class="mt-5">
            @auth
                <a class="btn btn-primary" href="{{ route('dashboard') }}">Kembali ke dashboard</a>
            @else
                <a class="btn btn-primary" href="{{ route('login') }}">Masuk</a>
            @endauth
        </div>
    </main>
</body>
</html>

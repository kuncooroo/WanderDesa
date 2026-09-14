@extends('layouts.app')

@section('title', $title)

@section('content')
    <div class="page space-y-6">
        <div>
            <h1 class="page-title">{{ $title }}</h1>
            <p class="page-desc">Modul ini diizinkan untuk akun Anda, tetapi halaman operasional penuh belum diluncurkan di MVP ini.</p>
        </div>

        <div class="card max-w-xl space-y-4">
            <div class="skeleton h-16 w-full"></div>
            <a class="btn" href="{{ route('dashboard') }}">Kembali ke beranda</a>
        </div>
    </div>
@endsection

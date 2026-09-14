@extends('layouts.app')

@section('title', 'Profil')

@section('content')
    <div class="page space-y-6">
        <div>
            <h1 class="page-title">Profil</h1>
            <p class="page-desc">Perubahan peran hanya oleh Super Admin. Tidak ada elevasi mandiri.</p>
        </div>

        <div class="card max-w-xl space-y-3 text-sm">
            <div class="flex justify-between gap-4 border-b border-wd-border py-2">
                <span class="text-wd-muted">Nama</span>
                <span class="font-medium">{{ $user->name }}</span>
            </div>
            <div class="flex justify-between gap-4 border-b border-wd-border py-2">
                <span class="text-wd-muted">Email</span>
                <span class="font-medium">{{ $user->email }}</span>
            </div>
            @if ($user->roles->isNotEmpty())
                <div class="flex justify-between gap-4 py-2">
                    <span class="text-wd-muted">Peran</span>
                    <span class="font-medium text-right">{{ $user->roles->pluck('name')->implode(', ') }}</span>
                </div>
            @endif
        </div>
    </div>
@endsection

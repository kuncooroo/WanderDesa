@php
    /** @var list<array{label: string, url: string|null}> $breadcrumbs */
    $user = auth()->user();
@endphp
<header class="shell-top">
    <div class="shell-top-left">
        <button type="button" class="icon-btn shell-menu-toggle" data-shell-toggle aria-label="Buka menu">☰</button>
        <nav class="shell-breadcrumbs" aria-label="Breadcrumb">
            @foreach ($breadcrumbs as $crumb)
                @if (! $loop->first)
                    <span aria-hidden="true">/</span>
                @endif
                @if ($crumb['url'] && ! $loop->last)
                    <a href="{{ $crumb['url'] }}">{{ $crumb['label'] }}</a>
                @else
                    <span @if($loop->last) aria-current="page" @endif>{{ $crumb['label'] }}</span>
                @endif
            @endforeach
        </nav>
    </div>
    <div class="shell-top-right">
        <livewire:ops.notification-inbox />
        <div class="shell-profile">
            <details>
                <summary>{{ $user?->name ?? 'Profil' }}</summary>
                <div class="shell-profile-menu">
                    <a href="{{ route('dashboard.profile') }}">Profil</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit">Keluar</button>
                    </form>
                </div>
            </details>
        </div>
    </div>
</header>

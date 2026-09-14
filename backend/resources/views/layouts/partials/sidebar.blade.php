@php
    /** @var list<array{group: string, items: list<array<string, mixed>>}> $navGroups */
    $itemCount = collect($navGroups)->sum(fn ($group) => count($group['items']));
@endphp

@if ($itemCount <= 1)
    <div class="rounded-lg border border-wd-border bg-wd-sidebar px-3 py-3 text-sm text-wd-muted">
        Tidak ada modul operasional yang diizinkan untuk akun ini. Hubungi administrator.
    </div>
@endif

@if ($navGroups !== [])
    <nav class="space-y-0.5">
        @foreach ($navGroups as $group)
            @foreach ($group['items'] as $item)
                @if ($item['url'])
                    <a
                        href="{{ $item['url'] }}"
                        @click="typeof sidebarOpen !== 'undefined' && (sidebarOpen = false)"
                        class="flex items-center justify-between rounded-lg px-3 py-2 text-sm transition
                            {{ $item['active']
                                ? 'bg-wd-bg font-medium text-wd-accent'
                                : 'text-wd-ink hover:bg-wd-bg hover:text-wd-accent' }}"
                        @if (! empty($item['stub'])) title="Halaman stub — modul penuh menyusul" @endif
                    >
                        <span>{{ $item['label'] }}</span>
                        @if (! empty($item['stub']))
                            <span class="text-[10px] font-medium uppercase tracking-wide {{ $item['active'] ? 'text-wd-accent/70' : 'text-wd-muted' }}">soon</span>
                        @endif
                    </a>
                @endif
            @endforeach
        @endforeach
    </nav>
@endif

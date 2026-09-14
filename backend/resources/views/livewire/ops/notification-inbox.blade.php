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
        aria-label="Notifikasi operasional"
        title="Notifikasi"
    >
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true" class="h-4 w-4">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
        </svg>
        @if ($unreadCount > 0)
            <span class="absolute -right-1 -top-1 inline-flex min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold leading-4 text-white">
                {{ $unreadCount > 9 ? '9+' : $unreadCount }}
            </span>
        @endif
    </button>

    <div
        x-show="open"
        x-cloak
        x-transition.opacity.duration.150ms
        @click.outside="open = false"
        class="absolute right-0 z-50 mt-2 w-[min(22rem,86vw)] max-h-96 overflow-auto rounded-xl border border-wd-border bg-wd-card p-2 shadow-lg"
        role="region"
        aria-label="Kotak masuk notifikasi"
        style="display: none;"
    >
        <div class="mb-2 flex items-center justify-between gap-2 border-b border-wd-border px-2 pb-2">
            <strong class="text-sm text-wd-ink">Notifikasi</strong>
            @if ($unreadCount > 0)
                <button type="button" class="btn btn-sm" wire:click="markAllRead">Tandai dibaca</button>
            @endif
        </div>
        @forelse ($items as $item)
            @php
                $data = is_array($item->data) ? $item->data : [];
                $href = $this->hrefFor($item);
                $unread = $item->read_at === null;
            @endphp
            <div class="rounded-lg px-2 py-2 {{ $unread ? 'bg-wd-sidebar' : '' }}" wire:key="n-{{ $item->id }}">
                <div class="text-sm font-semibold text-wd-ink">{{ $data['title'] ?? 'Pemberitahuan' }}</div>
                <div class="muted text-xs">{{ $data['message'] ?? '' }}</div>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    @if ($unread)
                        <button type="button" class="btn btn-sm" wire:click="markRead('{{ $item->id }}')">Dibaca</button>
                    @endif
                    @if ($href)
                        <a href="{{ $href }}" class="btn btn-sm">Buka</a>
                    @endif
                </div>
            </div>
        @empty
            <p class="muted m-0 px-2 py-3 text-sm">Tidak ada notifikasi</p>
        @endforelse
    </div>
</div>

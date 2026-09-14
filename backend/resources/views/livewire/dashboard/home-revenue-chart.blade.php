<div class="card space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-base font-semibold">Tren Pendapatan Harian</h2>
            <p class="text-sm text-wd-muted">
                Gross dari pesanan paid · {{ $trend['from'] }} – {{ $trend['to'] }} ({{ $trend['timezone'] }})
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button
                type="button"
                class="btn-seg {{ $days === 7 ? 'btn-seg-active' : '' }}"
                wire:click="setDays(7)"
            >7 hari</button>
            <button
                type="button"
                class="btn-seg {{ $days === 30 ? 'btn-seg-active' : '' }}"
                wire:click="setDays(30)"
            >30 hari</button>
        </div>
    </div>

    <p class="text-sm text-wd-muted" wire:loading wire:target="setDays">Memuat grafik…</p>

    <div wire:ignore>
        <div id="home-revenue-trend-chart" class="min-h-[18rem] w-full"></div>
    </div>
</div>

@script
<script>
    const el = document.getElementById('home-revenue-trend-chart');

    const paint = (payload, isUpdate = false) => {
        if (! window.WanderDesaCharts || ! el) {
            return;
        }

        window.WanderDesaCharts.lineRevenue(
            el,
            payload.categories ?? [],
            payload.series ?? [],
            { update: isUpdate },
        );
    };

    paint(@json($trend), false);

    $wire.$watch('days', async () => {
        const payload = await $wire.chartPayload();
        paint(payload, true);
    });
</script>
@endscript

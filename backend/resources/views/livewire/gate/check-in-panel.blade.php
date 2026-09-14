<div class="page space-y-6">
    <div>
        <h1 class="page-title">Check-in gerbang</h1>
        <p class="page-desc">Scanner adalah input. Keputusan ALLOW/DENY hanya dari server. Jangan buka gerbang jika hasil belum ALLOW.</p>
    </div>

    <div class="card space-y-5">
        <div wire:offline class="alert alert-warn mb-0">
            Tidak ada koneksi ke server. Jangan izinkan masuk secara lokal.
        </div>

        @if ($errorMessage !== '')
            <div class="alert alert-error mb-0" role="alert">{{ $errorMessage }}</div>
        @endif

        <form wire:submit="submitCheckIn" class="grid gap-4">
            <label class="field">
                <span class="field-label">Destinasi</span>
                <select wire:model.live="destinationId" class="field-select">
                    <option value="">— pilih —</option>
                    @foreach ($destinations as $destination)
                        <option value="{{ $destination->id }}">{{ $destination->name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="field">
                <span class="field-label">Gerbang (opsional)</span>
                <select wire:model="gateId" class="field-select">
                    <option value="">— tanpa gerbang —</option>
                    @foreach ($gates as $gate)
                        <option value="{{ $gate->id }}">{{ $gate->name }} ({{ $gate->code }})</option>
                    @endforeach
                </select>
            </label>

            <div>
                @if ($entryMode === 'scan')
                    <label class="field">
                        <span class="field-label">Scan QR (fokus otomatis · Enter dari scanner HID)</span>
                        <input
                            id="gate-scan-input"
                            type="text"
                            wire:model="qrPayload"
                            autocomplete="off"
                            autocapitalize="off"
                            spellcheck="false"
                            autofocus
                            inputmode="none"
                            maxlength="512"
                            class="field-input font-mono text-base"
                            placeholder="Arahkan scanner ke QR, atau tempel payload"
                        >
                    </label>
                    <p class="mt-2 text-sm text-wd-muted">
                        Scanner tidak siap?
                        <button type="button" class="btn-ghost" wire:click="useManualEntry">
                            Masukkan kode tiket secara manual
                        </button>
                    </p>
                @else
                    <label class="field">
                        <span class="field-label">Kode tiket (cadangan jika scanner gagal)</span>
                        <input
                            id="gate-scan-input"
                            type="text"
                            wire:model="qrPayload"
                            autocomplete="off"
                            spellcheck="false"
                            autofocus
                            maxlength="64"
                            class="field-input text-lg tracking-wide"
                            placeholder="TCK-…"
                        >
                    </label>
                    <p class="mt-2 text-sm text-wd-muted">
                        <button type="button" class="btn-ghost" wire:click="useScanEntry">
                            Kembali ke scan QR
                        </button>
                    </p>
                @endif
            </div>

            <button
                type="submit"
                class="btn btn-primary"
                wire:loading.attr="disabled"
                wire:target="submitCheckIn"
                @disabled($busy)
            >
                <span wire:loading.remove wire:target="submitCheckIn">Check-in</span>
                <span wire:loading wire:target="submitCheckIn">Memeriksa tiket…</span>
            </button>
        </form>

        @if ($result !== '')
            <div
                role="status"
                class="rounded-xl border-2 px-6 py-8 text-center
                    {{ $isAllow
                        ? 'border-wd-accent bg-emerald-50 text-wd-ink'
                        : 'border-red-700 bg-red-50 text-red-800' }}"
            >
                <div class="text-4xl font-extrabold tracking-wide sm:text-5xl">
                    {{ $isAllow ? 'ALLOW' : 'DENY' }}
                </div>
                @if ($isAllow)
                    <div class="mt-2 text-lg font-semibold">Check-in berhasil</div>
                @endif
                @if ($reasonCode !== '')
                    <div class="mt-2 text-lg font-semibold">{{ $this->reasonLabel() }}</div>
                    <div class="mt-1 text-sm text-wd-muted"><code>{{ $reasonCode }}</code></div>
                @endif
                @if ($ticketCode !== '')
                    <div class="mt-3 text-sm text-wd-muted">Tiket {{ $ticketCode }} · status {{ $ticketStatus }}</div>
                @endif
            </div>
            <div>
                <button type="button" class="btn" wire:click="clearResult">Scan berikutnya</button>
            </div>
        @endif
    </div>
</div>

@script
<script>
    const focusScan = () => {
        queueMicrotask(() => document.getElementById('gate-scan-input')?.focus());
    };
    $wire.on('gate-scan-focus', focusScan);
    focusScan();
</script>
@endscript

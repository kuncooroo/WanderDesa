<div class="page space-y-6">
    <div>
        <h1 class="page-title">Tiket</h1>
        <p class="page-desc">Cari tiket yang sudah diterbitkan. Cetak ulang memakai payload server dan tidak membuat tiket baru.</p>
    </div>

    <div class="card space-y-5">
        <form wire:submit="searchTicket" class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <label class="field min-w-0 flex-1">
                <span class="field-label">Kode tiket</span>
                <input type="text" wire:model="ticketCode" placeholder="TCK…" class="field-input" autocomplete="off">
            </label>
            <button type="submit" class="btn btn-primary shrink-0">Cari</button>
        </form>

        @if ($errorMessage !== '')
            <p class="alert alert-error mb-0">{{ $errorMessage }}</p>
        @endif
        @if ($flashMessage !== '')
            <p class="rounded-lg border border-wd-border bg-emerald-50 px-4 py-3 text-sm text-wd-accent">{{ $flashMessage }}</p>
        @endif

        @if ($ticket)
            <div class="panel space-y-1">
                <div class="font-medium">{{ $ticket['ticket_code'] }} · status <code class="rounded bg-white px-1.5 py-0.5 text-xs">{{ $ticket['status'] }}</code></div>
                <div class="text-sm text-wd-muted">{{ $ticket['destination'] }} · {{ $ticket['ticket_type'] }}</div>
                <div class="text-sm text-wd-muted">Berlaku {{ $ticket['valid_start_at'] }} — {{ $ticket['valid_end_at'] }}</div>
                @if ($alreadyPrinted)
                    <div class="text-sm text-wd-muted">Sudah ada catatan cetak berhasil.</div>
                @endif
            </div>

            <div class="flex flex-wrap gap-2">
                @if ($this->canPrint() || $this->canReprint())
                    <button type="button" class="btn" wire:click="loadPrintPayload">Ambil payload cetak</button>
                @endif
                @if ((! $alreadyPrinted && $this->canPrint()) || $this->canReprint())
                    <button type="button" class="btn btn-primary" wire:click="recordPrintSuccess">
                        {{ $alreadyPrinted ? 'Catat cetak ulang' : 'Catat cetak berhasil' }}
                    </button>
                @endif
                @if ($this->canPrint() || $this->canReprint())
                    <button type="button" class="btn" wire:click="recordPrintFailure">Catat cetak gagal</button>
                @endif
            </div>

            @if ($printPayload)
                <div class="space-y-2">
                    <h2 class="text-base font-semibold">Print payload (server)</h2>
                    <p class="text-sm text-wd-muted">QR server — tampilkan jika printer fisik tidak siap. Jangan bagikan sembarangan.</p>
                    <div class="panel space-y-2">
                        <div class="font-medium">{{ $printPayload['ticket_code'] }}</div>
                        <div class="text-sm text-wd-muted">{{ $printPayload['destination']['name'] ?? '' }} · {{ $printPayload['ticket_type']['name'] ?? '' }}</div>
                        <pre class="code-box">{{ $printPayload['qr_payload'] }}</pre>
                    </div>
                </div>
            @endif
        @endif
    </div>
</div>

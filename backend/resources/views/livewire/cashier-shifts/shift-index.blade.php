<div class="page space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="page-title">Shift &amp; rekonsiliasi kasir</h1>
            <p class="page-desc">Buka shift sebelum penjualan tunai. Tutup shift dengan kas aktual — selisih dihitung server.</p>
        </div>
        @if ($canCreate)
            <button type="button" class="btn btn-primary" wire:click="openCreateModal">
                Buka shift
            </button>
        @endif
    </div>

    @if ($errorMessage !== '')
        <div class="alert alert-error" role="alert">{{ $errorMessage }}</div>
    @endif
    @if ($flashMessage !== '')
        <div class="rounded-lg border border-wd-border bg-emerald-50 px-4 py-3 text-sm text-wd-accent">{{ $flashMessage }}</div>
    @endif

    <div class="grid gap-3 sm:grid-cols-3">
        <div class="panel">
            <div class="text-xs text-wd-muted">Shift terbuka</div>
            <div class="mt-1 text-2xl font-semibold tabular-nums">{{ $openCount }}</div>
        </div>
        <div class="panel">
            <div class="text-xs text-wd-muted">Ditutup hari ini</div>
            <div class="mt-1 text-2xl font-semibold tabular-nums">{{ $closedToday }}</div>
        </div>
        <div class="panel">
            <div class="text-xs text-wd-muted">Total selisih hari ini</div>
            <div class="mt-1 text-2xl font-semibold tabular-nums {{ $differenceToday === 0 ? 'text-wd-accent' : 'text-red-700' }}">
                Rp {{ number_format($differenceToday, 0, ',', '.') }}
            </div>
        </div>
    </div>

    @if ($activeShift)
        <div class="rounded-lg border border-wd-accent/30 bg-emerald-50 px-4 py-3 text-sm">
            Shift Anda terbuka sejak
            <strong>{{ $activeShift->opened_at?->timezone(config('app.timezone')) }}</strong>
            · modal awal
            <strong>Rp {{ number_format((int) $activeShift->initial_cash, 0, ',', '.') }}</strong>
            · kas seharusnya
            <strong>Rp {{ number_format((int) $activeShift->expected_cash, 0, ',', '.') }}</strong>
            <div class="mt-2 flex flex-wrap gap-2">
                <button type="button" class="btn btn-sm btn-primary" wire:click="openCloseModal({{ $activeShift->id }})">
                    Tutup shift
                </button>
                <a href="{{ route('dashboard.cashier-shifts.show', $activeShift) }}" class="btn btn-sm" title="Lihat detail shift">Detail</a>
            </div>
        </div>
    @elseif ($canCreate)
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
            Belum ada shift terbuka. Buka shift sebelum mengakses Penjualan dibantu.
        </div>
    @endif

    <div class="card space-y-5">
        <div class="grid gap-3 sm:grid-cols-3">
            <label class="field">
                <span class="field-label">Status</span>
                <select wire:model.live="statusFilter" class="field-select">
                    <option value="">Semua</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}">{{ $status->value }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field">
                <span class="field-label">Dari</span>
                <input type="date" wire:model.live="fromDate" class="field-input">
            </label>
            <label class="field">
                <span class="field-label">Sampai</span>
                <input type="date" wire:model.live="toDate" class="field-input">
            </label>
        </div>

        <div class="table-wrap">
            <table class="table min-w-[64rem]">
                <thead>
                    <tr>
                        <th>Kasir</th>
                        <th>Dibuka</th>
                        <th>Ditutup</th>
                        <th>Modal</th>
                        <th>Penjualan</th>
                        <th>Refund</th>
                        <th>Expected</th>
                        <th>Aktual</th>
                        <th>Selisih</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($shifts as $shift)
                        <tr wire:key="shift-{{ $shift->id }}">
                            <td class="font-medium">{{ $shift->user?->name ?? '—' }}</td>
                            <td class="whitespace-nowrap text-wd-muted">
                                {{ $shift->opened_at?->timezone(config('app.timezone')) }}
                            </td>
                            <td class="whitespace-nowrap text-wd-muted">
                                {{ $shift->closed_at?->timezone(config('app.timezone')) ?? '—' }}
                            </td>
                            <td class="tabular-nums">Rp {{ number_format((int) $shift->initial_cash, 0, ',', '.') }}</td>
                            <td class="tabular-nums">Rp {{ number_format((int) $shift->total_cash_sales, 0, ',', '.') }}</td>
                            <td class="tabular-nums">Rp {{ number_format((int) $shift->total_cash_refund, 0, ',', '.') }}</td>
                            <td class="tabular-nums">Rp {{ number_format((int) $shift->expected_cash, 0, ',', '.') }}</td>
                            <td class="tabular-nums">
                                @if ($shift->actual_cash === null)
                                    —
                                @else
                                    Rp {{ number_format((int) $shift->actual_cash, 0, ',', '.') }}
                                @endif
                            </td>
                            <td class="tabular-nums">
                                @if ($shift->difference === null)
                                    —
                                @elseif ((int) $shift->difference === 0)
                                    <span class="font-medium text-wd-accent">Sesuai</span>
                                @else
                                    <span class="font-medium text-red-700">
                                        Rp {{ number_format((int) $shift->difference, 0, ',', '.') }}
                                    </span>
                                @endif
                            </td>
                            <td>
                                <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium">
                                    {{ $shift->status instanceof \App\Enums\CashierShiftStatus ? $shift->status->value : $shift->status }}
                                </span>
                            </td>
                            <td>
                                <div class="flex flex-wrap gap-1.5">
                                    <a href="{{ route('dashboard.cashier-shifts.show', $shift) }}" class="btn btn-sm" title="Lihat detail shift">
                                        Detail
                                    </a>
                                    @can('update', $shift)
                                        <button type="button" class="btn btn-sm" wire:click="openNotesModal({{ $shift->id }})" title="Ubah catatan shift">
                                            Edit catatan
                                        </button>
                                    @endcan
                                    @can('close', $shift)
                                        <button type="button" class="btn btn-sm btn-primary" wire:click="openCloseModal({{ $shift->id }})" title="Tutup dan rekonsiliasi kas">
                                            Tutup shift
                                        </button>
                                    @endcan
                                    @can('delete', $shift)
                                        <button
                                            type="button"
                                            class="btn btn-sm"
                                            wire:click="discardShift({{ $shift->id }})"
                                            wire:confirm="Batalkan shift terbuka ini? Hanya boleh jika belum ada transaksi."
                                            title="Hapus shift kosong"
                                        >
                                            Hapus
                                        </button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="py-8 text-center text-wd-muted">Belum ada data shift.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $shifts->links() }}</div>
    </div>

    @if ($showOpenModal)
        <div class="modal-backdrop">
            <div class="modal-panel max-w-md space-y-4">
                <h2 class="text-lg font-semibold">Buka shift</h2>
                <p class="text-sm text-wd-muted">Isi modal awal (uang fisik di laci) sebelum menerima pembayaran tunai.</p>
                <form wire:submit="submitOpenShift" class="space-y-4">
                    <label class="field">
                        <span class="field-label">Modal awal (Rp)</span>
                        <input type="number" min="0" step="1" wire:model="initialCash" class="field-input" required>
                        @error('initialCash') <span class="mt-1 block text-xs text-red-700">{{ $message }}</span> @enderror
                    </label>
                    <label class="field">
                        <span class="field-label">Catatan (opsional)</span>
                        <textarea wire:model="openNotes" rows="2" class="field-input"></textarea>
                        @error('openNotes') <span class="mt-1 block text-xs text-red-700">{{ $message }}</span> @enderror
                    </label>
                    <div class="flex justify-end gap-2">
                        <button type="button" class="btn" wire:click="closeCreateModal">Batal</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Buka shift</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($showCloseModal && $closePreview)
        <div class="modal-backdrop">
            <div class="modal-panel max-w-lg space-y-4">
                <h2 class="text-lg font-semibold">Tutup shift</h2>
                <dl class="grid gap-2 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-wd-muted">Modal awal</dt>
                        <dd class="font-medium tabular-nums">Rp {{ number_format((int) $closePreview['initial_cash'], 0, ',', '.') }}</dd>
                    </div>
                    <div>
                        <dt class="text-wd-muted">Total penjualan tunai</dt>
                        <dd class="font-medium tabular-nums">Rp {{ number_format((int) $closePreview['total_cash_sales'], 0, ',', '.') }}</dd>
                    </div>
                    <div>
                        <dt class="text-wd-muted">Total refund tunai</dt>
                        <dd class="font-medium tabular-nums">Rp {{ number_format((int) $closePreview['total_cash_refund'], 0, ',', '.') }}</dd>
                    </div>
                    <div>
                        <dt class="text-wd-muted">Kas seharusnya</dt>
                        <dd class="font-semibold tabular-nums text-wd-accent">Rp {{ number_format((int) $closePreview['expected_cash'], 0, ',', '.') }}</dd>
                    </div>
                </dl>

                <form wire:submit="submitCloseShift" class="space-y-4">
                    <label class="field">
                        <span class="field-label">Kas aktual (uang fisik di laci)</span>
                        <input type="number" min="0" step="1" wire:model.live="actualCash" class="field-input" required>
                        @error('actualCash') <span class="mt-1 block text-xs text-red-700">{{ $message }}</span> @enderror
                    </label>

                    @if ($liveDifference !== null)
                        <div class="rounded-lg border px-3 py-2 text-sm {{ $liveDifference === 0 ? 'border-emerald-200 bg-emerald-50 text-wd-accent' : 'border-red-200 bg-red-50 text-red-800' }}">
                            @if ($liveDifference === 0)
                                Sesuai
                            @else
                                Selisih Rp {{ number_format($liveDifference, 0, ',', '.') }}
                            @endif
                        </div>
                    @endif

                    <label class="field">
                        <span class="field-label">Catatan (opsional)</span>
                        <textarea wire:model="closeNotes" rows="2" class="field-input" placeholder="Alasan selisih bila ada"></textarea>
                        @error('closeNotes') <span class="mt-1 block text-xs text-red-700">{{ $message }}</span> @enderror
                    </label>

                    <div class="flex justify-end gap-2">
                        <button type="button" class="btn" wire:click="closeCloseModal">Batal</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Tutup shift</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($showNotesModal)
        <div class="modal-backdrop">
            <div class="modal-panel max-w-md space-y-4">
                <h2 class="text-lg font-semibold">Edit catatan</h2>
                <p class="text-sm text-wd-muted">Hanya catatan yang dapat diubah. Angka uang tetap milik server.</p>
                <form wire:submit="submitNotes" class="space-y-4">
                    <label class="field">
                        <span class="field-label">Catatan</span>
                        <textarea wire:model="editNotes" rows="3" class="field-input"></textarea>
                        @error('editNotes') <span class="mt-1 block text-xs text-red-700">{{ $message }}</span> @enderror
                    </label>
                    <div class="flex justify-end gap-2">
                        <button type="button" class="btn" wire:click="closeNotesModal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan catatan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>

<div class="page space-y-6">
    <div>
        <h1 class="page-title">Pengaturan</h1>
        <p class="page-desc">
            TTL pembayaran, heartbeat kiosk, dan aktivasi perangkat. Hanya Super Admin yang dapat mengubah di MVP; Admin melihat nilai aktif.
        </p>
    </div>

    @if ($errorMessage !== '')
        <div class="alert alert-error" role="alert">{{ $errorMessage }}</div>
    @endif
    @if ($flashMessage !== '')
        <div class="rounded-lg border border-wd-border bg-emerald-50 px-4 py-3 text-sm text-wd-accent">{{ $flashMessage }}</div>
    @endif

    <form wire:submit="save" class="space-y-6">
        <div class="card space-y-4">
            <h2 class="text-base font-semibold">Operasional</h2>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <label class="field">
                    <span class="field-label">Order payment TTL (menit)</span>
                    <input
                        type="number"
                        min="1"
                        max="1440"
                        wire:model="orderPaymentTtlMinutes"
                        class="field-input"
                        @disabled(! $canUpdate)
                    >
                    <span class="mt-1 block text-xs text-wd-muted">{{ $definitions[\App\Models\Setting::ORDER_PAYMENT_TTL_MINUTES]['description'] }}</span>
                    @error('orderPaymentTtlMinutes') <span class="mt-1 block text-xs text-red-700">{{ $message }}</span> @enderror
                </label>
                <label class="field">
                    <span class="field-label">Heartbeat stale (detik)</span>
                    <input
                        type="number"
                        min="30"
                        max="3600"
                        wire:model="kioskHeartbeatStaleSeconds"
                        class="field-input"
                        @disabled(! $canUpdate)
                    >
                    <span class="mt-1 block text-xs text-wd-muted">{{ $definitions[\App\Models\Setting::KIOSK_HEARTBEAT_STALE_SECONDS]['description'] }}</span>
                    @error('kioskHeartbeatStaleSeconds') <span class="mt-1 block text-xs text-red-700">{{ $message }}</span> @enderror
                </label>
                <label class="field">
                    <span class="field-label">Activation TTL (menit)</span>
                    <input
                        type="number"
                        min="5"
                        max="1440"
                        wire:model="kioskActivationTtlMinutes"
                        class="field-input"
                        @disabled(! $canUpdate)
                    >
                    <span class="mt-1 block text-xs text-wd-muted">{{ $definitions[\App\Models\Setting::KIOSK_ACTIVATION_TTL_MINUTES]['description'] }}</span>
                    @error('kioskActivationTtlMinutes') <span class="mt-1 block text-xs text-red-700">{{ $message }}</span> @enderror
                </label>
            </div>
        </div>

        @if ($canUpdate)
            <div class="rounded-lg border border-red-200 bg-red-50 p-4 space-y-3">
                <h2 class="text-sm font-semibold text-red-900">Zona sensitif</h2>
                <p class="text-sm text-red-800">
                    Mengubah TTL pembayaran memengaruhi kapan pesanan unpaid kedaluwarsa. Salah nilai dapat membatalkan transaksi terlalu cepat atau menahan slot terlalu lama.
                </p>
                <label class="flex items-start gap-2 text-sm text-red-900">
                    <input type="checkbox" wire:model="confirmDanger" class="mt-0.5 rounded border-red-300 text-red-700 focus:ring-red-600">
                    <span>Saya paham dampaknya dan ingin menyimpan perubahan ini.</span>
                </label>
                @error('confirmDanger') <span class="block text-xs text-red-700">{{ $message }}</span> @enderror

                <div class="flex flex-wrap gap-2 pt-1">
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Simpan pengaturan</button>
                    <button type="button" class="btn" wire:click="resetDefaults">Isi nilai default</button>
                </div>
            </div>
        @else
            <p class="text-sm text-wd-muted">Akun Anda hanya dapat melihat pengaturan. Perubahan memerlukan <code class="text-xs">settings.update</code> (Super Admin).</p>
        @endif
    </form>

    @if ($updatedBy->isNotEmpty())
        <div class="card space-y-3">
            <h2 class="text-base font-semibold">Nilai tersimpan</h2>
            <div class="table-wrap">
                <table class="table min-w-[36rem]">
                    <thead>
                        <tr>
                            <th>Key</th>
                            <th>Value</th>
                            <th>Diperbarui</th>
                            <th>Oleh</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($updatedBy as $row)
                            <tr wire:key="setting-{{ $row->id }}">
                                <td><code class="text-xs">{{ $row->key }}</code></td>
                                <td class="tabular-nums">{{ $row->value }}</td>
                                <td class="whitespace-nowrap text-sm text-wd-muted">
                                    {{ $row->updated_at?->timezone(config('app.timezone')) ?? '—' }}
                                </td>
                                <td class="text-sm">{{ $row->updatedBy?->name ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

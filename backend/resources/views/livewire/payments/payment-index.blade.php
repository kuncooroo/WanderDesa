<div class="page space-y-6">
    <div>
        <h1 class="page-title">Pembayaran</h1>
        <p class="page-desc">Daftar pembayaran dan refund. Status PAID hanya dari server — tidak ada ubah status manual.</p>
    </div>

    @if ($errorMessage !== '')
        <div class="alert alert-error" role="alert">{{ $errorMessage }}</div>
    @endif
    @if ($flashMessage !== '')
        <div class="rounded-lg border border-wd-border bg-emerald-50 px-4 py-3 text-sm text-wd-accent">{{ $flashMessage }}</div>
    @endif

    <div class="card space-y-5">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
            <label class="field xl:col-span-2">
                <span class="field-label">Nomor bayar / pesanan</span>
                <input type="search" wire:model.live.debounce.300ms="paymentNumber" placeholder="PAY… / ORD…" class="field-input" autocomplete="off">
            </label>
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
                <span class="field-label">Metode</span>
                <select wire:model.live="methodFilter" class="field-select">
                    <option value="">Semua</option>
                    @foreach ($methods as $method)
                        <option value="{{ $method->value }}">{{ $method->value }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field">
                <span class="field-label">Destinasi</span>
                <select wire:model.live="destinationId" class="field-select">
                    <option value="">Semua</option>
                    @foreach ($destinations as $destination)
                        <option value="{{ $destination->id }}">{{ $destination->name }}</option>
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
            <table class="table min-w-[56rem]">
                <thead>
                    <tr>
                        <th>Nomor</th>
                        <th>Pesanan</th>
                        <th>Metode</th>
                        <th>Status</th>
                        <th>Nominal</th>
                        <th>Provider ref</th>
                        <th>Dibuat</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($payments as $payment)
                        <tr wire:key="pay-{{ $payment->id }}">
                            <td class="font-medium">
                                <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">{{ $payment->payment_number }}</code>
                            </td>
                            <td>
                                <code class="text-xs">{{ $payment->order?->order_number ?? '—' }}</code>
                            </td>
                            <td>{{ $payment->method instanceof \App\Enums\PaymentMethod ? $payment->method->label() : $payment->method }}</td>
                            <td>
                                <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-wd-ink">
                                    {{ $payment->status instanceof \App\Enums\PaymentStatus ? $payment->status->value : $payment->status }}
                                </span>
                            </td>
                            <td class="tabular-nums">Rp {{ number_format((int) $payment->amount, 0, ',', '.') }}</td>
                            <td class="max-w-[10rem] truncate text-sm text-wd-muted" title="{{ $payment->provider_reference }}">
                                {{ $payment->provider_reference ?: '—' }}
                            </td>
                            <td class="whitespace-nowrap text-wd-muted">
                                {{ $payment->created_at?->timezone(config('app.timezone')) }}
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm" wire:click="selectPayment({{ $payment->id }})" title="Lihat detail pembayaran">
                                    Detail
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-6 text-center text-wd-muted">Tidak ada pembayaran untuk filter ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $payments->links() }}</div>
    </div>

    @if ($selected)
        <div class="modal-backdrop">
            <div class="modal-panel max-w-3xl space-y-5" wire:key="payment-detail-{{ $selected->id }}">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold">Detail {{ $selected->payment_number }}</h2>
                    <p class="mt-1 text-sm text-wd-muted">
                        {{ $selected->method instanceof \App\Enums\PaymentMethod ? $selected->method->label() : $selected->method }}
                        · {{ $selected->status instanceof \App\Enums\PaymentStatus ? $selected->status->value : $selected->status }}
                        · {{ $selected->order?->destination?->name ?? '—' }}
                    </p>
                </div>
                <button type="button" class="btn btn-sm" wire:click="clearSelection" title="Tutup detail">Tutup</button>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="stat-card">
                    <div class="stat-label">Nominal</div>
                    <div class="stat-value text-base text-wd-accent">Rp {{ number_format((int) $selected->amount, 0, ',', '.') }}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Pesanan</div>
                    <div class="stat-value text-base">{{ $selected->order?->order_number ?? '—' }}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Status pesanan</div>
                    <div class="stat-value text-base">{{ $selected->order?->status?->value ?? '—' }}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Provider</div>
                    <div class="stat-value text-base">{{ $selected->provider ?: '—' }}</div>
                </div>
            </div>

            <div class="grid gap-3 text-sm sm:grid-cols-2">
                <p><span class="text-wd-muted">Provider payment id:</span> {{ $selected->provider_payment_id ?: '—' }}</p>
                <p><span class="text-wd-muted">Provider reference:</span> {{ $selected->provider_reference ?: '—' }}</p>
                <p><span class="text-wd-muted">Paid at:</span> {{ $selected->paid_at?->timezone(config('app.timezone')) ?? '—' }}</p>
                <p><span class="text-wd-muted">Refunded at:</span> {{ $selected->refunded_at?->timezone(config('app.timezone')) ?? '—' }}</p>
                <p><span class="text-wd-muted">Dikumpulkan oleh:</span>
                    @if ($selected->collectedBy)
                        {{ $selected->collectedBy->name }}
                    @elseif ($selected->device)
                        device:{{ $selected->device->device_id }}
                    @else
                        —
                    @endif
                </p>
                @if ($selected->failure_message)
                    <p class="sm:col-span-2"><span class="text-wd-muted">Gagal:</span> {{ $selected->failure_code }} — {{ $selected->failure_message }}</p>
                @endif
                @php
                    $meta = is_array($selected->metadata_json) ? $selected->metadata_json : [];
                    $refundAmount = $meta['refund_amount'] ?? null;
                    $refundReasonMeta = $meta['refund_reason'] ?? null;
                @endphp
                @if ($refundAmount !== null)
                    <p><span class="text-wd-muted">Refund amount:</span> Rp {{ number_format((int) $refundAmount, 0, ',', '.') }}</p>
                @endif
                @if ($refundReasonMeta)
                    <p><span class="text-wd-muted">Alasan refund:</span> {{ $refundReasonMeta }}</p>
                @endif
            </div>

            <div>
                <h3 class="mb-2 text-sm font-semibold">Tiket terkait</h3>
                <div class="table-wrap">
                    <table class="table min-w-0">
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($selected->tickets as $ticket)
                                <tr wire:key="tix-{{ $ticket->id }}">
                                    <td><code class="text-xs">{{ $ticket->ticket_code }}</code></td>
                                    <td>{{ $ticket->status instanceof \App\Enums\TicketStatus ? $ticket->status->value : $ticket->status }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="text-wd-muted">Belum ada tiket.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($canRefund && $canRefundSelected)
                <div class="panel space-y-3">
                    <h3 class="text-sm font-semibold">Refund</h3>
                    <p class="text-sm text-wd-muted">
                        Hanya pembayaran <code class="text-xs">paid</code> pada pesanan paid. Jumlah refund = nominal penuh (server). Diblokir jika ada tiket sudah dipakai.
                    </p>
                    <label class="field max-w-lg">
                        <span class="field-label">Alasan (wajib)</span>
                        <input type="text" wire:model="refundReason" maxlength="500" class="field-input" placeholder="Alasan refund">
                        @error('refundReason') <span class="mt-1 block text-xs text-red-700">{{ $message }}</span> @enderror
                    </label>
                    <button type="button" class="btn btn-danger" wire:click="refundSelected" wire:loading.attr="disabled" title="Proses refund penuh">
                        Proses refund
                    </button>
                </div>
            @elseif ($canRefund && $selected->isPaid())
                <p class="text-sm text-wd-muted">Refund tidak tersedia untuk pembayaran ini (tiket sudah dipakai atau status pesanan tidak eligible).</p>
            @endif
            </div>
        </div>
    @endif
</div>

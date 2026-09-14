<div class="page space-y-6">
    <div>
        <h1 class="page-title">Pesanan</h1>
        <p class="page-desc">Cari dan lihat pesanan. Total uang hanya dari server (read-only).</p>
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
                <span class="field-label">Nomor pesanan</span>
                <input type="search" wire:model.live.debounce.300ms="orderNumber" placeholder="ORD…" class="field-input" autocomplete="off">
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
                <span class="field-label">Kanal</span>
                <select wire:model.live="channelFilter" class="field-select">
                    <option value="">Semua</option>
                    @foreach ($channels as $channel)
                        <option value="{{ $channel->value }}">{{ $channel->value }}</option>
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
            <table class="table min-w-[52rem]">
                <thead>
                    <tr>
                        <th>Nomor</th>
                        <th>Kanal</th>
                        <th>Status</th>
                        <th>Total</th>
                        <th>Destinasi</th>
                        <th>Dibuat</th>
                        <th>Aktor</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr wire:key="order-{{ $order->id }}">
                            <td class="font-medium">
                                <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">{{ $order->order_number }}</code>
                            </td>
                            <td>{{ $order->channel->value }}</td>
                            <td>
                                <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-wd-ink">
                                    {{ $order->status->value }}
                                </span>
                            </td>
                            <td class="tabular-nums">Rp {{ number_format((int) $order->grand_total, 0, ',', '.') }}</td>
                            <td>{{ $order->destination?->name ?? '—' }}</td>
                            <td class="whitespace-nowrap text-wd-muted">
                                {{ $order->created_at?->timezone(config('app.timezone')) }}
                            </td>
                            <td class="text-sm text-wd-muted">
                                @if ($order->createdBy)
                                    {{ $order->createdBy->name }}
                                @elseif ($order->device)
                                    device:{{ $order->device->device_id }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm" wire:click="selectOrder({{ $order->id }})" title="Lihat detail pesanan">
                                    Detail
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-6 text-center text-wd-muted">Tidak ada pesanan untuk filter ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $orders->links() }}</div>
    </div>

    @if ($selected)
        <div class="modal-backdrop">
            <div class="modal-panel max-w-4xl space-y-5" wire:key="order-detail-{{ $selected->id }}">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold">Detail {{ $selected->order_number }}</h2>
                    <p class="mt-1 text-sm text-wd-muted">
                        {{ $selected->channel->value }} · {{ $selected->status->value }} · {{ $selected->destination?->name ?? '—' }}
                    </p>
                </div>
                <button type="button" class="btn btn-sm" wire:click="clearSelection" title="Tutup detail">Tutup</button>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="stat-card">
                    <div class="stat-label">Subtotal</div>
                    <div class="stat-value text-base">Rp {{ number_format((int) $selected->subtotal, 0, ',', '.') }}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Pajak</div>
                    <div class="stat-value text-base">Rp {{ number_format((int) $selected->tax_total, 0, ',', '.') }}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Biaya layanan</div>
                    <div class="stat-value text-base">Rp {{ number_format((int) $selected->service_fee_total, 0, ',', '.') }}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Grand total</div>
                    <div class="stat-value text-base text-wd-accent">Rp {{ number_format((int) $selected->grand_total, 0, ',', '.') }}</div>
                </div>
            </div>

            @if ($selected->customer_note)
                <p class="text-sm"><span class="text-wd-muted">Catatan:</span> {{ $selected->customer_note }}</p>
            @endif

            <div>
                <h3 class="mb-2 text-sm font-semibold">Item</h3>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Jenis</th>
                                <th>Qty</th>
                                <th>Harga</th>
                                <th>Total baris</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($selected->items as $item)
                                <tr wire:key="item-{{ $item->id }}">
                                    <td>
                                        <div class="font-medium">{{ $item->ticket_type_name }}</div>
                                        <div class="text-xs text-wd-muted">{{ $item->ticket_type_code }}</div>
                                    </td>
                                    <td>{{ $item->quantity }}</td>
                                    <td class="tabular-nums">Rp {{ number_format((int) $item->unit_price, 0, ',', '.') }}</td>
                                    <td class="tabular-nums">Rp {{ number_format((int) $item->line_grand_total, 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-wd-muted">Tidak ada item.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <div>
                    <h3 class="mb-2 text-sm font-semibold">Pembayaran</h3>
                    <div class="table-wrap">
                        <table class="table min-w-0">
                            <thead>
                                <tr>
                                    <th>Nomor</th>
                                    <th>Status</th>
                                    <th>Nominal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($selected->payments as $payment)
                                    <tr wire:key="pay-{{ $payment->id }}">
                                        <td><code class="text-xs">{{ $payment->payment_number }}</code></td>
                                        <td>{{ $payment->status->value }}</td>
                                        <td class="tabular-nums">Rp {{ number_format((int) $payment->amount, 0, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-wd-muted">Belum ada pembayaran.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div>
                    <h3 class="mb-2 text-sm font-semibold">Tiket</h3>
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
                                        <td>{{ $ticket->status->value }}</td>
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
            </div>

            @if ($canCancel && $selected->status === \App\Enums\OrderStatus::PendingPayment)
                <div class="panel space-y-3">
                    <h3 class="text-sm font-semibold">Batalkan pesanan unpaid</h3>
                    <p class="text-sm text-wd-muted">Hanya pesanan <code class="text-xs">pending_payment</code> tanpa pembayaran PAID yang bisa dibatalkan.</p>
                    <label class="field max-w-md">
                        <span class="field-label">Alasan (opsional)</span>
                        <input type="text" wire:model="cancelReason" maxlength="255" class="field-input" placeholder="Alasan pembatalan">
                    </label>
                    <button type="button" class="btn btn-danger" wire:click="cancelSelected" wire:loading.attr="disabled" title="Batalkan pesanan unpaid">
                        Batalkan pesanan
                    </button>
                </div>
            @endif
            </div>
        </div>
    @endif
</div>

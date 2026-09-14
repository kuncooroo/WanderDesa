<div class="page space-y-6">
    <div>
        <h1 class="page-title">Penjualan dibantu</h1>
        <p class="page-desc">Channel <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">{{ $channelAssisted }}</code> · total dari server · tanpa edit harga.</p>
    </div>

    @if ($errorMessage !== '')
        <div class="alert alert-error" role="alert">{{ $errorMessage }}</div>
    @endif

    @if (! $hasOpenShift)
        <div class="card space-y-4">
            <h2 class="text-base font-semibold">Shift belum dibuka</h2>
            <p class="text-sm text-wd-muted">Kasir wajib membuka shift dan mengisi modal awal sebelum menerima penjualan tunai.</p>
            <a href="{{ route('dashboard.cashier-shifts') }}" class="btn btn-primary inline-flex">Buka shift kasir</a>
        </div>
    @else
    <div class="card space-y-5">
        @if ($step === 'catalog' || $step === 'summary')
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="field">
                    <span class="field-label">Destinasi</span>
                    <select wire:model.live="destinationId" class="field-select">
                        <option value="">— pilih —</option>
                        @foreach ($destinations as $destination)
                            <option value="{{ $destination->id }}">{{ $destination->name }} ({{ $destination->code }})</option>
                        @endforeach
                    </select>
                </label>

                <label class="field">
                    <span class="field-label">Tanggal kunjungan</span>
                    <input type="date" wire:model.live="visitDate" class="field-input">
                </label>
            </div>

            @if ($ticketTypes->isNotEmpty())
                <div>
                    <h2 class="mb-3 text-base font-semibold">Tiket</h2>
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Jenis</th>
                                    <th>Harga server</th>
                                    <th>Qty</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($ticketTypes as $type)
                                    @php $qty = (int) ($quantities[(string) $type->id] ?? 0); @endphp
                                    <tr wire:key="tt-{{ $type->id }}">
                                        <td>
                                            <div class="font-medium">{{ $type->name }}</div>
                                            <div class="text-xs text-wd-muted">{{ $type->code }}</div>
                                        </td>
                                        <td>Rp {{ number_format((int) $type->unit_price, 0, ',', '.') }}</td>
                                        <td>
                                            <input
                                                type="number"
                                                min="0"
                                                max="{{ (int) $type->max_per_order }}"
                                                value="{{ $qty }}"
                                                wire:change="setQuantity({{ $type->id }}, $event.target.value)"
                                                class="field-input w-20"
                                            >
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <label class="field">
                <span class="field-label">Catatan (opsional)</span>
                <input type="text" wire:model="customerNote" maxlength="255" class="field-input" placeholder="Catatan pengunjung">
            </label>

            @if ($quote)
                <div class="panel space-y-1">
                    <div class="text-sm text-wd-muted">Ringkasan server</div>
                    <div class="text-sm">Subtotal: Rp {{ number_format((int) $quote['subtotal'], 0, ',', '.') }}</div>
                    <div class="text-sm">Pajak: Rp {{ number_format((int) $quote['tax_total'], 0, ',', '.') }}</div>
                    <div class="text-sm">Biaya layanan: Rp {{ number_format((int) $quote['service_fee_total'], 0, ',', '.') }}</div>
                    <div class="pt-1 text-xl font-semibold text-wd-accent">
                        Total: Rp {{ number_format((int) $quote['grand_total'], 0, ',', '.') }}
                    </div>
                </div>
            @endif

            @if ($step === 'summary')
                <div class="space-y-2">
                    <div class="text-sm font-medium text-wd-muted">Metode pembayaran</div>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($paymentMethods as $method)
                            <button
                                type="button"
                                class="btn-seg {{ $paymentMethod === $method->value ? 'btn-seg-active' : '' }}"
                                wire:click="$set('paymentMethod', '{{ $method->value }}')"
                            >{{ $method->label() }}</button>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="flex flex-wrap gap-3">
                @if ($step === 'catalog')
                    <button type="button" class="btn btn-primary" wire:click="goToSummary">Lanjut ringkasan</button>
                @else
                    <button type="button" class="btn" wire:click="backToCatalog">Kembali</button>
                    <button
                        type="button"
                        class="btn btn-primary"
                        wire:click="createAndCollect('{{ $paymentMethod }}')"
                        wire:loading.attr="disabled"
                    >
                        Buat pesanan &amp; bayar ({{ \App\Enums\PaymentMethod::tryFrom($paymentMethod)?->label() ?? $paymentMethod }})
                    </button>
                @endif
            </div>
        @endif

        @if ($step === 'pay')
            <div class="space-y-3">
                <p class="text-sm">
                    Pesanan <strong>{{ $orderNumber }}</strong>
                    · metode <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">{{ $paymentMethod }}</code>
                    · status <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">{{ $paymentStatus }}</code>
                </p>
                <p class="text-2xl font-semibold text-wd-accent">Rp {{ number_format($paidAmount, 0, ',', '.') }}</p>

                @if ($paymentMethod === 'cash')
                    <button type="button" class="btn btn-primary" wire:click="$set('showCashModal', true)">
                        Buka konfirmasi tunai
                    </button>
                @else
                    <p class="text-sm text-wd-muted">Menunggu konfirmasi provider. Status final dari server.</p>
                    @if (! empty($nextAction['qr_content']))
                        <div class="panel">
                            <div class="text-xs text-wd-muted">QR / instruksi provider</div>
                            <pre class="code-box mt-2">{{ $nextAction['qr_content'] }}</pre>
                        </div>
                    @endif
                    <button type="button" class="btn btn-primary" wire:click="refreshPaymentStatus">
                        Periksa status pembayaran
                    </button>
                @endif
            </div>
        @endif

        @if ($step === 'done')
            <div class="space-y-4">
                <div>
                    <h2 class="text-lg font-semibold text-wd-accent">Pembayaran berhasil</h2>
                    <p class="mt-1 text-sm">Order <strong>{{ $orderNumber }}</strong> · status pembayaran <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">{{ $paymentStatus }}</code></p>
                    <p class="text-sm">Total dibayar (server): <strong>Rp {{ number_format($paidAmount, 0, ',', '.') }}</strong></p>
                </div>

                <div>
                    <h3 class="mb-2 text-sm font-semibold">Tiket diterbitkan</h3>
                    <ul class="space-y-1 text-sm">
                        @foreach ($tickets as $ticket)
                            <li wire:key="ticket-{{ $ticket['id'] }}" class="rounded-lg border border-wd-border px-3 py-2">
                                <code class="text-xs">{{ $ticket['ticket_code'] }}</code>
                                · {{ $ticket['status'] }}
                                · channel {{ $ticket['channel'] }}
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button type="button" class="btn btn-primary" wire:click="loadPrintPayloads">Ambil payload cetak</button>
                    <button type="button" class="btn" wire:click="recordPrintSuccess">Catat cetak / cetak ulang</button>
                    <button type="button" class="btn" wire:click="recordPrintFailure">Catat cetak gagal</button>
                    <button type="button" class="btn" wire:click="startNewSale">Penjualan baru</button>
                </div>

                @if ($printPayloads !== [])
                    <div class="space-y-3">
                        <h3 class="text-sm font-semibold">Print payload (server)</h3>
                        @foreach ($printPayloads as $payload)
                            <div class="panel" wire:key="print-{{ $payload['ticket_code'] }}">
                                <div class="font-medium">{{ $payload['ticket_code'] }}</div>
                                <div class="mt-1 text-xs text-wd-muted">QR (tampilkan jika cetak gagal):</div>
                                <pre class="code-box mt-2">{{ $payload['qr_payload'] }}</pre>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </div>

    @if ($showCashModal)
        <div class="modal-backdrop">
            <div class="modal-panel max-w-md space-y-4">
                <h2 class="text-lg font-semibold">Konfirmasi tunai</h2>
                <p class="text-sm">Terima tunai <strong>Rp {{ number_format($paidAmount, 0, ',', '.') }}</strong>?</p>
                <p class="text-sm text-wd-muted">Konfirmasi ini menandai pembayaran PAID di server dan menerbitkan tiket.</p>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn" wire:click="cancelCashModal" title="Batalkan konfirmasi">Batal</button>
                    <button type="button" class="btn btn-primary" wire:click="confirmCashReceived" wire:loading.attr="disabled" title="Konfirmasi terima tunai">
                        Ya, terima tunai
                    </button>
                </div>
            </div>
        </div>
    @endif
    @endif
</div>

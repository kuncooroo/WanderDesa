<div class="page space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="page-title">Detail shift #{{ $shift->id }}</h1>
            <p class="page-desc">
                {{ $shift->user?->name ?? '—' }}
                · status
                <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">{{ $shift->status->value }}</code>
            </p>
        </div>
        <a href="{{ route('dashboard.cashier-shifts') }}" class="btn">Kembali ke daftar</a>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="panel">
            <div class="text-xs text-wd-muted">Modal awal</div>
            <div class="mt-1 text-lg font-semibold tabular-nums">Rp {{ number_format((int) $shift->initial_cash, 0, ',', '.') }}</div>
        </div>
        <div class="panel">
            <div class="text-xs text-wd-muted">Penjualan tunai</div>
            <div class="mt-1 text-lg font-semibold tabular-nums">Rp {{ number_format((int) $totals['total_cash_sales'], 0, ',', '.') }}</div>
        </div>
        <div class="panel">
            <div class="text-xs text-wd-muted">Refund tunai</div>
            <div class="mt-1 text-lg font-semibold tabular-nums">Rp {{ number_format((int) $totals['total_cash_refund'], 0, ',', '.') }}</div>
        </div>
        <div class="panel">
            <div class="text-xs text-wd-muted">Kas seharusnya</div>
            <div class="mt-1 text-lg font-semibold tabular-nums text-wd-accent">Rp {{ number_format((int) $totals['expected_cash'], 0, ',', '.') }}</div>
        </div>
        <div class="panel">
            <div class="text-xs text-wd-muted">QRIS (bukan kas laci)</div>
            <div class="mt-1 text-lg font-semibold tabular-nums">Rp {{ number_format((int) ($totals['total_qris_sales'] ?? 0), 0, ',', '.') }}</div>
        </div>
    </div>

    @if ($shift->isClosed())
        <div class="card grid gap-3 sm:grid-cols-3">
            <div>
                <div class="text-xs text-wd-muted">Kas aktual</div>
                <div class="mt-1 font-semibold tabular-nums">Rp {{ number_format((int) $shift->actual_cash, 0, ',', '.') }}</div>
            </div>
            <div>
                <div class="text-xs text-wd-muted">Selisih</div>
                <div class="mt-1 font-semibold tabular-nums {{ (int) $shift->difference === 0 ? 'text-wd-accent' : 'text-red-700' }}">
                    @if ((int) $shift->difference === 0)
                        Sesuai
                    @else
                        Rp {{ number_format((int) $shift->difference, 0, ',', '.') }}
                    @endif
                </div>
            </div>
            <div>
                <div class="text-xs text-wd-muted">Ditutup</div>
                <div class="mt-1 text-sm">{{ $shift->closed_at?->timezone(config('app.timezone')) }}</div>
            </div>
        </div>
    @endif

    @if ($shift->notes)
        <div class="panel text-sm">
            <div class="text-xs text-wd-muted">Catatan</div>
            <p class="mt-1 whitespace-pre-wrap">{{ $shift->notes }}</p>
        </div>
    @endif

    <div class="card space-y-3">
        <h2 class="text-base font-semibold">Penjualan tunai terhubung</h2>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Pembayaran</th>
                        <th>Pesanan</th>
                        <th>Nominal</th>
                        <th>Dibayar</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sales as $payment)
                        <tr wire:key="sale-{{ $payment->id }}">
                            <td><code class="text-xs">{{ $payment->payment_number }}</code></td>
                            <td><code class="text-xs">{{ $payment->order?->order_number ?? '—' }}</code></td>
                            <td class="tabular-nums">Rp {{ number_format((int) $payment->amount, 0, ',', '.') }}</td>
                            <td class="text-wd-muted">{{ $payment->paid_at?->timezone(config('app.timezone')) ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-6 text-center text-wd-muted">Belum ada penjualan tunai.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card space-y-3">
        <h2 class="text-base font-semibold">Penjualan QRIS terhubung</h2>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Pembayaran</th>
                        <th>Pesanan</th>
                        <th>Nominal</th>
                        <th>Dibayar</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($qrisSales as $payment)
                        <tr wire:key="qris-{{ $payment->id }}">
                            <td><code class="text-xs">{{ $payment->payment_number }}</code></td>
                            <td><code class="text-xs">{{ $payment->order?->order_number ?? '—' }}</code></td>
                            <td class="tabular-nums">Rp {{ number_format((int) $payment->amount, 0, ',', '.') }}</td>
                            <td class="text-wd-muted">{{ $payment->paid_at?->timezone(config('app.timezone')) ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-6 text-center text-wd-muted">Belum ada penjualan QRIS.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card space-y-3">
        <h2 class="text-base font-semibold">Refund tunai terhubung</h2>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Pembayaran</th>
                        <th>Pesanan</th>
                        <th>Nominal</th>
                        <th>Direfund</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($refunds as $payment)
                        <tr wire:key="refund-{{ $payment->id }}">
                            <td><code class="text-xs">{{ $payment->payment_number }}</code></td>
                            <td><code class="text-xs">{{ $payment->order?->order_number ?? '—' }}</code></td>
                            <td class="tabular-nums">Rp {{ number_format((int) $payment->amount, 0, ',', '.') }}</td>
                            <td class="text-wd-muted">{{ $payment->refunded_at?->timezone(config('app.timezone')) ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-6 text-center text-wd-muted">Belum ada refund tunai.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="page space-y-6">
    <div>
        <h1 class="page-title">Laporan</h1>
        <p class="page-desc">Angka dari database Laravel. Zona waktu tampilan: {{ $timezone }} (penyimpanan UTC).</p>
    </div>

    <div class="card space-y-5">
        <div class="flex flex-wrap gap-2">
            <button type="button" class="btn-seg {{ $report === 'sales' ? 'btn-seg-active' : '' }}" wire:click="showReport('sales')">Penjualan harian</button>
            <button type="button" class="btn-seg {{ $report === 'by_product' ? 'btn-seg-active' : '' }}" wire:click="showReport('by_product')">Penjualan per produk</button>
            <button type="button" class="btn-seg {{ $report === 'by_cashier' ? 'btn-seg-active' : '' }}" wire:click="showReport('by_cashier')">Penjualan per kasir</button>
            <button type="button" class="btn-seg {{ $report === 'payments' ? 'btn-seg-active' : '' }}" wire:click="showReport('payments')">Pembayaran</button>
            <button type="button" class="btn-seg {{ $report === 'tickets' ? 'btn-seg-active' : '' }}" wire:click="showReport('tickets')">Tiket terbit vs dipakai</button>
            <button type="button" class="btn-seg {{ $report === 'refunds' ? 'btn-seg-active' : '' }}" wire:click="showReport('refunds')">Refund / batal</button>
            <button type="button" class="btn-seg {{ $report === 'kiosks' ? 'btn-seg-active' : '' }}" wire:click="showReport('kiosks')">Kesehatan kiosk</button>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5">
            <label class="field">
                <span class="field-label">Dari</span>
                <input type="date" wire:model.live="fromDate" class="field-input">
            </label>
            <label class="field">
                <span class="field-label">Sampai</span>
                <input type="date" wire:model.live="toDate" class="field-input">
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
            @if ($showCashierFilter)
                <label class="field">
                    <span class="field-label">Kasir</span>
                    <select wire:model.live="cashierUserId" class="field-select">
                        <option value="">Semua</option>
                        @foreach ($cashiers as $cashier)
                            <option value="{{ $cashier['id'] }}">{{ $cashier['name'] }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            @if ($report === 'payments')
                <label class="field">
                    <span class="field-label">Status pembayaran</span>
                    <select wire:model.live="paymentStatus" class="field-select">
                        <option value="">Semua</option>
                        <option value="pending">pending</option>
                        <option value="processing">processing</option>
                        <option value="paid">paid</option>
                        <option value="failed">failed</option>
                        <option value="expired">expired</option>
                        <option value="cancelled">cancelled</option>
                        <option value="refunded">refunded</option>
                    </select>
                </label>
            @endif
        </div>

        @error('toDate')
            <p class="alert alert-error">{{ $message }}</p>
        @enderror

        <p class="text-sm text-wd-muted" wire:loading wire:target="showReport, fromDate, toDate, destinationId, cashierUserId, paymentStatus">Memuat laporan…</p>

        @if ($salesSummary)
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="stat-card">
                    <div class="stat-label">Gross Sales</div>
                    <div class="stat-value text-base">Rp {{ number_format((int) $salesSummary['totals']['gross_sales'], 0, ',', '.') }}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Diskon</div>
                    <div class="stat-value text-base">Rp {{ number_format((int) $salesSummary['totals']['discount_total'], 0, ',', '.') }}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Refund</div>
                    <div class="stat-value text-base">Rp {{ number_format((int) $salesSummary['totals']['refund_total'], 0, ',', '.') }}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Net Sales</div>
                    <div class="stat-value text-base text-wd-accent">Rp {{ number_format((int) $salesSummary['totals']['net_sales'], 0, ',', '.') }}</div>
                </div>
            </div>
            <p class="text-xs text-wd-muted">Net Sales = Gross Sales − Diskon − Refund (semua dihitung server, IDR integer).</p>
        @endif

        @if ($report === 'sales')
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-base font-semibold">Penjualan per kanal</h2>
                @if ($canExport)
                    <button type="button" class="btn btn-primary" wire:click="exportSales" title="Unduh CSV penjualan + produk">
                        Unduh CSV
                    </button>
                @endif
            </div>
            <p class="text-sm text-wd-muted">Gross = pesanan dengan <code class="rounded bg-gray-100 px-1 text-xs">paid_at</code> pada rentang (termasuk yang kemudian direfund).</p>
            @if ((int) $payload['totals']['order_count'] === 0)
                <p class="text-sm text-wd-muted">Tidak ada data pada filter ini</p>
            @endif
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Kanal</th>
                            <th>Jumlah pesanan</th>
                            <th>Gross (IDR)</th>
                            <th>Diskon (IDR)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payload['channels'] as $row)
                            <tr>
                                <td>{{ $row['channel'] }}</td>
                                <td>{{ $row['order_count'] }}</td>
                                <td>Rp {{ number_format((int) $row['gross_sales'], 0, ',', '.') }}</td>
                                <td>Rp {{ number_format((int) $row['discount_total'], 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                        <tr>
                            <td><strong>Total</strong></td>
                            <td><strong>{{ $payload['totals']['order_count'] }}</strong></td>
                            <td><strong>Rp {{ number_format((int) $payload['totals']['gross_sales'], 0, ',', '.') }}</strong></td>
                            <td><strong>Rp {{ number_format((int) $payload['totals']['discount_total'], 0, ',', '.') }}</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @endif

        @if ($report === 'by_product')
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-base font-semibold">Penjualan per produk</h2>
                @if ($canExport)
                    <button type="button" class="btn btn-primary" wire:click="exportSales" title="Unduh CSV penjualan + produk">
                        Unduh CSV
                    </button>
                @endif
            </div>
            <p class="text-sm text-wd-muted">Qty dan omzet dari item pesanan yang <code class="rounded bg-gray-100 px-1 text-xs">paid_at</code>-nya masuk filter.</p>

            <div class="space-y-2">
                <h3 class="text-sm font-semibold">5 Tiket/Wahana Terlaris</h3>
                <p class="text-xs text-wd-muted">Berdasarkan unit terjual pada filter aktif.</p>
                <div wire:ignore>
                    <div id="report-top-products-chart" class="min-h-[18rem] w-full"></div>
                </div>
            </div>

            @if ($payload['rows'] === [])
                <p class="text-sm text-wd-muted">Tidak ada data pada filter ini</p>
            @endif
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nama tiket / wahana</th>
                            <th>Kode</th>
                            <th>Jumlah terjual (Qty)</th>
                            <th>Total omzet (IDR)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payload['rows'] as $row)
                            <tr>
                                <td class="font-medium">{{ $row['product_name'] }}</td>
                                <td><code class="text-xs">{{ $row['ticket_type_code'] }}</code></td>
                                <td>{{ $row['qty_sold'] }}</td>
                                <td class="tabular-nums">Rp {{ number_format((int) $row['omzet'], 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                        <tr>
                            <td colspan="2"><strong>Total</strong></td>
                            <td><strong>{{ $payload['totals']['qty_sold'] }}</strong></td>
                            <td><strong>Rp {{ number_format((int) $payload['totals']['omzet'], 0, ',', '.') }}</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @endif

        @if ($report === 'by_cashier')
            <h2 class="text-base font-semibold">Penjualan per kasir</h2>
            <p class="text-sm text-wd-muted">Omzet dari pesanan paid yang diatribusikan ke kasir. Selisih dari shift yang ditutup pada rentang filter.</p>
            @if ($payload['rows'] === [])
                <p class="text-sm text-wd-muted">Tidak ada data pada filter ini</p>
            @endif
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nama kasir</th>
                            <th>Jumlah transaksi</th>
                            <th>Total omzet (IDR)</th>
                            <th>Ringkasan selisih shift</th>
                            <th>Shift ditutup</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payload['rows'] as $row)
                            <tr>
                                <td class="font-medium">{{ $row['cashier_name'] }}</td>
                                <td>{{ $row['transaction_count'] }}</td>
                                <td class="tabular-nums">Rp {{ number_format((int) $row['omzet'], 0, ',', '.') }}</td>
                                <td class="tabular-nums {{ (int) $row['shift_difference_total'] === 0 ? 'text-wd-accent' : 'text-red-700' }}">
                                    @if ((int) $row['closed_shift_count'] === 0)
                                        —
                                    @elseif ((int) $row['shift_difference_total'] === 0)
                                        Sesuai
                                    @else
                                        Rp {{ number_format((int) $row['shift_difference_total'], 0, ',', '.') }}
                                    @endif
                                </td>
                                <td>{{ $row['closed_shift_count'] }}</td>
                            </tr>
                        @endforeach
                        <tr>
                            <td><strong>Total</strong></td>
                            <td><strong>{{ $payload['totals']['transaction_count'] }}</strong></td>
                            <td><strong>Rp {{ number_format((int) $payload['totals']['omzet'], 0, ',', '.') }}</strong></td>
                            <td><strong>Rp {{ number_format((int) $payload['totals']['shift_difference_total'], 0, ',', '.') }}</strong></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @endif

        @if ($report === 'payments')
            <h2 class="text-base font-semibold">Pembayaran per status</h2>
            <p class="text-sm text-wd-muted">Dikelompokkan dari status kolom <code class="rounded bg-gray-100 px-1 text-xs">payments</code>, jendela <code class="rounded bg-gray-100 px-1 text-xs">created_at</code>.</p>

            <div class="space-y-2">
                <h3 class="text-sm font-semibold">Perbandingan metode pembayaran</h3>
                <p class="text-xs text-wd-muted">
                    Metode resmi: <code class="rounded bg-gray-100 px-1">cash</code>,
                    <code class="rounded bg-gray-100 px-1">qris</code>,
                    <code class="rounded bg-gray-100 px-1">debit</code>,
                    <code class="rounded bg-gray-100 px-1">e_wallet</code>.
                </p>
                <div wire:ignore>
                    <div id="report-payment-methods-chart" class="mx-auto min-h-[18rem] w-full max-w-lg"></div>
                </div>
            </div>

            @if ((int) $payload['totals']['payment_count'] === 0)
                <p class="text-sm text-wd-muted">Tidak ada data pada filter ini</p>
            @endif
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Jumlah</th>
                            <th>Nominal (IDR)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payload['rows'] as $row)
                            <tr>
                                <td>{{ $row['status'] }}</td>
                                <td>{{ $row['payment_count'] }}</td>
                                <td>Rp {{ number_format((int) $row['amount_total'], 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                        <tr>
                            <td><strong>Total</strong></td>
                            <td><strong>{{ $payload['totals']['payment_count'] }}</strong></td>
                            <td><strong>Rp {{ number_format((int) $payload['totals']['amount_total'], 0, ',', '.') }}</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @endif

        @if ($report === 'tickets')
            <h2 class="text-base font-semibold">Tiket terbit vs dipakai</h2>
            <p class="text-sm text-wd-muted">Dihitung dari timestamp peristiwa, bukan dari cache klien.</p>
            @if ((int) $payload['issued_count'] === 0 && (int) $payload['used_count'] === 0 && (int) $payload['cancelled_count'] === 0 && (int) $payload['refunded_count'] === 0)
                <p class="text-sm text-wd-muted">Tidak ada data pada filter ini</p>
            @endif
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="stat-card"><div class="stat-label">Diterbitkan</div><div class="stat-value">{{ $payload['issued_count'] }}</div></div>
                <div class="stat-card"><div class="stat-label">Dipakai</div><div class="stat-value">{{ $payload['used_count'] }}</div></div>
                <div class="stat-card"><div class="stat-label">Dibatalkan</div><div class="stat-value">{{ $payload['cancelled_count'] }}</div></div>
                <div class="stat-card"><div class="stat-label">Direfund</div><div class="stat-value">{{ $payload['refunded_count'] }}</div></div>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Status saat ini (kohort diterbitkan)</th>
                            <th>Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payload['by_status'] as $row)
                            <tr>
                                <td>{{ $row['status'] }}</td>
                                <td>{{ $row['ticket_count'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($report === 'refunds')
            <h2 class="text-base font-semibold">Refund dan pembatalan</h2>
            <p class="text-sm text-wd-muted">Tanpa data pengunjung. Nominal dari kolom server.</p>
            @if ($payload['rows'] === [])
                <p class="text-sm text-wd-muted">Tidak ada data pada filter ini</p>
            @else
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Jenis</th>
                                <th>Pesanan</th>
                                <th>Pembayaran</th>
                                <th>Kanal</th>
                                <th>Nominal</th>
                                <th>Waktu (UTC)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($payload['rows'] as $row)
                                <tr>
                                    <td>{{ $row['kind'] }}</td>
                                    <td>{{ $row['order_number'] ?? '—' }}</td>
                                    <td>{{ $row['payment_number'] ?? '—' }}</td>
                                    <td>{{ $row['channel'] ?? '—' }}</td>
                                    <td>Rp {{ number_format((int) $row['amount'], 0, ',', '.') }}</td>
                                    <td class="whitespace-nowrap">{{ $row['occurred_at'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif

        @if ($report === 'kiosks')
            <h2 class="text-base font-semibold">Kesehatan kiosk</h2>
            <p class="text-sm text-wd-muted">Snapshot armada saat ini (bukan histori). Offline jika heartbeat lebih lama dari {{ $payload['stale_after_seconds'] }} detik.</p>
            @if ((int) $payload['total'] === 0)
                <p class="text-sm text-wd-muted">Tidak ada data pada filter ini</p>
            @endif
            <div class="grid gap-3 sm:grid-cols-3">
                <div class="stat-card"><div class="stat-label">Online</div><div class="stat-value text-wd-accent">{{ $payload['online_count'] }}</div></div>
                <div class="stat-card"><div class="stat-label">Offline</div><div class="stat-value">{{ $payload['offline_count'] }}</div></div>
                <div class="stat-card"><div class="stat-label">Total</div><div class="stat-value">{{ $payload['total'] }}</div></div>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Status perangkat</th>
                            <th>Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payload['by_status'] as $row)
                            <tr>
                                <td>{{ $row['status'] }}</td>
                                <td>{{ $row['device_count'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

@script
<script>
    const paintReportsChart = (payload, report, isUpdate = false) => {
        if (! window.WanderDesaCharts || ! payload) {
            return;
        }

        if (report === 'payments') {
            const el = document.getElementById('report-payment-methods-chart');
            if (! el) {
                return;
            }

            window.WanderDesaCharts.donutMethods(
                el,
                payload.labels ?? [],
                payload.series ?? [],
                { update: isUpdate && Boolean(el._wdChart) },
            );

            return;
        }

        if (report === 'by_product') {
            const el = document.getElementById('report-top-products-chart');
            if (! el) {
                return;
            }

            window.WanderDesaCharts.barTopProducts(
                el,
                payload.categories ?? [],
                payload.series ?? [],
                { update: isUpdate && Boolean(el._wdChart) },
            );
        }
    };

    const refreshChart = async (isUpdate = false) => {
        const report = $wire.report;
        if (report !== 'payments' && report !== 'by_product') {
            return;
        }

        const payload = await $wire.chartPayload();
        // Wait for Livewire morph so chart hosts exist after tab switches.
        setTimeout(() => paintReportsChart(payload, report, isUpdate), 40);
    };

    refreshChart(false);

    $wire.$watch('report', () => refreshChart(false));
    ['fromDate', 'toDate', 'destinationId', 'cashierUserId', 'paymentStatus'].forEach((prop) => {
        $wire.$watch(prop, () => refreshChart(true));
    });
</script>
@endscript

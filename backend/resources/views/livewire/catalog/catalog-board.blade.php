<div class="page space-y-6">
    <div>
        <h1 class="page-title">Destinasi & tiket</h1>
        <p class="page-desc">Kelola destinasi dan jenis tiket. Harga IDR integer; penyimpanan hanya lewat server.</p>
    </div>

    @if ($errorMessage !== '')
        <div class="alert alert-error" role="alert">{{ $errorMessage }}</div>
    @endif
    @if ($flashMessage !== '')
        <div class="rounded-lg border border-wd-border bg-emerald-50 px-4 py-3 text-sm text-wd-accent">{{ $flashMessage }}</div>
    @endif

    <div class="flex flex-wrap gap-2">
        <button type="button" class="btn-seg {{ $tab === 'destinations' ? 'btn-seg-active' : '' }}" wire:click="showTab('destinations')">Destinasi</button>
        <button type="button" class="btn-seg {{ $tab === 'tickets' ? 'btn-seg-active' : '' }}" wire:click="showTab('tickets')">Jenis tiket</button>
    </div>

    <div class="card space-y-4">
        <label class="field max-w-md">
            <span class="field-label">Cari</span>
            <input type="search" wire:model.live.debounce.300ms="search" class="field-input" placeholder="Nama atau kode…" autocomplete="off">
        </label>

        @if ($tab === 'destinations')
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-base font-semibold">Daftar destinasi</h2>
                @if ($canManageDestinations)
                    <button type="button" class="btn btn-primary" wire:click="startCreateDestination" title="Tambah destinasi baru">
                        Tambah destinasi
                    </button>
                @endif
            </div>

            <div class="table-wrap">
                <table class="table min-w-[40rem]">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Nama</th>
                            <th>Timezone</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($destinations as $destination)
                            <tr wire:key="dest-{{ $destination->id }}">
                                <td><code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">{{ $destination->code }}</code></td>
                                <td class="font-medium">{{ $destination->name }}</td>
                                <td class="text-wd-muted">{{ $destination->timezone }}</td>
                                <td>
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $destination->is_active ? 'bg-emerald-50 text-wd-accent' : 'bg-gray-100 text-wd-muted' }}">
                                        {{ $destination->is_active ? 'aktif' : 'nonaktif' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="flex flex-wrap gap-1.5">
                                        <button type="button" class="btn btn-sm" wire:click="selectDestination({{ $destination->id }})" title="Kelola jenis tiket destinasi ini">
                                            Kelola tiket
                                        </button>
                                        @if ($canManageDestinations)
                                            <button type="button" class="btn btn-sm" wire:click="editDestination({{ $destination->id }})" title="Ubah destinasi">
                                                Edit
                                            </button>
                                            @if ($destination->is_active)
                                                <button type="button" class="btn btn-sm btn-danger" wire:click="deactivateDestination({{ $destination->id }})" title="Nonaktifkan destinasi">
                                                    Nonaktifkan
                                                </button>
                                            @else
                                                <button type="button" class="btn btn-sm" wire:click="activateDestination({{ $destination->id }})" title="Aktifkan destinasi">
                                                    Aktifkan
                                                </button>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-6 text-center text-wd-muted">Belum ada destinasi.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>{{ $destinations->links() }}</div>
        @endif

        @if ($tab === 'tickets')
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="field">
                    <span class="field-label">Destinasi</span>
                    <select wire:model.live="selectedDestinationId" class="field-select">
                        <option value="">— pilih —</option>
                        @foreach ($allDestinations as $destination)
                            <option value="{{ $destination->id }}">
                                {{ $destination->name }} ({{ $destination->code }}){{ $destination->is_active ? '' : ' · nonaktif' }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <div class="flex items-end">
                    @if ($canManageTicketTypes && $selectedDestinationId !== '')
                        <button type="button" class="btn btn-primary" wire:click="startCreateTicketType" title="Tambah jenis tiket">
                            Tambah jenis tiket
                        </button>
                    @endif
                </div>
            </div>

            @if ($selectedDestination)
                <p class="text-sm text-wd-muted">
                    Jenis tiket untuk <strong>{{ $selectedDestination->name }}</strong>
                </p>

                <div class="table-wrap">
                    <table class="table min-w-[48rem]">
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Nama</th>
                                <th>Harga</th>
                                <th>Validitas</th>
                                <th>Max/order</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($ticketTypes as $type)
                                <tr wire:key="tt-{{ $type->id }}">
                                    <td><code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">{{ $type->code }}</code></td>
                                    <td class="font-medium">{{ $type->name }}</td>
                                    <td class="tabular-nums">Rp {{ number_format((int) $type->unit_price, 0, ',', '.') }}</td>
                                    <td class="text-sm text-wd-muted">{{ $type->validity_type instanceof \App\Enums\ValidityType ? $type->validity_type->value : $type->validity_type }}</td>
                                    <td>{{ $type->max_per_order }}</td>
                                    <td>
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $type->is_active ? 'bg-emerald-50 text-wd-accent' : 'bg-gray-100 text-wd-muted' }}">
                                            {{ $type->is_active ? 'aktif' : 'nonaktif' }}
                                        </span>
                                    </td>
                                    <td>
                                        @if ($canManageTicketTypes)
                                            <div class="flex flex-wrap gap-1.5">
                                                <button type="button" class="btn btn-sm" wire:click="editTicketType({{ $type->id }})" title="Ubah jenis tiket">
                                                    Edit
                                                </button>
                                                @if ($type->is_active)
                                                    <button type="button" class="btn btn-sm btn-danger" wire:click="deactivateTicketType({{ $type->id }})" title="Nonaktifkan jenis tiket">
                                                        Nonaktifkan
                                                    </button>
                                                @else
                                                    <button type="button" class="btn btn-sm" wire:click="activateTicketType({{ $type->id }})" title="Aktifkan jenis tiket">
                                                        Aktifkan
                                                    </button>
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-6 text-center text-wd-muted">Belum ada jenis tiket untuk destinasi ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div>{{ $ticketTypes->links() }}</div>
            @else
                <p class="text-sm text-wd-muted">Pilih destinasi untuk melihat atau mengelola jenis tiket.</p>
            @endif
        @endif
    </div>

    @if ($canManageDestinations && $showDestinationForm)
        <div class="modal-backdrop">
            <div class="modal-panel max-w-2xl">
                <h2 class="text-lg font-semibold">
                    {{ $editingDestinationId ? 'Edit destinasi' : 'Tambah destinasi' }}
                </h2>
                <form wire:submit="saveDestination" class="grid gap-3 sm:grid-cols-2">
                    <label class="field">
                        <span class="field-label">Kode</span>
                        <input type="text" wire:model="destinationCode" class="field-input" maxlength="40">
                        @error('destinationCode') <span class="mt-1 block text-xs text-red-700">{{ $message }}</span> @enderror
                    </label>
                    <label class="field">
                        <span class="field-label">Nama</span>
                        <input type="text" wire:model="destinationName" class="field-input" maxlength="160">
                        @error('destinationName') <span class="mt-1 block text-xs text-red-700">{{ $message }}</span> @enderror
                    </label>
                    <label class="field">
                        <span class="field-label">Timezone</span>
                        <input type="text" wire:model="destinationTimezone" class="field-input" placeholder="Asia/Jakarta">
                        @error('destinationTimezone') <span class="mt-1 block text-xs text-red-700">{{ $message }}</span> @enderror
                    </label>
                    <label class="field flex items-end gap-2 pb-2">
                        <input type="checkbox" wire:model="destinationActive" class="rounded border-wd-border text-wd-accent focus:ring-wd-accent">
                        <span class="text-sm">Aktif</span>
                    </label>
                    <label class="field sm:col-span-2">
                        <span class="field-label">Deskripsi</span>
                        <textarea wire:model="destinationDescription" class="field-textarea" rows="2"></textarea>
                    </label>
                    <div class="sm:col-span-2 flex flex-wrap justify-end gap-2">
                        <button type="button" class="btn" wire:click="cancelDestinationForm" title="Batalkan">Batal</button>
                        <button type="submit" class="btn btn-primary" title="Simpan destinasi">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($canManageTicketTypes && $selectedDestinationId !== '' && $showTicketForm)
        <div class="modal-backdrop">
            <div class="modal-panel max-w-3xl">
                <h2 class="text-lg font-semibold">
                    {{ $editingTicketTypeId ? 'Edit jenis tiket' : 'Tambah jenis tiket' }}
                </h2>
                <p class="text-xs text-wd-muted">Ubah harga berdampak pada quote baru saja. Total selalu dihitung server.</p>
                <form wire:submit="saveTicketType" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <label class="field">
                        <span class="field-label">Kode</span>
                        <input type="text" wire:model="ticketCode" class="field-input" maxlength="40">
                        @error('ticketCode') <span class="mt-1 block text-xs text-red-700">{{ $message }}</span> @enderror
                    </label>
                    <label class="field">
                        <span class="field-label">Nama</span>
                        <input type="text" wire:model="ticketName" class="field-input" maxlength="160">
                        @error('ticketName') <span class="mt-1 block text-xs text-red-700">{{ $message }}</span> @enderror
                    </label>
                    <label class="field">
                        <span class="field-label">Harga (IDR)</span>
                        <input type="number" min="0" wire:model="unitPrice" class="field-input">
                        @error('unitPrice') <span class="mt-1 block text-xs text-red-700">{{ $message }}</span> @enderror
                    </label>
                    <label class="field">
                        <span class="field-label">Pajak (IDR)</span>
                        <input type="number" min="0" wire:model="taxAmount" class="field-input">
                        @error('taxAmount') <span class="mt-1 block text-xs text-red-700">{{ $message }}</span> @enderror
                    </label>
                    <label class="field">
                        <span class="field-label">Biaya layanan (IDR)</span>
                        <input type="number" min="0" wire:model="serviceFeeAmount" class="field-input">
                        @error('serviceFeeAmount') <span class="mt-1 block text-xs text-red-700">{{ $message }}</span> @enderror
                    </label>
                    <label class="field">
                        <span class="field-label">Max per order</span>
                        <input type="number" min="1" wire:model="maxPerOrder" class="field-input">
                        @error('maxPerOrder') <span class="mt-1 block text-xs text-red-700">{{ $message }}</span> @enderror
                    </label>
                    <label class="field">
                        <span class="field-label">Validitas</span>
                        <select wire:model.live="validityType" class="field-select">
                            @foreach ($validityTypes as $type)
                                <option value="{{ $type->value }}">{{ $type->value }}</option>
                            @endforeach
                        </select>
                    </label>
                    @if ($validityType === 'days_from_issue')
                        <label class="field">
                            <span class="field-label">Validity days</span>
                            <input type="number" min="1" wire:model="validityDays" class="field-input">
                            @error('validityDays') <span class="mt-1 block text-xs text-red-700">{{ $message }}</span> @enderror
                        </label>
                    @endif
                    <label class="field">
                        <span class="field-label">Valid from (HH:MM)</span>
                        <input type="text" wire:model="validFromTime" class="field-input" placeholder="08:00">
                        @error('validFromTime') <span class="mt-1 block text-xs text-red-700">{{ $message }}</span> @enderror
                    </label>
                    <label class="field">
                        <span class="field-label">Valid until (HH:MM)</span>
                        <input type="text" wire:model="validUntilTime" class="field-input" placeholder="17:00">
                        @error('validUntilTime') <span class="mt-1 block text-xs text-red-700">{{ $message }}</span> @enderror
                    </label>
                    <label class="field flex items-end gap-2 pb-2">
                        <input type="checkbox" wire:model="ticketActive" class="rounded border-wd-border text-wd-accent focus:ring-wd-accent">
                        <span class="text-sm">Aktif</span>
                    </label>
                    <label class="field sm:col-span-2 lg:col-span-3">
                        <span class="field-label">Deskripsi</span>
                        <textarea wire:model="ticketDescription" class="field-textarea" rows="2"></textarea>
                    </label>
                    <div class="sm:col-span-2 lg:col-span-3 flex flex-wrap justify-end gap-2">
                        <button type="button" class="btn" wire:click="cancelTicketForm" title="Batalkan">Batal</button>
                        <button type="submit" class="btn btn-primary" title="Simpan jenis tiket">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>

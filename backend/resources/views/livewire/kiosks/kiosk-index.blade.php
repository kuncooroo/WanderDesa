<div class="page space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="page-title">Kiosk fleet</h1>
            <p class="page-desc">Registrasi, edit, aktivasi, maintenance, dan deaktivasi perangkat. Status online diturunkan dari heartbeat (stale {{ $staleSeconds }}s).</p>
        </div>
        @if ($canCreate)
            <button type="button" class="btn btn-primary" wire:click="openRegisterModal" title="Daftarkan kiosk baru">
                Daftarkan kiosk
            </button>
        @endif
    </div>

    @if ($errorMessage !== '')
        <div class="alert alert-error" role="alert">{{ $errorMessage }}</div>
    @endif
    @if ($flashMessage !== '')
        <div class="rounded-lg border border-wd-border bg-emerald-50 px-4 py-3 text-sm text-wd-accent">{{ $flashMessage }}</div>
    @endif

    <div class="card space-y-4">
        <div class="table-wrap">
            <table class="table min-w-[48rem]">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>device_id</th>
                        <th>Destinasi</th>
                        <th>Status</th>
                        <th>Online</th>
                        <th>Heartbeat</th>
                        <th>Versi</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($devices as $device)
                        <tr wire:key="device-{{ $device->id }}">
                            <td class="font-medium">{{ $device->name }}</td>
                            <td><code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">{{ $device->device_id }}</code></td>
                            <td>{{ $device->destination?->name ?? '—' }}</td>
                            <td>
                                <span>{{ $device->status->value }}</span>
                                @if ($device->maintenance_mode)
                                    <span class="text-wd-muted"> · maint</span>
                                @endif
                            </td>
                            <td>
                                <span class="{{ $device->isOnline($staleSeconds) ? 'text-wd-accent font-medium' : 'text-wd-muted' }}">
                                    {{ $device->isOnline($staleSeconds) ? 'online' : 'offline' }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap text-wd-muted">
                                {{ $device->last_heartbeat_at?->timezone(config('app.timezone')) ?? '—' }}
                            </td>
                            <td>{{ $device->software_version ?? '—' }}</td>
                            <td>
                                <div class="flex flex-wrap gap-1.5">
                                    <button type="button" class="btn btn-sm" wire:click="showDetail({{ $device->id }})" title="Lihat detail kiosk">
                                        Detail
                                    </button>
                                    @if ($canUpdate)
                                        <button type="button" class="btn btn-sm" wire:click="editDevice({{ $device->id }})" title="Ubah data kiosk">
                                            Edit
                                        </button>
                                    @endif

                                    @if ($canActivate && $device->status->value !== 'disabled')
                                        <button type="button" class="btn btn-sm" wire:click="issueActivation({{ $device->id }})" title="Terbitkan kode aktivasi">
                                            Aktifkan
                                        </button>
                                    @elseif ($canActivate && $device->status->value === 'disabled')
                                        <button type="button" class="btn btn-sm" wire:click="issueActivation({{ $device->id }})" title="Terbitkan kode re-aktivasi">
                                            Re-aktifkan
                                        </button>
                                    @endif

                                    @if ($canMaintenance && in_array($device->status->value, ['active', 'maintenance'], true))
                                        @if ($device->maintenance_mode)
                                            <button type="button" class="btn btn-sm" wire:click="toggleMaintenance({{ $device->id }}, false)" title="Keluar mode maintenance">
                                                Keluar maint
                                            </button>
                                        @else
                                            <button type="button" class="btn btn-sm" wire:click="toggleMaintenance({{ $device->id }}, true)" title="Masuk mode maintenance">
                                                Maintenance
                                            </button>
                                        @endif
                                    @endif

                                    @if ($canDeactivate && $device->status->value !== 'disabled')
                                        <button type="button" class="btn btn-sm btn-danger" wire:click="askDeactivate({{ $device->id }})" title="Nonaktifkan kiosk">
                                            Nonaktifkan
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-6 text-center text-wd-muted">Belum ada kiosk terdaftar.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $devices->links() }}</div>
    </div>

    @if ($showRegisterModal && $canCreate)
        <div class="modal-backdrop">
            <div class="modal-panel max-w-2xl">
                <h2 class="text-lg font-semibold">Daftarkan kiosk</h2>
                <form wire:submit="registerDevice" class="space-y-4">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="field">
                            <span class="field-label">device_id</span>
                            <input type="text" wire:model="deviceId" class="field-input">
                            @error('deviceId') <span class="mt-1 block text-xs text-red-700">{{ $message }}</span> @enderror
                        </label>
                        <label class="field">
                            <span class="field-label">Nama</span>
                            <input type="text" wire:model="name" class="field-input">
                            @error('name') <span class="mt-1 block text-xs text-red-700">{{ $message }}</span> @enderror
                        </label>
                        <label class="field">
                            <span class="field-label">terminal_id (opsional)</span>
                            <input type="text" wire:model="terminalId" class="field-input">
                            @error('terminalId') <span class="mt-1 block text-xs text-red-700">{{ $message }}</span> @enderror
                        </label>
                        <label class="field">
                            <span class="field-label">Destinasi</span>
                            <select wire:model="destinationId" class="field-select">
                                <option value="">— pilih —</option>
                                @foreach ($destinations as $destination)
                                    <option value="{{ $destination->id }}">{{ $destination->name }}</option>
                                @endforeach
                            </select>
                            @error('destinationId') <span class="mt-1 block text-xs text-red-700">{{ $message }}</span> @enderror
                        </label>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" class="btn" wire:click="closeRegisterModal" title="Batalkan">Batal</button>
                        <button type="submit" class="btn btn-primary" title="Daftarkan kiosk">Daftarkan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($canUpdate && $editingId)
        <div class="modal-backdrop">
            <div class="modal-panel max-w-lg">
                <h2 class="text-lg font-semibold">Edit kiosk #{{ $editingId }}</h2>
                <form wire:submit="saveDevice" class="grid gap-3 sm:grid-cols-2">
                    <label class="field">
                        <span class="field-label">Nama</span>
                        <input type="text" wire:model="editName" class="field-input" maxlength="120">
                        @error('editName') <span class="mt-1 block text-xs text-red-700">{{ $message }}</span> @enderror
                    </label>
                    <label class="field">
                        <span class="field-label">Destinasi</span>
                        <select wire:model="editDestinationId" class="field-select">
                            <option value="">— pilih —</option>
                            @foreach ($destinations as $destination)
                                <option value="{{ $destination->id }}">{{ $destination->name }}</option>
                            @endforeach
                        </select>
                        @error('editDestinationId') <span class="mt-1 block text-xs text-red-700">{{ $message }}</span> @enderror
                    </label>
                    <div class="sm:col-span-2 flex flex-wrap justify-end gap-2">
                        <button type="button" class="btn" wire:click="cancelEdit" title="Batalkan">Batal</button>
                        <button type="submit" class="btn btn-primary" title="Simpan perubahan">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($detailDevice)
        <div class="modal-backdrop">
            <div class="modal-panel max-w-lg">
                <div class="flex items-start justify-between gap-3">
                    <h2 class="text-lg font-semibold">Detail kiosk</h2>
                    <button type="button" class="btn btn-sm" wire:click="closeDetail" title="Tutup detail">Tutup</button>
                </div>
                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-wd-muted">Nama</dt>
                        <dd class="font-medium">{{ $detailDevice->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-wd-muted">device_id</dt>
                        <dd><code class="text-xs">{{ $detailDevice->device_id }}</code></dd>
                    </div>
                    <div>
                        <dt class="text-wd-muted">Destinasi</dt>
                        <dd>{{ $detailDevice->destination?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-wd-muted">Status</dt>
                        <dd>{{ $detailDevice->status->value }}{{ $detailDevice->maintenance_mode ? ' · maint' : '' }}</dd>
                    </div>
                    <div>
                        <dt class="text-wd-muted">Online</dt>
                        <dd>{{ $detailDevice->isOnline($staleSeconds) ? 'online' : 'offline' }}</dd>
                    </div>
                    <div>
                        <dt class="text-wd-muted">Heartbeat</dt>
                        <dd>{{ $detailDevice->last_heartbeat_at?->timezone(config('app.timezone')) ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-wd-muted">Software</dt>
                        <dd>{{ $detailDevice->software_version ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-wd-muted">Hardware</dt>
                        <dd>{{ $detailDevice->hardware_version ?? '—' }}</dd>
                    </div>
                </dl>
                @if ($canUpdate)
                    <div class="flex justify-end">
                        <button type="button" class="btn btn-primary" wire:click="editDevice({{ $detailDevice->id }})" title="Ubah data kiosk">
                            Edit
                        </button>
                    </div>
                @endif
            </div>
        </div>
    @endif

    @if ($activationCode)
        <div class="modal-backdrop">
            <div class="modal-panel max-w-md">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold">Kode aktivasi</h2>
                    <button type="button" class="btn btn-sm" wire:click="clearActivationModal" title="Tutup kode aktivasi">Tutup</button>
                </div>
                <p class="text-sm text-wd-muted">Salin kode ke aplikasi kiosk sekarang. Kode tidak disimpan dalam bentuk teks di server.</p>
                <p class="font-mono text-lg tracking-wider text-wd-accent">{{ $activationCode }}</p>
                <p class="text-sm text-wd-muted">Kadaluarsa: {{ $activationExpiresAt }} · device #{{ $activationDeviceId }}</p>
            </div>
        </div>
    @endif

    @if ($confirmDeactivateId)
        <div class="modal-backdrop">
            <div class="modal-panel max-w-md">
                <h2 class="text-lg font-semibold">Konfirmasi nonaktifkan</h2>
                <p class="text-sm">Perangkat akan DISABLED, token dicabut, dan tidak bisa berjualan hingga diaktifkan ulang.</p>
                <div class="flex flex-wrap justify-end gap-2">
                    <button type="button" class="btn" wire:click="cancelDeactivate" title="Batalkan">Batal</button>
                    <button type="button" class="btn btn-danger" wire:click="confirmDeactivate" title="Nonaktifkan kiosk">
                        Ya, nonaktifkan
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

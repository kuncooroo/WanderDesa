<div class="page space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="page-title">Pengguna</h1>
            <p class="page-desc">Kelola staf: aktif/nonaktif, dan peran utama (hanya Super Admin yang bisa assign peran).</p>
        </div>
        @if ($canCreate)
            <button type="button" class="btn btn-primary" wire:click="startCreate" title="Tambah pengguna baru">
                Tambah pengguna
            </button>
        @endif
    </div>

    @if ($errorMessage !== '')
        <div class="alert alert-error" role="alert">{{ $errorMessage }}</div>
    @endif
    @if ($flashMessage !== '')
        <div class="rounded-lg border border-wd-border bg-emerald-50 px-4 py-3 text-sm text-wd-accent">{{ $flashMessage }}</div>
    @endif

    <div class="card space-y-5">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <label class="field sm:col-span-2">
                <span class="field-label">Cari</span>
                <input type="search" wire:model.live.debounce.300ms="search" class="field-input" placeholder="Nama atau email…" autocomplete="off">
            </label>
            <label class="field">
                <span class="field-label">Status</span>
                <select wire:model.live="activeFilter" class="field-select">
                    <option value="">Semua</option>
                    <option value="1">Aktif</option>
                    <option value="0">Nonaktif</option>
                </select>
            </label>
        </div>

        <div class="table-wrap">
            <table class="table min-w-[48rem]">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>Peran</th>
                        <th>Status</th>
                        <th>Login terakhir</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr wire:key="user-{{ $user->id }}">
                            <td class="font-medium">{{ $user->name }}</td>
                            <td class="text-sm">{{ $user->email }}</td>
                            <td>
                                @forelse ($user->roles as $role)
                                    <span class="mr-1 inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium">{{ $role->display_name ?: $role->name }}</span>
                                @empty
                                    <span class="text-wd-muted">—</span>
                                @endforelse
                            </td>
                            <td>
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $user->is_active ? 'bg-emerald-50 text-wd-accent' : 'bg-gray-100 text-wd-muted' }}">
                                    {{ $user->is_active ? 'aktif' : 'nonaktif' }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap text-sm text-wd-muted">
                                {{ $user->last_login_at?->timezone(config('app.timezone')) ?? '—' }}
                            </td>
                            <td>
                                <div class="flex flex-wrap gap-1.5">
                                    <button type="button" class="btn btn-sm" wire:click="showDetail({{ $user->id }})" title="Lihat detail pengguna">
                                        Detail
                                    </button>
                                    @if ($canUpdate)
                                        <button type="button" class="btn btn-sm" wire:click="editUser({{ $user->id }})" title="Ubah data pengguna">
                                            Edit
                                        </button>
                                        @if ($user->id !== auth()->id())
                                            @if ($user->is_active)
                                                <button type="button" class="btn btn-sm" wire:click="deactivateUser({{ $user->id }})" title="Nonaktifkan akun">
                                                    Nonaktifkan
                                                </button>
                                            @else
                                                <button type="button" class="btn btn-sm" wire:click="activateUser({{ $user->id }})" title="Aktifkan akun">
                                                    Aktifkan
                                                </button>
                                            @endif
                                        @endif
                                    @endif
                                    @if ($canDelete && $user->id !== auth()->id())
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-danger"
                                            wire:click="deleteUser({{ $user->id }})"
                                            wire:confirm="Hapus pengguna ini? (soft delete)"
                                            title="Hapus pengguna"
                                        >
                                            Hapus
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-wd-muted">Belum ada pengguna.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $users->links() }}</div>
    </div>

    @if ($detailUser)
        <div class="modal-backdrop">
            <div class="modal-panel max-w-lg">
                <div class="flex items-start justify-between gap-3">
                    <h2 class="text-lg font-semibold">Detail pengguna</h2>
                    <button type="button" class="btn btn-sm" wire:click="closeDetail" title="Tutup detail">Tutup</button>
                </div>
                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-wd-muted">Nama</dt>
                        <dd class="font-medium">{{ $detailUser->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-wd-muted">Email</dt>
                        <dd>{{ $detailUser->email }}</dd>
                    </div>
                    <div>
                        <dt class="text-wd-muted">Status</dt>
                        <dd>{{ $detailUser->is_active ? 'aktif' : 'nonaktif' }}</dd>
                    </div>
                    <div>
                        <dt class="text-wd-muted">Login terakhir</dt>
                        <dd>{{ $detailUser->last_login_at?->timezone(config('app.timezone')) ?? '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-wd-muted">Peran</dt>
                        <dd class="mt-1">
                            @forelse ($detailUser->roles as $role)
                                <span class="mr-1 inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium">{{ $role->display_name ?: $role->name }}</span>
                            @empty
                                —
                            @endforelse
                        </dd>
                    </div>
                </dl>
                @if ($canUpdate)
                    <div class="flex justify-end">
                        <button type="button" class="btn btn-primary" wire:click="editUser({{ $detailUser->id }})" title="Ubah data pengguna">
                            Edit
                        </button>
                    </div>
                @endif
            </div>
        </div>
    @endif

    @if ($showForm && ($canCreate || $canUpdate))
        <div class="modal-backdrop">
            <div class="modal-panel max-w-2xl">
                <h2 class="text-lg font-semibold">
                    {{ $editingId ? 'Edit pengguna' : 'Tambah pengguna' }}
                </h2>
                <form wire:submit="saveUser" class="grid gap-3 sm:grid-cols-2">
                    <label class="field">
                        <span class="field-label">Nama</span>
                        <input type="text" wire:model="name" class="field-input" maxlength="120">
                        @error('name') <span class="mt-1 block text-xs text-red-700">{{ $message }}</span> @enderror
                    </label>
                    <label class="field">
                        <span class="field-label">Email</span>
                        <input type="email" wire:model="email" class="field-input" maxlength="255" autocomplete="off">
                        @error('email') <span class="mt-1 block text-xs text-red-700">{{ $message }}</span> @enderror
                    </label>
                    <label class="field">
                        <span class="field-label">{{ $editingId ? 'Password baru (opsional)' : 'Password' }}</span>
                        <input type="password" wire:model="password" class="field-input" autocomplete="new-password">
                        @error('password') <span class="mt-1 block text-xs text-red-700">{{ $message }}</span> @enderror
                    </label>
                    <label class="field">
                        <span class="field-label">Konfirmasi password</span>
                        <input type="password" wire:model="password_confirmation" class="field-input" autocomplete="new-password">
                    </label>
                    <label class="field flex items-end gap-2 pb-2">
                        <input type="checkbox" wire:model="isActive" class="rounded border-wd-border text-wd-accent focus:ring-wd-accent">
                        <span class="text-sm">Aktif</span>
                    </label>
                    @if ($canAssignRoles)
                        <label class="field">
                            <span class="field-label">Peran utama</span>
                            <select wire:model="primaryRole" class="field-select">
                                <option value="">— tanpa peran —</option>
                                @foreach ($roles as $role)
                                    <option value="{{ $role->name }}">{{ $role->display_name ?: $role->name }}</option>
                                @endforeach
                            </select>
                            @error('primaryRole') <span class="mt-1 block text-xs text-red-700">{{ $message }}</span> @enderror
                        </label>
                    @else
                        <p class="text-sm text-wd-muted sm:col-span-2">
                            Assign peran hanya Super Admin (`roles.assign`). Admin dapat membuat/mengubah data akun tanpa mengubah peran.
                        </p>
                    @endif
                    <div class="sm:col-span-2 flex flex-wrap justify-end gap-2">
                        <button type="button" class="btn" wire:click="cancelForm" title="Batalkan">Batal</button>
                        <button type="submit" class="btn btn-primary" title="Simpan pengguna">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>

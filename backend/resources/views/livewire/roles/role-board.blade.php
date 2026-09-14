<div class="page space-y-6">
    <div>
        <h1 class="page-title">Peran</h1>
        <p class="page-desc">
            Katalog peran MVP dan matriks izin (read-only). Ubah grant hanya lewat seeder/matrix resmi — tidak ada edit di dashboard.
        </p>
    </div>

    <div class="flex flex-wrap gap-2">
        <button type="button" class="btn-seg {{ $tab === 'roles' ? 'btn-seg-active' : '' }}" wire:click="showTab('roles')">Daftar peran</button>
        <button type="button" class="btn-seg {{ $tab === 'matrix' ? 'btn-seg-active' : '' }}" wire:click="showTab('matrix')">Matriks izin</button>
    </div>

    @if ($tab === 'roles')
        <div class="card space-y-4">
            <div class="table-wrap">
                <table class="table min-w-[40rem]">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Nama</th>
                            <th>Deskripsi</th>
                            <th>Izin</th>
                            <th>Pengguna</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($roles as $role)
                            <tr wire:key="role-{{ $role->id }}">
                                <td><code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">{{ $role->name }}</code></td>
                                <td class="font-medium">{{ $role->display_name ?: $role->name }}</td>
                                <td class="max-w-sm text-sm text-wd-muted">{{ $role->description ?: '—' }}</td>
                                <td>{{ $role->permissions_count }}</td>
                                <td>{{ $role->users_count }}</td>
                                <td>
                                    <button type="button" class="btn btn-sm" wire:click="selectRole({{ $role->id }})" title="Lihat detail peran">
                                        Detail
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-6 text-center text-wd-muted">Belum ada peran. Jalankan seeder RBAC.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($selected)
            <div class="modal-backdrop">
                <div class="modal-panel max-w-3xl space-y-4" wire:key="role-detail-{{ $selected->id }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold">{{ $selected->display_name ?: $selected->name }}</h2>
                        <p class="mt-1 text-sm text-wd-muted">
                            <code class="text-xs">{{ $selected->name }}</code>
                            · {{ $selected->permissions_count }} izin
                            · {{ $selected->users_count }} pengguna
                        </p>
                        @if ($selected->description)
                            <p class="mt-2 text-sm">{{ $selected->description }}</p>
                        @endif
                    </div>
                    <button type="button" class="btn btn-sm" wire:click="clearSelection" title="Tutup detail">Tutup</button>
                </div>

                <div>
                    <h3 class="mb-2 text-sm font-semibold">Izin yang di-grant</h3>
                    <div class="table-wrap">
                        <table class="table min-w-0">
                            <thead>
                                <tr>
                                    <th>Permission</th>
                                    <th>Label</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($selected->permissions as $permission)
                                    <tr wire:key="perm-{{ $permission->id }}">
                                        <td><code class="text-xs">{{ $permission->name }}</code></td>
                                        <td class="text-sm text-wd-muted">{{ $permission->display_name }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="2" class="text-wd-muted">Tidak ada izin.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @if ($canAssignRoles)
                    <p class="text-sm text-wd-muted">
                        Assign peran ke pengguna dilakukan di menu <strong>Pengguna</strong> (bukan di sini).
                    </p>
                @endif
                </div>
            </div>
        @endif
    @endif

    @if ($tab === 'matrix')
        <div class="card space-y-4">
            <p class="text-sm text-wd-muted">
                Matriks role × permission dari database (hasil seeder MVP). Sel bertanda ✓ = grant.
            </p>
            <div class="table-wrap overflow-x-auto">
                <table class="table min-w-max text-xs">
                    <thead>
                        <tr>
                            <th class="sticky left-0 z-10 bg-white">Permission</th>
                            @foreach ($roles as $role)
                                <th class="whitespace-nowrap text-center" title="{{ $role->description }}">
                                    {{ $role->display_name ?: $role->name }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($permissions as $permission)
                            <tr wire:key="matrix-{{ $permission->id }}">
                                <td class="sticky left-0 z-10 bg-white whitespace-nowrap">
                                    <code>{{ $permission->name }}</code>
                                </td>
                                @foreach ($roles as $role)
                                    @php $granted = in_array($permission->name, $grants[$role->id] ?? [], true); @endphp
                                    <td class="text-center {{ $granted ? 'text-wd-accent font-semibold' : 'text-wd-muted' }}">
                                        {{ $granted ? '✓' : '·' }}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

<div class="page space-y-6">
    <div>
        <h1 class="page-title">Audit logs</h1>
        <p class="page-desc">Jejak bisnis append-only. Tidak ada ubah/hapus.</p>
    </div>

    <div class="card space-y-5">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <label class="field">
                <span class="field-label">Action</span>
                <input type="search" wire:model.live.debounce.300ms="actionFilter" placeholder="refund.created" class="field-input">
            </label>
            <label class="field">
                <span class="field-label">Actor type</span>
                <select wire:model.live="actorTypeFilter" class="field-select">
                    <option value="">Semua</option>
                    <option value="user">user</option>
                    <option value="device">device</option>
                    <option value="system">system</option>
                    <option value="anonymous">anonymous</option>
                </select>
            </label>
            <label class="field">
                <span class="field-label">Entity</span>
                <input type="search" wire:model.live.debounce.300ms="entityTypeFilter" placeholder="payment" class="field-input">
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
            <table class="table">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Action</th>
                        <th>Actor</th>
                        <th>Entity</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr wire:key="audit-{{ $log->id }}">
                            <td class="whitespace-nowrap text-wd-muted">{{ $log->created_at?->timezone(config('app.timezone')) }}</td>
                            <td><code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">{{ $log->action }}</code></td>
                            <td>{{ $log->actor_type }}{{ $log->actor_id ? '#'.$log->actor_id : '' }}</td>
                            <td>
                                @if ($log->entity_type)
                                    {{ $log->entity_type }}#{{ $log->entity_id }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm" wire:click="selectLog({{ $log->id }})" title="Lihat detail audit">
                                    Detail
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-6 text-center text-wd-muted">Tidak ada audit untuk filter ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $logs->links() }}</div>
    </div>

    @if ($selected)
        <div class="modal-backdrop">
            <div class="modal-panel max-w-5xl space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-semibold">Detail #{{ $selected->id }}</h2>
                <button type="button" class="btn btn-sm" wire:click="clearSelection" title="Tutup detail">Tutup</button>
            </div>
            <p class="text-sm"><strong>Action:</strong> {{ $selected->action }}</p>
            <p class="text-sm"><strong>IP / UA:</strong> {{ $selected->ip_address ?? '—' }} / {{ $selected->user_agent ?? '—' }}</p>
            <div class="grid gap-4 lg:grid-cols-3">
                <div>
                    <h3 class="mb-2 text-sm font-semibold">Before</h3>
                    <pre class="code-box">{{ json_encode($selected->before_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: 'null' }}</pre>
                </div>
                <div>
                    <h3 class="mb-2 text-sm font-semibold">After</h3>
                    <pre class="code-box">{{ json_encode($selected->after_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: 'null' }}</pre>
                </div>
                <div>
                    <h3 class="mb-2 text-sm font-semibold">Meta</h3>
                    <pre class="code-box">{{ json_encode($selected->meta_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: 'null' }}</pre>
                </div>
            </div>
            </div>
        </div>
    @endif
</div>

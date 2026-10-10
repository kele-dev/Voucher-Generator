<x-layouts.app title="Aktivitas">
    <x-page-header title="Log aktivitas" subtitle="Jejak audit seluruh perubahan. Tidak pernah dihapus." />

    @php($hasActiveFilter = ! empty(request('action')) || ! empty(request('actor_id')) || ! empty(request('from')) || ! empty(request('to')))

    <form method="GET" class="card mb-6 p-4 sm:p-5 shadow-card">
        <div class="mb-3.5 flex items-center justify-between border-b border-line/60 pb-3">
            <div class="flex items-center gap-2">
                <span class="grid size-6 place-items-center rounded-md bg-primary/10 text-primary">
                    <x-icon name="filter" class="size-3.5" />
                </span>
                <span class="text-xs font-bold uppercase tracking-wider text-ink">Filter Log Aktivitas</span>
            </div>
            @if ($hasActiveFilter)
                <span class="inline-flex items-center gap-1 rounded-full border border-primary/20 bg-primary-soft px-2.5 py-0.5 text-[11px] font-semibold text-primary">
                    <span class="size-1.5 rounded-full bg-primary"></span>
                    Filter aktif
                </span>
            @endif
        </div>

        <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-12 items-end">
            <label class="block xl:col-span-3">
                <span class="mb-1.5 flex items-center gap-1 text-xs font-semibold text-ink-muted">
                    <x-icon name="layers" class="size-3.5 text-ink-muted" /> Aksi
                </span>
                <select name="action" class="input">
                    <option value="">Semua aksi</option>
                    @foreach ($actions as $a)
                        <option value="{{ $a }}" @selected(request('action') === $a)>{{ $a }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block xl:col-span-3">
                <span class="mb-1.5 flex items-center gap-1 text-xs font-semibold text-ink-muted">
                    <x-icon name="users" class="size-3.5 text-ink-muted" /> Pelaku
                </span>
                <select name="actor_id" class="input">
                    <option value="">Semua pelaku</option>
                    @foreach ($actors as $u)
                        <option value="{{ $u->id }}" @selected(request('actor_id') == $u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block xl:col-span-2">
                <span class="mb-1.5 flex items-center gap-1 text-xs font-semibold text-ink-muted">
                    <x-icon name="calendar" class="size-3.5 text-ink-muted" /> Dari tanggal
                </span>
                <input type="date" name="from" value="{{ request('from') }}" class="input">
            </label>
            <label class="block xl:col-span-2">
                <span class="mb-1.5 flex items-center gap-1 text-xs font-semibold text-ink-muted">
                    <x-icon name="calendar" class="size-3.5 text-ink-muted" /> Sampai tanggal
                </span>
                <input type="date" name="to" value="{{ request('to') }}" class="input">
            </label>
            <div class="flex items-center gap-2 xl:col-span-2">
                <x-button variant="secondary" icon="filter" class="w-full sm:w-auto">Terapkan</x-button>
                @if ($hasActiveFilter)
                    <a href="{{ route('activity.index') }}" class="inline-flex h-[42px] items-center justify-center gap-1 rounded-xl px-3 text-xs font-semibold text-ink-muted hover:bg-canvas hover:text-ink transition-colors" title="Reset filter">
                        <x-icon name="x" class="size-3.5" />
                        <span>Reset</span>
                    </a>
                @endif
            </div>
        </div>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Pelaku</th>
                    <th>Aksi</th>
                    <th>Objek</th>
                    <th>Detail</th>
                </tr>
            </thead>
            <tbody class="align-top">
                @forelse ($logs as $log)
                    <tr>
                        <td class="whitespace-nowrap">{{ $log->created_at->translatedFormat('j M Y H:i:s') }}</td>
                        <td>{{ $log->actor?->name ?? 'Sistem' }}
                            @if ($log->ip_address) <div class="text-xs text-ink-muted">{{ $log->ip_address }}</div> @endif</td>
                        <td><span class="rounded-md bg-canvas px-2 py-0.5 font-mono text-xs">{{ $log->action }}</span></td>
                        <td>{{ $log->subjectLabel() }}</td>
                        <td>
                            @if ($log->payload_json)
                                <pre class="max-w-md overflow-x-auto rounded-lg bg-canvas p-2 text-xs whitespace-pre-wrap text-ink-muted">{{ json_encode($log->payload_json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) }}</pre>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-empty-state icon="activity" title="Belum ada aktivitas." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $logs->links() }}</div>
</x-layouts.app>

<x-layouts.app title="Outlet">
    <x-page-header title="Outlet" subtitle="Cabang tempat voucher bisa ditukarkan.">
        @can('create', App\Models\Outlet::class)
            <x-button icon="plus" :href="route('outlets.create')">Tambah outlet</x-button>
        @endcan
    </x-page-header>

    @php($hasActiveFilter = ! empty(request('brand_id')))

    <form method="GET" class="card mb-6 p-4 shadow-card" x-data>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-ink-muted">
                    <span class="grid size-6 place-items-center rounded-md bg-primary/10 text-primary">
                        <x-icon name="filter" class="size-3.5" />
                    </span>
                    <span>Filter Brand:</span>
                </div>
                <select name="brand_id" @change="$el.form.submit()" class="input sm:w-56 py-2 text-xs" aria-label="Filter brand">
                    <option value="">Semua brand</option>
                    @foreach ($brands as $brand)
                        <option value="{{ $brand->id }}" @selected(request('brand_id') == $brand->id)>{{ $brand->name }}</option>
                    @endforeach
                </select>
            </div>
            @if ($hasActiveFilter)
                <div class="flex items-center pt-1 sm:pt-0">
                    <a href="{{ route('outlets.index') }}" class="inline-flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-semibold text-ink-muted hover:bg-canvas hover:text-ink transition-colors">
                        <x-icon name="x" class="size-3.5" />
                        <span>Reset filter</span>
                    </a>
                </div>
            @endif
        </div>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Kode</th>
                    <th>Brand</th>
                    <th>Kota</th>
                    <th>Status</th>
                    <th><span class="sr-only">Aksi</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($outlets as $outlet)
                    <tr class="group">
                        <td>
                            <div class="flex items-center gap-3">
                                <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-accent-soft text-accent border border-accent/20">
                                    <x-icon name="store" class="size-4.5" />
                                </span>
                                <div>
                                    <div class="font-bold text-ink group-hover:text-primary transition-colors">{{ $outlet->name }}</div>
                                    <div class="text-xs text-ink-muted">{{ $outlet->brand->name }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="inline-flex items-center rounded-lg bg-canvas px-2.5 py-1 font-mono text-xs font-bold text-ink border border-line">
                                {{ $outlet->code }}
                            </span>
                        </td>
                        <td>{{ $outlet->brand->name }}</td>
                        <td class="text-xs font-medium text-ink-muted">{{ $outlet->city ?? '-' }}</td>
                        <td><x-status-badge :active="$outlet->is_active" /></td>
                        <td class="text-right">
                            <div class="flex justify-end gap-2">
                                @can('update', $outlet)
                                    <x-button variant="secondary" size="sm" icon="pencil" :href="route('outlets.edit', $outlet)">Ubah</x-button>
                                @endcan
                                @can('delete', $outlet)
                                    <x-delete-button :action="route('outlets.destroy', $outlet)" :confirm="'Hapus outlet '.$outlet->name.'?'" />
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-empty-state icon="store" title="Belum ada outlet." description="Tambahkan cabang outlet tempat voucher promo dapat divalidasi dan digunakan." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $outlets->links() }}</div>
</x-layouts.app>

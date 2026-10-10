<x-layouts.app title="Brand">
    <x-page-header title="Brand" subtitle="Brand menentukan prefix kode, template, dan outlet.">
        @can('create', App\Models\Brand::class)
            <x-button icon="plus" :href="route('brands.create')">Tambah brand</x-button>
        @endcan
    </x-page-header>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Prefix kode</th>
                    <th>Outlet</th>
                    <th>Status</th>
                    <th><span class="sr-only">Aksi</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($brands as $brand)
                    <tr class="group">
                        <td>
                            <div class="flex items-center gap-3">
                                <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-primary/10 font-mono text-xs font-bold text-primary border border-primary/20">
                                    {{ Str::upper(Str::substr($brand->name, 0, 2)) }}
                                </span>
                                <div>
                                    <div class="font-bold text-ink group-hover:text-primary transition-colors">{{ $brand->name }}</div>
                                    <div class="text-xs text-ink-muted font-mono">{{ $brand->slug }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="inline-flex items-center rounded-lg bg-canvas px-2.5 py-1 font-mono text-xs font-bold text-ink border border-line">
                                {{ $brand->code_prefix }}
                            </span>
                        </td>
                        <td>
                            <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-ink-muted">
                                <x-icon name="store" class="size-3.5 text-ink-muted" />
                                <span class="tabular-nums font-bold text-ink">{{ $brand->outlets_count }}</span> cabang
                            </span>
                        </td>
                        <td><x-status-badge :active="$brand->is_active" /></td>
                        <td class="text-right">
                            <div class="flex justify-end gap-2">
                                @can('update', $brand)
                                    <x-button variant="secondary" size="sm" icon="pencil" :href="route('brands.edit', $brand)">Ubah</x-button>
                                @endcan
                                @can('delete', $brand)
                                    <x-delete-button :action="route('brands.destroy', $brand)" :confirm="'Hapus brand '.$brand->name.'?'" />
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-empty-state icon="tag" title="Belum ada brand." description="Tambahkan brand restoran untuk mulai membuat template dan outlet." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $brands->links() }}</div>
</x-layouts.app>

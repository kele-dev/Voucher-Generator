<x-layouts.app title="Template">
    <x-page-header title="Template voucher" subtitle="Desain dasar tiap brand. Atur posisi kode, menu, porsi, dan tanggal di editor.">
        @can('create', App\Models\VoucherTemplate::class)
            <x-button icon="plus" :href="route('templates.create')">Upload template</x-button>
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
                    <a href="{{ route('templates.index') }}" class="inline-flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-semibold text-ink-muted hover:bg-canvas hover:text-ink transition-colors">
                        <x-icon name="x" class="size-3.5" />
                        <span>Reset filter</span>
                    </a>
                </div>
            @endif
        </div>
    </form>

    @if ($templates->isEmpty())
        <div class="card shadow-card">
            <x-empty-state icon="template" title="Belum ada template." description="Upload gambar voucher kosong (JPG/PNG), lalu pasang field di editor." />
        </div>
    @else
        <div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($templates as $template)
                <article class="card group overflow-hidden transition-all duration-200 hover:-translate-y-1 hover:border-primary-line hover:shadow-soft">
                    <a href="{{ route('templates.editor', $template) }}" class="relative block overflow-hidden bg-canvas border-b border-line/60" aria-label="Buka editor {{ $template->name }}">
                        <img src="{{ route('templates.image', $template) }}" alt="{{ $template->name }}" loading="lazy"
                             width="{{ $template->image_width }}" height="{{ $template->image_height }}"
                             class="h-auto w-full object-cover transition duration-300 group-hover:scale-105">
                        <div class="absolute bottom-2.5 right-2.5 rounded-lg bg-black/70 px-2 py-1 text-[11px] font-mono font-bold text-white shadow-2xs">
                            {{ $template->image_width }}×{{ $template->image_height }}px
                        </div>
                    </a>
                    <div class="space-y-3 p-5">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <h2 class="truncate font-bold text-ink group-hover:text-primary transition-colors">{{ $template->name }}</h2>
                                <p class="text-xs text-ink-muted">{{ $template->brand->name }} · v{{ $template->version }}</p>
                            </div>
                            <x-status-badge :active="$template->is_active" />
                        </div>
                        <div class="flex items-center gap-2 text-xs font-semibold text-ink-muted">
                            <span class="inline-flex items-center gap-1.5 rounded-md bg-canvas px-2 py-0.5 border border-line">
                                <x-icon name="layers" class="size-3.5 text-primary" />
                                <span>{{ count($template->fields()) }} field terpasang</span>
                            </span>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 border-t border-line/80 pt-3.5">
                            <x-button size="sm" icon="pencil" :href="route('templates.editor', $template)">Editor</x-button>
                            @can('update', $template)
                                <x-button variant="secondary" size="sm" :href="route('templates.edit', $template)">Ubah</x-button>
                            @endcan
                            @can('createVersion', $template)
                                <form method="POST" action="{{ route('templates.versions', $template) }}">
                                    @csrf
                                    <x-button variant="secondary" size="sm">Versi baru</x-button>
                                </form>
                            @endcan
                            @can('delete', $template)
                                <x-delete-button :action="route('templates.destroy', $template)" :confirm="'Hapus '.$template->name.' v'.$template->version.'?'" />
                            @endcan
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @endif

    <div class="mt-6">{{ $templates->links() }}</div>
</x-layouts.app>

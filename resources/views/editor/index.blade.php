<x-layouts.app title="Editor Template">
    <x-page-header title="Editor template voucher" subtitle="Atur posisi teks, kode voucher, tanggal, dan foto produk di canvas interaktif.">
        <x-button variant="secondary" icon="tag" :href="route('brands.index')">Kelola desain di Brand</x-button>
    </x-page-header>

    {{-- Filter Brand Chips --}}
    <div class="mb-6 card p-4 shadow-card">
        <div class="flex flex-col gap-3">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-ink-muted">Pilih Brand:</span>
                @if ($selectedBrandId)
                    <a href="{{ route('editor.index') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline">
                        <x-icon name="refresh" class="size-3" /> Tampilkan Semua
                    </a>
                @endif
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('editor.index') }}"
                   @class([
                       'rounded-full px-3.5 py-1.5 text-xs font-bold transition',
                       'bg-primary text-white shadow-xs' => ! $selectedBrandId,
                       'bg-canvas text-ink-muted hover:bg-canvas hover:text-ink' => (bool) $selectedBrandId,
                   ])>
                    Semua Brand
                </a>
                @foreach ($brands as $brand)
                    <a href="{{ route('editor.index', ['brand_id' => $brand->id]) }}"
                       @class([
                           'rounded-full px-3.5 py-1.5 text-xs font-bold transition',
                           'bg-primary text-white shadow-xs' => $selectedBrandId === $brand->id,
                           'bg-canvas text-ink-muted hover:bg-canvas hover:text-ink' => $selectedBrandId !== $brand->id,
                       ])>
                        {{ $brand->name }}
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    @if ($templates->isEmpty())
        <div class="card p-8 shadow-card text-center">
            <div class="mx-auto grid size-12 place-items-center rounded-2xl bg-primary-soft text-primary">
                <x-icon name="pencil" class="size-6" />
            </div>
            <h3 class="mt-4 text-base font-bold text-ink">Belum Ada Template untuk Diedit</h3>
            <p class="mx-auto mt-2 max-w-md text-xs text-ink-muted">
                Silakan upload gambar master template di menu <strong class="text-ink">Master &gt; Brand &gt; Ubah brand</strong> untuk jenis Voucher Fisik, e-Voucher Fisik, Voucher Nominal, atau e-Voucher Nominal.
            </p>
            <div class="mt-5">
                <x-button icon="tag" :href="route('brands.index')">Buka Menu Brand</x-button>
            </div>
        </div>
    @else
        <div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($templates as $template)
                @php
                    $name = strtolower($template->name);
                    $isDigital = str_contains($name, 'e-voucher') || str_contains($name, 'digital');
                    $isNominal = str_contains($name, 'nominal');
                @endphp
                <article class="card group overflow-hidden transition-all duration-200 hover:-translate-y-1 hover:border-primary-line hover:shadow-soft">
                    <a href="{{ route('templates.editor', $template) }}" class="relative block overflow-hidden bg-canvas border-b border-line/60" aria-label="Buka editor {{ $template->name }}">
                        <img src="{{ route('templates.image', $template) }}" alt="{{ $template->name }}" loading="lazy"
                             width="{{ $template->image_width }}" height="{{ $template->image_height }}"
                             class="h-auto w-full object-cover transition duration-300 group-hover:scale-105">
                        <div class="absolute bottom-2.5 right-2.5 rounded-lg bg-black/70 px-2 py-1 text-[11px] font-mono font-bold text-white shadow-2xs">
                            {{ $template->image_width }}×{{ $template->image_height }}px
                        </div>
                        @if ($isNominal)
                            <div class="absolute top-2.5 left-2.5 rounded-full bg-amber-700/90 px-2.5 py-0.5 text-2xs font-extrabold text-white">
                                {{ $isDigital ? 'e-Nominal' : 'Nominal' }}
                            </div>
                        @elseif ($isDigital)
                            <div class="absolute top-2.5 left-2.5 rounded-full bg-emerald-700/90 px-2.5 py-0.5 text-2xs font-extrabold text-white">
                                e-Voucher
                            </div>
                        @elseif (str_contains($name, 'fisik'))
                            <div class="absolute top-2.5 left-2.5 rounded-full bg-primary/90 px-2.5 py-0.5 text-2xs font-extrabold text-white">
                                Fisik
                            </div>
                        @endif
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

                        <div class="flex flex-wrap items-center justify-between gap-2 border-t border-line/80 pt-3.5">
                            <x-button size="sm" icon="pencil" :href="route('templates.editor', $template)">Buka Canvas Editor</x-button>

                            <a href="{{ route('brands.edit', $template->brand) }}"
                               class="text-xs font-semibold text-ink-muted hover:text-primary transition-colors"
                               title="Ganti gambar dasar di Brand">
                                Ubah di Brand →
                            </a>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</x-layouts.app>

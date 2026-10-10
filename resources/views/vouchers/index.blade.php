<x-layouts.app title="Voucher">
    <x-page-header title="Voucher" subtitle="Cari, lihat, dan unduh voucher yang sudah terbit." />

    <form method="GET" class="card mb-6 p-4 shadow-card" x-data>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-12 items-center">
            <div class="relative sm:col-span-2 lg:col-span-4">
                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-ink-muted" />
                <label for="q" class="sr-only">Cari kode</label>
                <input id="q" name="q" value="{{ request('q') }}" placeholder="Cari kode voucher…" class="input pl-10 font-mono uppercase">
            </div>
            <div class="sm:col-span-1 lg:col-span-3">
                <select name="campaign_id" @change="$el.form.submit()" class="input" aria-label="Campaign">
                    <option value="">Semua campaign</option>
                    @foreach ($campaigns as $campaign)
                        <option value="{{ $campaign->id }}" @selected(request('campaign_id') == $campaign->id)>{{ $campaign->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-1 lg:col-span-2">
                <select name="brand_id" @change="$el.form.submit()" class="input" aria-label="Brand">
                    <option value="">Semua brand</option>
                    @foreach ($brands as $brand)
                        <option value="{{ $brand->id }}" @selected(request('brand_id') == $brand->id)>{{ $brand->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-1 lg:col-span-2">
                <select name="status" @change="$el.form.submit()" class="input" aria-label="Status">
                    <option value="">Semua status</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-2 sm:col-span-1 lg:col-span-1">
                <x-button variant="secondary" icon="search" class="w-full lg:w-auto" title="Cari">
                    <span class="lg:sr-only">Cari</span>
                </x-button>
                @if (request('q') || request('campaign_id') || request('brand_id') || request('status'))
                    <a href="{{ route('vouchers.index') }}" class="inline-flex h-[42px] items-center justify-center gap-1 rounded-xl px-2.5 text-xs font-semibold text-ink-muted hover:bg-canvas hover:text-ink transition-colors" title="Reset filter">
                        <x-icon name="x" class="size-3.5" />
                        <span class="sr-only">Reset</span>
                    </a>
                @endif
            </div>
        </div>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Kode Voucher</th>
                    <th>Campaign</th>
                    <th>Berlaku Hingga</th>
                    <th>Status</th>
                    <th class="text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($vouchers as $voucher)
                    @php($status = $voucher->effectiveStatus())
                    <tr class="group">
                        <td>
                            <a href="{{ route('vouchers.show', $voucher) }}" class="inline-flex items-center gap-1.5 rounded-full border border-primary/20 bg-primary-soft px-3 py-1 font-mono text-xs font-bold text-primary transition-colors hover:border-primary">
                                <x-icon name="ticket" class="size-3.5 text-primary" />
                                <span>{{ $voucher->code }}</span>
                            </a>
                            <div class="mt-1 flex items-center gap-1">
                                @if ($voucher->voucher_type || $voucher->campaign->voucher_type)
                                    <span class="inline-flex items-center rounded-full bg-amber-50 px-1.5 py-0.2 text-[9px] font-bold text-amber-700 border border-amber-200/70">
                                        {{ ($voucher->voucher_type ?? $voucher->campaign->voucher_type)->value }}
                                    </span>
                                @endif
                                @if ($voucher->voucher_kind || $voucher->campaign->voucher_kind)
                                    <span class="inline-flex items-center rounded-full border border-line bg-canvas px-1.5 py-0.2 text-[9px] font-bold text-ink-muted">
                                        {{ ($voucher->voucher_kind ?? $voucher->campaign->voucher_kind)->label() }}
                                    </span>
                                @endif
                            </div>
                        </td>
                        <td>
                            <a href="{{ route('campaigns.show', $voucher->campaign) }}" class="font-bold text-ink hover:text-primary transition-colors">
                                {{ $voucher->campaign->name }}
                            </a>
                            <div class="text-xs text-ink-muted">{{ $voucher->campaign->brand->name }}</div>
                        </td>
                        <td class="whitespace-nowrap font-medium text-xs text-ink-muted">
                            {{ $voucher->valid_until->translatedFormat('j M Y') }}
                        </td>
                        <td>
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold border {{ $status->badgeClass() }}">
                                <span class="size-1.5 rounded-full bg-current"></span>
                                {{ $status->label() }}
                            </span>
                        </td>
                        <td class="text-right">
                            <x-button variant="secondary" size="sm" icon="download" :href="route('vouchers.download', $voucher)">
                                Unduh
                            </x-button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">
                            <x-empty-state icon="ticket" title="Tidak ada voucher." description="Coba ubah filter pencarian, atau terbitkan voucher dari halaman campaign." />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $vouchers->links() }}</div>
</x-layouts.app>

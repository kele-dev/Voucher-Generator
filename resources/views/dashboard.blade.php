<x-layouts.app title="Dashboard">
    @php
        $brandCount = $brands->count();
    @endphp

    {{-- Hero: one solid brand panel, no gradient, no orbs. --}}
    <div class="mb-6 rounded-2xl bg-primary p-6 text-white sm:p-8">
        <div class="max-w-3xl">
            <h1 class="text-2xl font-black tracking-tight text-white sm:text-3xl">
                Apa yang ingin Anda terbitkan hari ini, {{ Str::before(auth()->user()->name, ' ') }}?
            </h1>
            <p class="mt-2 text-sm leading-relaxed text-white/90 sm:text-base">
                Pusat penerbitan voucher fisik dan e-voucher {{ $brandCount }} brand kuliner. Buat kode otomatis berstandar, atur masa berlaku, lalu unduh paket siap cetak.
            </p>

            <div class="mt-6 flex flex-wrap items-center gap-2.5">
                @can('create', App\Models\Campaign::class)
                    <a href="{{ route('campaigns.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-surface px-4 py-2.5 text-xs font-bold text-primary transition-colors hover:bg-white active:scale-[0.98]">
                        <x-icon name="plus" class="size-3.5" />
                        <span>Buat Campaign / Voucher</span>
                    </a>
                @endcan
                <a href="{{ route('vouchers.index') }}" class="inline-flex items-center gap-2 rounded-xl bg-white/15 px-4 py-2.5 text-xs font-bold text-white transition-colors hover:bg-white/25 active:scale-[0.98]">
                    <x-icon name="ticket" class="size-3.5" />
                    <span>Daftar Voucher</span>
                </a>
                <a href="{{ route('editor.index') }}" class="inline-flex items-center gap-2 rounded-xl bg-white/15 px-4 py-2.5 text-xs font-bold text-white transition-colors hover:bg-white/25 active:scale-[0.98]">
                    <x-icon name="pencil" class="size-3.5" />
                    <span>Studio Editor</span>
                </a>
                <a href="{{ route('brands.index') }}" class="inline-flex items-center gap-2 rounded-xl bg-white/15 px-4 py-2.5 text-xs font-bold text-white transition-colors hover:bg-white/25 active:scale-[0.98]">
                    <x-icon name="tag" class="size-3.5" />
                    <span>{{ $brandCount }} Brand Terdaftar</span>
                </a>
            </div>
        </div>
    </div>

    {{-- Brand quick filter --}}
    <div class="mb-6 flex items-center gap-2 overflow-x-auto pb-2">
        <span class="shrink-0 text-xs font-bold tracking-wider text-ink-muted uppercase">Pilih brand:</span>
        <a href="{{ route('dashboard') }}"
           @class([
               'shrink-0 inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs font-bold transition-colors',
               'bg-primary text-white' => empty($filters->brandId),
               'border border-line bg-surface text-ink-muted hover:border-primary/40 hover:text-primary' => ! empty($filters->brandId),
           ])>
            Semua ({{ $brandCount }})
        </a>
        @foreach ($brands as $b)
            @php $isSelected = ($filters->brandId == $b->id); @endphp
            <a href="{{ route('dashboard', array_merge(request()->query(), ['brand_id' => $isSelected ? null : $b->id])) }}"
               @class([
                   'shrink-0 inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs font-bold transition-colors',
                   'bg-primary text-white' => $isSelected,
                   'border border-line bg-surface text-ink-muted hover:border-primary/40 hover:text-primary' => ! $isSelected,
               ])>
                <span class="font-mono text-[10px]">{{ $b->code_prefix }}</span>
                <span>{{ $b->name }}</span>
            </a>
        @endforeach
    </div>

    <x-dashboard-filters :filters="$filters" :brands="$brands" :campaigns="$campaignOptions" :action="route('dashboard')" />

    @php
        // Two metrics carry the page; the rest are supporting numbers. This
        // hierarchy is deliberate, not seven equal cards.
        $featured = array_values(array_filter([
            ['Voucher diterbitkan', $metrics['issued'], 'terbit dalam periode terpilih', 'ticket'],
            ['Aktif', $metrics['active'], 'siap digunakan pelanggan', 'clock'],
        ], fn ($m) => $m[1] !== null));
        $supporting = array_values(array_filter([
            ['Batch generate', $metrics['batches'], 'dibuat dalam periode', 'zap'],
            ['Campaign', $metrics['campaigns'], 'sesuai filter brand & campaign', 'campaign'],
            ['Kedaluwarsa', $metrics['expired'], 'melewati batas masa berlaku', 'calendar'],
            ['Dibatalkan', $metrics['voided'], 'dibatalkan manual oleh staf', 'x'],
            ['User aktif', $metrics['users_active'], 'pengguna internal aktif', 'users'],
        ], fn ($m) => $m[1] !== null));
    @endphp

    <div class="grid gap-4 sm:grid-cols-2">
        @foreach ($featured as [$label, $value, $hint, $icon])
            <div class="card p-5">
                <div class="flex items-center gap-3">
                    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-primary-soft text-primary">
                        <x-icon :name="$icon" class="size-5" />
                    </span>
                    <p class="text-sm font-bold text-ink">{{ $label }}</p>
                </div>
                <p class="mt-3 text-4xl font-black tracking-tight text-ink tabular-nums" x-data="countUp({{ (int) $value }})" x-text="display">{{ number_format($value) }}</p>
                <p class="mt-1 text-xs text-ink-muted">{{ $hint }}</p>
            </div>
        @endforeach
    </div>

    @if (count($supporting))
        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($supporting as [$label, $value, $hint, $icon])
                <div class="flex items-center gap-3 rounded-2xl border border-line bg-surface px-4 py-3">
                    <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-canvas text-ink-muted">
                        <x-icon :name="$icon" class="size-4" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-semibold text-ink-muted">{{ $label }}</p>
                        <p class="text-xs text-ink-muted">{{ $hint }}</p>
                    </div>
                    <p class="text-xl font-black tabular-nums text-ink" x-data="countUp({{ (int) $value }})" x-text="display">{{ number_format($value) }}</p>
                </div>
            @endforeach
        </div>
    @endif

    <div class="mt-8 grid gap-6 lg:grid-cols-[1fr_20rem]">
        {{-- Recent campaigns --}}
        <section class="card overflow-hidden">
            <header class="flex flex-wrap items-center justify-between gap-3 border-b border-line/80 px-6 py-4">
                <div>
                    <h2 class="text-base font-bold tracking-tight text-ink">Campaign terbaru</h2>
                    <p class="text-xs text-ink-muted">Daftar campaign terbitan terkini</p>
                </div>
                <a href="{{ route('campaigns.index') }}" class="group inline-flex items-center gap-1 text-xs font-semibold text-primary hover:text-primary-hover">
                    <span>Lihat semua</span>
                    <x-icon name="arrow-right" class="size-3.5 transition-transform duration-150 group-hover:translate-x-0.5" />
                </a>
            </header>

            <div class="table-wrap border-0 rounded-none shadow-none">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Campaign</th>
                            <th>Status</th>
                            @if ($campaignWide)
                                <th class="text-right">Terbit</th>
                            @endif
                            <th class="w-10"><span class="sr-only">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($campaigns as $c)
                            <tr class="group">
                                <td>
                                    <div class="flex items-center gap-3">
                                        <span class="grid size-8 shrink-0 place-items-center rounded-lg border border-line bg-canvas font-mono text-[11px] font-bold text-ink-muted">
                                            {{ Str::substr($c->brand->name, 0, 2) }}
                                        </span>
                                        <div class="min-w-0">
                                            <a href="{{ route('campaigns.show', $c) }}" class="font-bold text-ink transition-colors group-hover:text-primary">
                                                {{ $c->name }}
                                            </a>
                                            <p class="text-xs text-ink-muted">{{ $c->brand->name }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td><x-campaign-status-badge :status="$c->status" /></td>
                                @if ($campaignWide)
                                    <td class="text-right">
                                        <span class="inline-flex items-center gap-1 rounded-md border border-line/60 bg-canvas px-2 py-0.5 text-xs font-bold tabular-nums text-ink">
                                            <x-icon name="ticket" class="size-3 text-primary" />
                                            {{ number_format($c->vouchers_count) }}
                                        </span>
                                    </td>
                                @endif
                                <td class="text-right">
                                    <a href="{{ route('campaigns.show', $c) }}" class="text-ink-muted hover:text-primary" title="Lihat detail campaign">
                                        <x-icon name="arrow-right" class="size-4" />
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">
                                    <x-empty-state icon="campaign" title="Belum ada campaign." description="Mulai dengan membuat campaign baru untuk mendistribusikan voucher promo restoran.">
                                        @can('create', App\Models\Campaign::class)
                                            <x-button icon="plus" :href="route('campaigns.create')">Buat Campaign Baru</x-button>
                                        @endcan
                                    </x-empty-state>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Status breakdown --}}
        <div class="space-y-6">
            <x-card title="Campaign per status" description="Distribusi status seluruh campaign">
                @php
                    $totalCampaigns = $campaignCounts->sum();
                @endphp

                @if ($totalCampaigns > 0)
                    <div class="mb-5 flex h-2.5 w-full overflow-hidden rounded-full bg-canvas">
                        @foreach ($statuses as $s)
                            @php
                                $cnt = $campaignCounts[$s->value] ?? 0;
                                $pct = ($cnt / $totalCampaigns) * 100;
                            @endphp
                            @if ($pct > 0)
                                <div style="width: {{ $pct }}%"
                                     title="{{ $s->label() }}: {{ $cnt }}"
                                     @class([
                                         'h-full transition-[width] duration-300',
                                         'bg-ink-muted/50' => $s === App\Enums\CampaignStatus::Draft,
                                         'bg-emerald-500' => $s === App\Enums\CampaignStatus::Active,
                                         'bg-amber-500' => $s === App\Enums\CampaignStatus::Paused,
                                         'bg-rose-500' => $s === App\Enums\CampaignStatus::Closed,
                                     ])></div>
                            @endif
                        @endforeach
                    </div>
                @endif

                <dl class="space-y-2.5 text-sm">
                    @foreach ($statuses as $s)
                        <div class="flex items-center justify-between rounded-lg px-2.5 py-1.5 transition-colors hover:bg-canvas">
                            <dt><x-campaign-status-badge :status="$s" /></dt>
                            <dd class="flex items-center gap-1.5 font-bold tabular-nums text-ink">
                                <span>{{ number_format($campaignCounts[$s->value] ?? 0) }}</span>
                                <span class="text-xs font-normal text-ink-muted">campaign</span>
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </x-card>
        </div>
    </div>
</x-layouts.app>

@php($showCounts = auth()->user()->role !== App\Enums\Role::OutletStaff)

<x-layouts.app title="Campaign">
    <x-page-header title="Campaign" subtitle="Isi voucher (menu gratis, masa berlaku, syarat), lalu generate batch.">
        @can('create', App\Models\Campaign::class)
            <x-button icon="plus" :href="route('campaigns.create')">Buat campaign</x-button>
        @endcan
    </x-page-header>

    @php($hasActiveFilter = ! empty(request('brand_id')) || ! empty(request('status')))

    <form method="GET" class="card mb-6 p-4 shadow-card" x-data>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-ink-muted">
                    <span class="grid size-6 place-items-center rounded-md bg-primary/10 text-primary">
                        <x-icon name="filter" class="size-3.5" />
                    </span>
                    <span>Filter:</span>
                </div>
                <div class="grid grid-cols-1 gap-2.5 sm:flex sm:items-center">
                    <select name="brand_id" @change="$el.form.submit()" class="input sm:w-48 py-2 text-xs" aria-label="Filter brand">
                        <option value="">Semua brand</option>
                        @foreach ($brands as $brand)
                            <option value="{{ $brand->id }}" @selected(request('brand_id') == $brand->id)>{{ $brand->name }}</option>
                        @endforeach
                    </select>
                    <select name="status" @change="$el.form.submit()" class="input sm:w-44 py-2 text-xs" aria-label="Filter status">
                        <option value="">Semua status</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            @if ($hasActiveFilter)
                <div class="flex items-center pt-1 sm:pt-0">
                    <a href="{{ route('campaigns.index') }}" class="inline-flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-semibold text-ink-muted hover:bg-canvas hover:text-ink transition-colors">
                        <x-icon name="x" class="size-3.5" />
                        <span>Reset filter</span>
                    </a>
                </div>
            @endif
        </div>
    </form>

    @if ($campaigns->isEmpty())
        <div class="card shadow-card">
            <x-empty-state icon="campaign" title="Belum ada campaign." description="Buat campaign untuk menentukan menu gratis, masa berlaku, lalu generate voucher.">
                @can('create', App\Models\Campaign::class)
                    <x-button icon="plus" :href="route('campaigns.create')">Buat campaign</x-button>
                @endcan
            </x-empty-state>
        </div>
    @else
        <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($campaigns as $campaign)
                @php($content = App\Services\Voucher\VoucherContent::texts(App\Services\Voucher\VoucherContent::snapshot($campaign)))
                <a href="{{ route('campaigns.show', $campaign) }}"
                   class="card group flex flex-col p-5.5 transition-all duration-200 hover:-translate-y-1 hover:border-primary-line hover:shadow-soft">
                    <div class="flex items-start justify-between gap-3">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="inline-flex items-center rounded-full border border-primary/20 bg-primary-soft px-2.5 py-0.5 text-[11px] font-mono font-bold text-primary">
                                    {{ $campaign->brand->code_prefix }} · {{ $campaign->brand->name }}
                                </span>
                                @if ($campaign->voucher_type)
                                    <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-700 border border-amber-200/80">
                                        {{ $campaign->voucher_type->label() }}
                                    </span>
                                @endif
                                @if ($campaign->voucher_kind)
                                    <span class="inline-flex items-center rounded-full border border-line bg-canvas px-2 py-0.5 text-[10px] font-bold text-ink-muted">
                                        {{ $campaign->voucher_kind->label() }}
                                    </span>
                                @endif
                            </div>
                            <h2 class="mt-2 truncate text-lg font-bold text-ink group-hover:text-primary transition-colors">{{ $campaign->name }}</h2>
                        </div>
                        <x-campaign-status-badge :status="$campaign->status" />
                    </div>

                    <div class="mt-3.5 rounded-xl border border-primary/10 bg-primary-soft/60 p-3.5">
                        <p class="text-xs font-bold text-primary">{{ $content['free_portion'] ?: $campaign->benefit_title }}</p>
                        <p class="mt-1 line-clamp-2 text-xs text-ink-muted leading-relaxed">{{ $content['menu_items'] ?: $campaign->benefit_description }}</p>
                    </div>

                    <div class="flex-1"></div>
                    <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-line/80 pt-3 text-xs text-ink-muted">
                        <span class="inline-flex items-center gap-1.5 font-medium">
                            <x-icon name="calendar" class="size-3.5 text-ink-muted" />
                            {{ $campaign->starts_at->translatedFormat('j M') }} – {{ $campaign->expires_at->translatedFormat('j M Y') }}
                        </span>
                        @if ($showCounts)
                            <span class="inline-flex items-center gap-1 rounded-md bg-canvas px-2 py-0.5 font-bold tabular-nums text-ink border border-line/60">
                                <x-icon name="ticket" class="size-3 text-primary" />
                                {{ number_format($campaign->vouchers_count) }}{{ $campaign->generation_limit ? ' / '.number_format($campaign->generation_limit) : '' }}
                            </span>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    @endif

    <div class="mt-6">{{ $campaigns->links() }}</div>
</x-layouts.app>

@props(['filters', 'brands', 'campaigns', 'action'])

@php($q = $filters->query())

@php($hasActiveFilter = ! empty($q['from']) || ! empty($q['to']) || ! empty($q['brand_id']) || ! empty($q['campaign_id']))

<form method="GET" action="{{ $action }}" class="card mb-6 p-4 sm:p-5 shadow-card">
    <div class="mb-3.5 flex items-center justify-between border-b border-line/60 pb-3">
        <div class="flex items-center gap-2">
            <span class="grid size-6 place-items-center rounded-md bg-primary/10 text-primary">
                <x-icon name="filter" class="size-3.5" />
            </span>
            <span class="text-xs font-bold uppercase tracking-wider text-ink">Filter Periode & Data</span>
        </div>
        @if ($hasActiveFilter)
            <span class="inline-flex items-center gap-1 rounded-full border border-primary/20 bg-primary-soft px-2.5 py-0.5 text-[11px] font-semibold text-primary">
                <span class="size-1.5 rounded-full bg-primary"></span>
                Filter aktif
            </span>
        @endif
    </div>

    <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-12 items-end">
        <label class="block xl:col-span-2">
            <span class="mb-1.5 flex items-center gap-1 text-xs font-semibold text-ink-muted">
                <x-icon name="calendar" class="size-3.5 text-ink-muted" /> Dari tanggal
            </span>
            <input type="date" name="from" value="{{ $q['from'] ?? '' }}" class="input">
        </label>
        <label class="block xl:col-span-2">
            <span class="mb-1.5 flex items-center gap-1 text-xs font-semibold text-ink-muted">
                <x-icon name="calendar" class="size-3.5 text-ink-muted" /> Sampai tanggal
            </span>
            <input type="date" name="to" value="{{ $q['to'] ?? '' }}" class="input">
        </label>
        <label class="block xl:col-span-3">
            <span class="mb-1.5 flex items-center gap-1 text-xs font-semibold text-ink-muted">
                <x-icon name="tag" class="size-3.5 text-ink-muted" /> Brand
            </span>
            <select name="brand_id" class="input">
                <option value="">Semua brand</option>
                @foreach ($brands as $b)
                    <option value="{{ $b->id }}" @selected(($q['brand_id'] ?? null) == $b->id)>{{ $b->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="block xl:col-span-3">
            <span class="mb-1.5 flex items-center gap-1 text-xs font-semibold text-ink-muted">
                <x-icon name="campaign" class="size-3.5 text-ink-muted" /> Campaign
            </span>
            <select name="campaign_id" class="input">
                <option value="">Semua campaign</option>
                @foreach ($campaigns as $c)
                    <option value="{{ $c->id }}" @selected(($q['campaign_id'] ?? null) == $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </label>
        <div class="flex items-center gap-2 xl:col-span-2">
            <x-button variant="secondary" icon="filter" class="w-full sm:w-auto">Terapkan</x-button>
            @if ($q)
                <a href="{{ $action }}" class="inline-flex h-[42px] items-center justify-center gap-1 rounded-xl px-3 text-xs font-semibold text-ink-muted hover:bg-canvas hover:text-ink transition-colors" title="Reset filter">
                    <x-icon name="x" class="size-3.5" />
                    <span>Reset</span>
                </a>
            @endif
        </div>
        {{ $slot }}
    </div>
</form>

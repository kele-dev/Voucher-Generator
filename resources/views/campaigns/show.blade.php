@use('App\Enums\VoucherStatus')
@use('App\Services\Voucher\VoucherContent')
@php
    $canGenerate = auth()->user()->can('generate', $campaign) && $campaign->status->canGenerate() && ! $campaign->isExpired();
    $issuedTotal = $counts->sum();
    // Campaign-wide totals and batches span all outlets: not for outlet staff.
    $campaignWide = auth()->user()->role !== App\Enums\Role::OutletStaff;
    $content = array_filter(VoucherContent::texts(VoucherContent::snapshot($campaign)));
    $canPreview = auth()->user()->can('create', App\Models\Campaign::class);
@endphp

<x-layouts.app :title="$campaign->name" wide>
    <x-page-header :title="$campaign->name" :back="route('campaigns.index')"
                   :subtitle="$campaign->brand->name.' · template '.$campaign->template->name.' v'.$campaign->template->version">
        @can('changeStatus', $campaign)
            @foreach ($campaign->status->allowedTransitions() as $next)
                <form method="POST" action="{{ route('campaigns.status', $campaign) }}" x-data
                      @if ($next === App\Enums\CampaignStatus::Closed) @submit.prevent="$store.confirm.ask({ title: 'Tutup campaign?', body: 'Campaign yang ditutup tidak bisa dibuka lagi dan tidak bisa generate voucher baru. Voucher yang sudah terbit tetap berlaku.', confirmLabel: 'Tutup campaign', tone: 'danger' }).then(ok => ok && $el.submit())" @endif>
                    @csrf @method('PATCH')
                    <input type="hidden" name="status" value="{{ $next->value }}">
                    <x-button :variant="$next === App\Enums\CampaignStatus::Active ? 'accent' : 'secondary'" size="sm">
                        {{ match ($next) {
                            App\Enums\CampaignStatus::Active => $campaign->status === App\Enums\CampaignStatus::Paused ? 'Lanjutkan' : 'Aktifkan',
                            App\Enums\CampaignStatus::Paused => 'Jeda',
                            App\Enums\CampaignStatus::Closed => 'Tutup',
                            default => $next->label(),
                        } }}
                    </x-button>
                </form>
            @endforeach
        @endcan
        @can('update', $campaign)
            <x-button variant="secondary" size="sm" icon="pencil" :href="route('campaigns.edit', $campaign)">Ubah</x-button>
        @endcan
        @can('delete', $campaign)
            <x-delete-button :action="route('campaigns.destroy', $campaign)" :confirm="'Hapus campaign '.$campaign->name.'?'" />
        @endcan
    </x-page-header>

    <div class="mb-6 flex flex-wrap items-center gap-3 text-sm">
        <x-campaign-status-badge :status="$campaign->status" />
        @if ($campaign->voucher_type)
            <span class="inline-flex items-center rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-700 border border-amber-200">
                Tipe: {{ $campaign->voucher_type->label() }}
            </span>
        @endif
        @if ($campaign->voucher_kind)
            <span class="inline-flex items-center rounded-full border border-line bg-canvas px-3 py-1 text-xs font-bold text-ink-muted">
                Jenis: {{ $campaign->voucher_kind->label() }}
            </span>
        @endif
        <span class="inline-flex items-center gap-1.5 text-ink-muted"><x-icon name="calendar" class="size-4" />
            {{ $campaign->starts_at->translatedFormat('j M Y H:i') }} – {{ $campaign->expires_at->translatedFormat('j M Y H:i') }}
            @if ($campaign->isExpired()) <span class="font-semibold text-danger">(berakhir)</span> @endif
        </span>
    </div>

    {{-- Summary --}}
    @if ($campaignWide)
        <div class="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['Diterbitkan', $issuedTotal, null, 'ticket', 'bg-primary text-white'],
                ['Aktif', max(0, ($counts['issued'] ?? 0) - $expiredCount), VoucherStatus::Issued, 'clock', 'bg-emerald-600 text-white'],
                ['Kedaluwarsa', $expiredCount, VoucherStatus::Expired, 'calendar', 'bg-amber-600 text-white'],
                ['Dibatalkan', $counts['voided'] ?? 0, VoucherStatus::Voided, 'x', 'bg-rose-600 text-white'],
            ] as [$label, $value, $status, $icon, $theme])
                <a href="{{ route('vouchers.index', array_filter(['campaign_id' => $campaign->id, 'status' => $status?->value])) }}"
                   class="card group p-5 transition duration-200 hover:border-primary/40 hover:shadow-soft">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-bold tracking-wider text-ink-muted uppercase">{{ $label }}</p>
                        <span class="grid size-9 place-items-center rounded-2xl {{ $theme }} shadow-sm transition-transform group-hover:scale-110">
                            <x-icon :name="$icon" class="size-4.5" />
                        </span>
                    </div>
                    <p class="mt-2 text-3xl font-black tracking-tight text-ink tabular-nums">{{ number_format($value) }}</p>
                </a>
            @endforeach
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[1fr_24rem]">
        <div class="space-y-6">
            {{-- Voucher content + preview --}}
            <x-card title="Isi voucher" icon="utensils" description="Yang tercetak pada voucher berikutnya.">
                <div @class(['grid gap-6', 'lg:grid-cols-[1fr_1.1fr]' => $canPreview])>
                    <dl class="space-y-4 text-sm">
                        @if (! empty($campaign->menu_items))
                            <div>
                                <dt class="mb-2 text-xs font-semibold tracking-wide text-ink-muted uppercase">Menu gratis</dt>
                                <dd>
                                    <ul class="space-y-1.5">
                                        @foreach ($campaign->menu_items as $item)
                                            <li class="flex items-center justify-between rounded-lg bg-canvas px-3 py-2">
                                                <span class="font-medium">{{ $item['name'] }}</span>
                                                <span class="rounded-md bg-primary-soft px-2 py-0.5 text-xs font-bold text-primary tabular-nums">{{ $item['qty'] }} porsi</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                    @isset($content['free_portion']) <p class="mt-2 font-bold text-primary">{{ $content['free_portion'] }}</p> @endisset
                                </dd>
                            </div>
                        @endif
                        <div><dt class="text-xs font-semibold tracking-wide text-ink-muted uppercase">Benefit</dt>
                            <dd class="mt-1 font-medium">{{ $campaign->benefit_title }} {{ $campaign->benefit_description }}</dd></div>
                        @foreach (['normal_value' => 'Nilai', 'min_purchase' => 'Minimal belanja', 'service_type' => 'Layanan', 'valid_schedule' => 'Hari & jam'] as $key => $label)
                            @isset($content[$key])
                                <div><dt class="text-xs font-semibold tracking-wide text-ink-muted uppercase">{{ $label }}</dt><dd class="mt-1">{{ $content[$key] }}</dd></div>
                            @endisset
                        @endforeach
                        <div><dt class="text-xs font-semibold tracking-wide text-ink-muted uppercase">Outlet</dt><dd class="mt-1">{{ $campaign->outletScopeText() }}</dd></div>
                        @if ($campaign->terms) <div><dt class="text-xs font-semibold tracking-wide text-ink-muted uppercase">Syarat</dt><dd class="mt-1">{{ $campaign->terms }}</dd></div> @endif
                    </dl>

                    @if ($canPreview)
                        <div>
                            <a href="{{ route('campaigns.saved-preview', $campaign) }}" target="_blank" rel="noopener"
                               class="group relative block overflow-hidden rounded-xl border border-line bg-canvas"
                               title="Buka pratinjau ukuran penuh di tab baru">
                                <img src="{{ route('campaigns.saved-preview', $campaign) }}" alt="Pratinjau voucher {{ $campaign->name }}" loading="lazy"
                                     width="{{ $campaign->template->image_width }}" height="{{ $campaign->template->image_height }}"
                                     class="block h-auto w-full transition duration-300 group-hover:scale-[1.01]">
                                <span class="absolute right-2 bottom-2 inline-flex items-center gap-1.5 rounded-full bg-black/70 px-3 py-1 text-xs font-semibold text-white opacity-90 transition group-hover:opacity-100">
                                    <x-icon name="eye" class="size-3.5" /> Buka di tab baru
                                </span>
                            </a>
                            <p class="mt-2 text-xs text-ink-muted">Kode contoh. Slot yang belum dipasang di editor template tidak tampil.</p>
                        </div>
                    @endif
                </div>
            </x-card>

            {{-- Batches --}}
            @if ($campaignWide)
                <section>
                    <h2 class="mb-3 text-base font-bold">Batch</h2>
                    <div class="space-y-3">
                        @forelse ($batches as $batch)
                            <div class="card p-4 text-sm"
                                 x-data="batchRow(@js($batch->toStatusArray()), @js(route('batches.show', $batch)))">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <span class="font-bold">Batch #{{ $batch->id }}</span>
                                        <span class="text-ink-muted">· {{ number_format($batch->requested_count) }} voucher · {{ $batch->created_at->translatedFormat('j M Y H:i') }}
                                            @if ($batch->creator) · {{ $batch->creator->name }} @endif</span>
                                    </div>
                                    <template x-if="batch.download_url">
                                        <a :href="batch.download_url" class="inline-flex items-center gap-2 rounded-lg bg-accent px-3 py-1.5 text-xs font-semibold text-white hover:bg-accent-hover">
                                            <x-icon name="download" class="size-4" /> Unduh ZIP
                                        </a>
                                    </template>
                                </div>

                                <div class="mt-3 space-y-2">
                                    <div>
                                        <div class="flex justify-between text-xs text-ink-muted">
                                            <span>Kode: <span x-text="batch.status_label"></span></span>
                                            <span x-text="`${batch.generated} / ${batch.requested}`"></span>
                                        </div>
                                        <div class="mt-1 h-2 rounded-full bg-canvas" role="progressbar" aria-label="Kemajuan pembuatan kode voucher"
                                             aria-valuemin="0" :aria-valuemax="batch.requested" :aria-valuenow="batch.generated"
                                             :aria-valuetext="`${batch.generated} dari ${batch.requested} kode`"><div class="h-2 rounded-full bg-primary transition-[width] duration-500 ease-out" :style="`width: ${generatedPercent}%`"></div></div>
                                    </div>
                                    <template x-if="batch.export_status">
                                        <div>
                                            <div class="flex justify-between text-xs text-ink-muted">
                                                <span>Gambar/ZIP: <span x-text="batch.export_status_label"></span></span>
                                                <span x-text="`${batch.exported} / ${batch.requested}`"></span>
                                            </div>
                                            <div class="mt-1 h-2 rounded-full bg-canvas" role="progressbar" aria-label="Kemajuan pembuatan gambar dan ZIP"
                                                 aria-valuemin="0" :aria-valuemax="batch.requested" :aria-valuenow="batch.exported"
                                                 :aria-valuetext="`${batch.exported} dari ${batch.requested} gambar`"><div class="h-2 rounded-full bg-accent transition-[width] duration-500 ease-out" :style="`width: ${exportedPercent}%`"></div></div>
                                        </div>
                                    </template>
                                    <p x-show="batch.status === 'pending'" class="text-xs text-amber-800">Menunggu queue worker. Pastikan <code>php artisan queue:work</code> berjalan.</p>
                                    <p x-show="batch.error" x-text="batch.error" class="text-xs text-danger"></p>
                                    <p x-show="batch.export_error" x-text="batch.export_error" class="text-xs text-danger"></p>
                                </div>

                                @can('export', $batch)
                                    {{-- Recovery when a job was lost (worker crash, queue outage). Safe to repeat. --}}
                                    <form method="POST" action="{{ route('batches.retry', $batch) }}" class="mt-3"
                                          x-show="batch.status !== 'completed' || batch.export_status !== 'completed'" x-data
                                          @submit.prevent="$store.confirm.ask({ title: 'Proses ulang batch ini?', body: 'Aman diulang: voucher yang sudah dibuat tidak digandakan.', confirmLabel: 'Proses ulang', tone: 'neutral' }).then(ok => ok && $el.submit())">
                                        @csrf
                                        <button class="inline-flex items-center gap-1 text-xs font-semibold text-amber-800 hover:underline"><x-icon name="refresh" class="size-3.5" /> Macet? Proses ulang</button>
                                    </form>

                                    <form method="POST" action="{{ route('batches.export', $batch) }}" class="mt-3 flex flex-wrap items-center gap-2"
                                          x-show="batch.status === 'completed' && batch.export_status !== 'processing'"
                                          x-data="{ sending: false }" @submit="sending = true">
                                        @csrf
                                        <select name="export_format" class="input w-auto py-1 text-xs" aria-label="Format">
                                            @foreach (config('voucher.exports.formats') as $format)
                                                <option value="{{ $format }}" @selected($batch->export_format === $format)>{{ strtoupper($format) }}</option>
                                            @endforeach
                                        </select>
                                        <select name="export_scale" class="input w-auto py-1 text-xs" aria-label="Resolusi">
                                            @foreach (config('voucher.exports.scales') as $scale)
                                                <option value="{{ $scale }}" @selected($batch->export_scale === $scale)>{{ $scale }}%</option>
                                            @endforeach
                                        </select>
                                        <button class="text-xs font-semibold text-primary hover:underline disabled:opacity-60" :disabled="sending"
                                                x-text="sending ? 'Memproses…' : 'Export ulang'">Export ulang</button>
                                    </form>
                                @endcan
                            </div>
                        @empty
                            <div class="card"><x-empty-state icon="ticket" title="Belum ada batch." description="Generate voucher dari panel di samping." /></div>
                        @endforelse
                    </div>
                </section>
            @endif
        </div>

        {{-- Generate + details --}}
        <aside class="space-y-6">
            @if ($canGenerate)
                <form method="POST" action="{{ route('campaigns.batches.store', $campaign) }}"
                      class="card space-y-4 p-5 text-sm"
                      x-data="{ quantity: {{ (int) old('quantity', 100) }}, sending: false }" @submit="sending = true">
                    @csrf
                    {{-- Fixed per page load: a double submit reuses it and gets the same batch. --}}
                    <input type="hidden" name="idempotency_key" value="{{ (string) Str::uuid() }}">
                    <h2 class="flex items-center gap-2 text-base font-bold"><x-icon name="ticket" class="text-primary" /> Generate voucher</h2>

                    <div class="flex flex-wrap gap-2">
                        @foreach (config('voucher.batches.quantities') as $q)
                            <button type="button" @click="quantity = {{ $q }}"
                                    :class="quantity === {{ $q }} ? 'bg-primary text-white border-primary' : 'bg-surface hover:bg-primary-soft'"
                                    class="min-h-11 rounded-lg border border-line-strong px-3 font-semibold tabular-nums transition">{{ number_format($q) }}</button>
                        @endforeach
                    </div>

                    <x-field label="Jumlah" name="quantity" :hint="'Maks '.number_format(config('voucher.batches.max_count')).' per batch.'">
                        <input id="quantity" name="quantity" @aria('quantity', true) type="number" min="1" max="{{ config('voucher.batches.max_count') }}"
                               x-model.number="quantity" required class="input">
                    </x-field>

                    <div class="grid grid-cols-2 gap-3">
                        <x-field label="Format file" name="export_format">
                            <select id="export_format" name="export_format" @aria('export_format') class="input">
                                @foreach (config('voucher.exports.formats') as $format)
                                    <option value="{{ $format }}">{{ strtoupper($format) }}</option>
                                @endforeach
                            </select>
                        </x-field>
                        <x-field label="Resolusi" name="export_scale">
                            <select id="export_scale" name="export_scale" @aria('export_scale') class="input">
                                @foreach (config('voucher.exports.scales') as $scale)
                                    <option value="{{ $scale }}">{{ $scale }}% ({{ intdiv($campaign->template->image_width * $scale, 100) }}px)</option>
                                @endforeach
                            </select>
                        </x-field>
                    </div>

                    <div class="space-y-0.5 text-xs text-ink-muted">
                        @if ($remainingQuota !== null) <p>Sisa kuota campaign: {{ number_format($remainingQuota) }}</p> @endif
                        @if ($seqRemaining !== null) <p>Sisa nomor urut hari ini: {{ number_format($seqRemaining) }}</p> @endif
                    </div>

                    <x-button class="w-full" x-bind:disabled="sending">
                        <span x-text="sending ? 'Memproses…' : `Generate ${quantity.toLocaleString('id-ID')} voucher`"></span>
                    </x-button>
                </form>
            @endif

            <x-card title="Detail" icon="code">
                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="text-xs font-semibold tracking-wide text-ink-muted uppercase">Format kode</dt>
                        <dd class="mt-1"><span class="font-mono text-xs">{{ $campaign->code_pattern }}</span><br><span class="font-mono font-bold">{{ $example }}</span></dd>
                        @if ($pattern->isGuessable())
                            <dd class="mt-1 flex items-center gap-1 text-xs text-amber-800"><x-icon name="alert" class="size-3.5" /> Pola tanpa bagian acak, kode mudah ditebak.</dd>
                        @endif
                    </div>
                    <div><dt class="text-xs font-semibold tracking-wide text-ink-muted uppercase">Batas total</dt><dd class="mt-1">{{ $campaign->generation_limit ? number_format($campaign->generation_limit) : 'Tanpa batas' }}</dd></div>
                    @if ($campaign->product_image_path)
                        <div><dt class="text-xs font-semibold tracking-wide text-ink-muted uppercase">Foto produk</dt>
                            <dd><img src="{{ route('campaigns.product-image', $campaign) }}" alt="Foto produk" class="mt-1 h-24 rounded-lg border border-line bg-canvas object-contain"></dd></div>
                    @endif
                </dl>
            </x-card>
        </aside>
    </div>
</x-layouts.app>

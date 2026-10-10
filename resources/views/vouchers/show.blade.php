@php
    $status = $voucher->effectiveStatus();
    $lines = $voucher->contentLines();
@endphp

<x-layouts.app :title="$voucher->code">
    <x-page-header :title="$voucher->code" :back="route('vouchers.index')" :subtitle="$voucher->campaign->name.' · '.$voucher->campaign->brand->name">
        <x-button variant="secondary" icon="copy" data-copy="{{ $voucher->code }}" x-data="copyButton()" x-on:click="copy()">
            <span x-show="! copied">Salin kode</span>
            <span x-show="copied" x-cloak>Tersalin</span>
        </x-button>
        <x-button icon="download" :href="route('vouchers.download', [$voucher, 'format' => 'jpg'])">Unduh JPG</x-button>
        <x-button variant="secondary" :href="route('vouchers.download', [$voucher, 'format' => 'png'])">Unduh PNG</x-button>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="card p-3 sm:p-4">
            <img src="{{ route('vouchers.download', [$voucher, 'inline' => 1]) }}" alt="Voucher {{ $voucher->code }}"
                 width="{{ $voucher->template->image_width }}" height="{{ $voucher->template->image_height }}"
                 class="h-auto w-full rounded-xl bg-canvas">
        </div>

        <div class="space-y-6">
            <x-card>
                <div class="mb-4"><span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $status->badgeClass() }}">{{ $status->label() }}</span></div>
                <dl class="space-y-3 text-sm">
                    <div><dt class="text-xs font-semibold tracking-wide text-ink-muted uppercase">Campaign</dt>
                        <dd class="mt-0.5"><a href="{{ route('campaigns.show', $voucher->campaign) }}" class="link">{{ $voucher->campaign->name }}</a> · {{ $voucher->campaign->brand->name }}</dd></div>
                    <div><dt class="text-xs font-semibold tracking-wide text-ink-muted uppercase">Benefit</dt><dd class="mt-0.5 font-semibold">{{ $voucher->benefit_title }} {{ $voucher->benefit_description }}</dd></div>
                    @isset($lines['menu_items'])
                        <div><dt class="text-xs font-semibold tracking-wide text-ink-muted uppercase">Menu</dt>
                            <dd class="mt-0.5">{{ $lines['menu_items'] }} @isset($lines['free_portion']) <span class="font-bold text-primary">· {{ $lines['free_portion'] }}</span> @endisset</dd></div>
                    @endisset
                    @foreach (['normal_value' => 'Nilai', 'min_purchase' => 'Minimal belanja', 'service_type' => 'Layanan', 'valid_schedule' => 'Hari & jam'] as $key => $label)
                        @isset($lines[$key]) <div><dt class="text-xs font-semibold tracking-wide text-ink-muted uppercase">{{ $label }}</dt><dd class="mt-0.5">{{ $lines[$key] }}</dd></div> @endisset
                    @endforeach
                    <div><dt class="text-xs font-semibold tracking-wide text-ink-muted uppercase">Berlaku</dt>
                        <dd class="mt-0.5">{{ $voucher->valid_from->translatedFormat('j M Y H:i') }} – {{ $voucher->valid_until->translatedFormat('j M Y H:i') }}</dd></div>
                    <div><dt class="text-xs font-semibold tracking-wide text-ink-muted uppercase">Outlet</dt><dd class="mt-0.5">{{ $voucher->outlet_scope_text }}</dd></div>
                    <div><dt class="text-xs font-semibold tracking-wide text-ink-muted uppercase">Diterbitkan</dt>
                        <dd class="mt-0.5">{{ $voucher->issued_at->translatedFormat('j M Y H:i') }}@if ($voucher->batch) · batch #{{ $voucher->batch->id }}@endif</dd></div>
                    <div><dt class="text-xs font-semibold tracking-wide text-ink-muted uppercase">Template</dt><dd class="mt-0.5">{{ $voucher->template->name }} v{{ $voucher->template->version }}</dd></div>
                    @if ($voucher->voided_at)
                        <div><dt class="text-xs font-semibold tracking-wide text-ink-muted uppercase">Dibatalkan</dt><dd class="mt-0.5">{{ $voucher->voided_at->translatedFormat('j M Y H:i') }} · {{ $voucher->void_reason }}</dd></div>
                    @endif
                </dl>
            </x-card>

            @can('void', $voucher)
                @if ($voucher->status === App\Enums\VoucherStatus::Issued)
                    <form method="POST" action="{{ route('vouchers.void', $voucher) }}" class="card space-y-3 p-5 text-sm"
                          x-data @submit.prevent="$store.confirm.ask({ title: 'Batalkan voucher ini?', body: 'Voucher yang dibatalkan tidak bisa dipakai dan tidak bisa dikembalikan.', confirmLabel: 'Batalkan voucher', cancelLabel: 'Kembali', tone: 'danger' }).then(ok => ok && $el.submit())">
                        @csrf
                        <x-field label="Batalkan voucher" name="void_reason">
                            <input id="void_reason" name="void_reason" @aria('void_reason') required minlength="5" maxlength="255" placeholder="Alasan pembatalan"
                                   value="{{ old('void_reason') }}" class="input">
                        </x-field>
                        <x-button variant="danger" icon="x">Batalkan voucher</x-button>
                    </form>
                @endif
            @endcan
        </div>
    </div>
</x-layouts.app>

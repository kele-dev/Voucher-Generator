@use('App\Services\Voucher\VoucherContent')
@php
    $editing = $campaign->exists;
    $selectedOutlets = array_map('intval', old('outlet_ids', $campaign->outlets?->pluck('id')->all() ?? []));
    $menuItems = old('menu_items', $campaign->menu_items ?? []);
    $validDays = array_map('intval', old('valid_days', $campaign->valid_days ?? []));
    $serviceType = old('service_type', $campaign->service_type ?? 'any');
    $menuErrors = collect($errors->get('menu_items.*'))->flatten()->unique();
@endphp

<x-layouts.app :title="$editing ? 'Ubah campaign' : 'Buat campaign'" wide>
    <x-page-header :title="$editing ? 'Ubah campaign' : 'Buat campaign'"
                   subtitle="Isi menu gratis, masa berlaku, dan syarat, lalu pratinjau voucher diperbarui otomatis."
                   :back="$editing ? route('campaigns.show', $campaign) : route('campaigns.index')" />

    @if ($locked)
        <div class="mb-6 flex gap-3 rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950">
            <x-icon name="alert" class="mt-0.5 text-amber-700" />
            <p>Campaign ini sudah menerbitkan voucher. Perubahan hanya berlaku untuk batch berikutnya,
                voucher yang sudah terbit tetap memakai isi, tanggal, dan gambar saat diterbitkan. Brand dan template tidak bisa diganti.</p>
        </div>
    @endif

    <form method="POST" enctype="multipart/form-data"
          action="{{ $editing ? route('campaigns.update', $campaign) : route('campaigns.store') }}"
          class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_28rem]"
          @input.debounce.400ms="refreshPreview()" @change="refreshPreview()"
          x-data="campaignForm(@js([
              'brandId' => old('brand_id', $campaign->brand_id),
              'voucherKind' => old('voucher_kind', $campaign->voucher_kind?->value ?? 'physical'),
              'voucherType' => old('voucher_type', $campaign->voucher_type?->value ?? 'VM'),
              'templateId' => old('voucher_template_id', $campaign->voucher_template_id),
              'prefix' => old('code_prefix', $campaign->code_prefix ?? ''),
              'lastBrandPrefix' => $brands->firstWhere('id', $campaign->brand_id)?->code_prefix ?? '',
              'preset' => old('code_preset', $preset),
              'pattern' => old('code_pattern', $campaign->code_pattern),
              'allOutlets' => (bool) old('applies_to_all_outlets', $campaign->applies_to_all_outlets),
              'brands' => $brands,
              'templates' => $templates,
              'outlets' => $outlets,
              'presets' => $presets,
              'charset' => config('voucher.codes.charset'),
              'alphaCharset' => config('voucher.codes.alpha_charset'),
              'menuItems' => array_values($menuItems),
              'minPurchase' => old('min_purchase', $campaign->min_purchase),
              'normalValue' => old('normal_value', $campaign->normal_value),
              'previewUrl' => route('campaigns.preview'),
              'campaignId' => $campaign->id,
          ]))">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="min-w-0 space-y-6">
            <x-card title="Pengaturan Voucher" icon="template" description="Alur: Pilih Brand > Jenis Voucher > Tipe Voucher.">
                <div class="space-y-5">
                    {{-- 1. Pilih Brand --}}
                    <x-field label="1. Brand" name="brand_id">
                        <select id="brand_id" name="brand_id" @aria('brand_id') x-model="brandId" @change="changeBrand()" required class="input" @disabled($locked)>
                            <option value="">Pilih brand</option>
                            @foreach ($brands as $brand)
                                <option value="{{ $brand->id }}">{{ $brand->name }} (Inisial: {{ $brand->code_prefix }})</option>
                            @endforeach
                        </select>
                        @if ($locked) <input type="hidden" name="brand_id" value="{{ $campaign->brand_id }}"> @endif
                    </x-field>

                    {{-- 2. Pilih Jenis Voucher & 3. Pilih Tipe Voucher --}}
                    <div class="grid gap-5 sm:grid-cols-2">
                        <fieldset>
                            <legend class="mb-2 text-sm font-bold text-ink">2. Jenis Voucher</legend>
                            <div class="flex flex-wrap gap-2.5">
                                @foreach ($voucherKinds as $kind)
                                    <label class="cursor-pointer">
                                        <input type="radio" name="voucher_kind" value="{{ $kind->value }}" class="peer sr-only"
                                               x-model="voucherKind" @change="changeVoucherKind()">
                                        <span class="inline-flex min-h-10 items-center rounded-xl border border-line bg-surface px-4 text-xs font-bold text-ink-muted shadow-2xs transition peer-checked:border-primary peer-checked:bg-primary-soft peer-checked:text-primary peer-checked:ring-2 peer-checked:ring-primary/30 peer-focus-visible:ring-2 peer-focus-visible:ring-primary">
                                            {{ $kind->label() }}
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            @error('voucher_kind') <p class="mt-1.5 text-sm font-medium text-danger">{{ $message }}</p> @enderror
                        </fieldset>

                        <fieldset>
                            <legend class="mb-2 text-sm font-bold text-ink">3. Tipe Voucher</legend>
                            <div class="flex flex-wrap gap-2.5">
                                @foreach ($voucherTypes as $type)
                                    <label class="cursor-pointer">
                                        <input type="radio" name="voucher_type" value="{{ $type->value }}" class="peer sr-only"
                                               x-model="voucherType" @change="changeVoucherType()">
                                        <span class="inline-flex min-h-10 items-center rounded-xl border border-line bg-surface px-4 text-xs font-bold text-ink-muted shadow-2xs transition peer-checked:border-primary peer-checked:bg-primary-soft peer-checked:text-primary peer-checked:ring-2 peer-checked:ring-primary/30 peer-focus-visible:ring-2 peer-focus-visible:ring-primary">
                                            {{ $type->shortLabel() }} ({{ $type->value }})
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            @error('voucher_type') <p class="mt-1.5 text-sm font-medium text-danger">{{ $message }}</p> @enderror
                        </fieldset>
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-field label="Template Voucher" name="voucher_template_id">
                            <select id="voucher_template_id" name="voucher_template_id" @aria('voucher_template_id') x-model="templateId" required class="input" @disabled($locked)>
                                <option value="">Pilih template</option>
                                <template x-for="t in filteredTemplates" :key="t.id">
                                    <option :value="t.id" x-text="`${t.name} (v${t.version})`" :selected="String(t.id) === String(templateId)"></option>
                                </template>
                            </select>
                            @if ($locked) <input type="hidden" name="voucher_template_id" value="{{ $campaign->voucher_template_id }}"> @endif
                        </x-field>

                        <x-field label="Nama campaign" name="name">
                            <input id="name" name="name" @aria('name') value="{{ old('name', $campaign->name) }}" required maxlength="150" class="input" placeholder="Promo Voucher Makan">
                        </x-field>
                    </div>

                    <x-field label="Deskripsi internal" name="description" optional>
                        <textarea id="description" name="description" @aria('description') rows="2" maxlength="2000" class="input">{{ old('description', $campaign->description) }}</textarea>
                    </x-field>
                </div>
            </x-card>

            <x-card title="Menu & porsi gratis" icon="utensils" description="Daftar menu yang didapat pemegang voucher. Total porsi dihitung otomatis.">
                <div class="space-y-3">
                    <template x-for="(item, index) in menuItems" :key="index">
                        <div class="flex items-center gap-2 rounded-xl border border-line bg-canvas/60 p-2 sm:gap-3 sm:p-3">
                            <span class="hidden size-8 shrink-0 place-items-center rounded-lg bg-surface text-xs font-bold text-ink-muted sm:grid" x-text="index + 1"></span>
                            <div class="min-w-0 flex-1">
                                <label :for="`menu_items_${index}_name`" class="sr-only" x-text="`Nama menu ${index + 1}`"></label>
                                <input :id="`menu_items_${index}_name`" :name="`menu_items[${index}][name]`" x-model="item.name"
                                       maxlength="60" placeholder="Nama menu, mis. Bebek Goreng" class="input">
                            </div>
                            <div class="flex shrink-0 items-center rounded-lg border border-line-strong bg-surface">
                                <button type="button" @click="stepQty(index, -1)" class="grid size-10 place-items-center text-ink-muted hover:text-primary" :aria-label="`Kurangi porsi menu ${index + 1}`">
                                    <x-icon name="minus" class="size-4" />
                                </button>
                                <label :for="`menu_items_${index}_qty`" class="sr-only">Jumlah porsi</label>
                                <input :id="`menu_items_${index}_qty`" :name="`menu_items[${index}][qty]`" x-model.number="item.qty" type="number" min="1" max="99"
                                       class="w-10 border-0 bg-transparent p-0 text-center text-sm font-bold tabular-nums focus:ring-0 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none">
                                <button type="button" @click="stepQty(index, 1)" class="grid size-10 place-items-center text-ink-muted hover:text-primary" :aria-label="`Tambah porsi menu ${index + 1}`">
                                    <x-icon name="plus" class="size-4" />
                                </button>
                            </div>
                            <button type="button" @click="removeMenu(index)" class="grid size-10 shrink-0 place-items-center rounded-lg text-ink-muted hover:bg-danger-soft hover:text-danger" :aria-label="`Hapus menu ${index + 1}`">
                                <x-icon name="trash" class="size-4" />
                            </button>
                        </div>
                    </template>

                    @error('menu_items') <p class="text-sm font-medium text-danger">{{ $message }}</p> @enderror
                    @foreach ($menuErrors as $message) <p class="text-sm font-medium text-danger">{{ $message }}</p> @endforeach

                    <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
                        <x-button type="button" variant="secondary" size="sm" icon="plus" x-on:click="addMenu()" x-bind:disabled="!canAddMenu">Tambah menu</x-button>
                        <p class="text-sm">Total: <span class="rounded-lg bg-primary-soft px-2.5 py-1 font-bold text-primary tabular-nums" x-text="totalPortions ? `GRATIS ${totalPortions} PORSI` : '-'"></span></p>
                    </div>
                </div>

                <div class="mt-6 grid gap-5 border-t border-line pt-6 sm:grid-cols-2">
                    <x-field label="Judul benefit" name="benefit_title" hint="Teks besar di voucher, mis. 1 PORSI atau GRATIS.">
                        <input id="benefit_title" name="benefit_title" @aria('benefit_title', true) value="{{ old('benefit_title', $campaign->benefit_title) }}" required maxlength="100" class="input">
                    </x-field>
                    <x-field label="Deskripsi benefit" name="benefit_description" optional hint="Mis. NASI BEBEK + ES TEH">
                        <input id="benefit_description" name="benefit_description" @aria('benefit_description', true) value="{{ old('benefit_description', $campaign->benefit_description) }}" maxlength="200" class="input">
                    </x-field>
                    <x-field label="Nilai / harga normal" name="normal_value" optional hint="Tercetak: “Senilai Rp…”">
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-3 grid place-items-center text-sm text-ink-muted">Rp</span>
                            <input id="normal_value" name="normal_value" @aria('normal_value', true) type="number" min="0" step="500" inputmode="numeric" x-model="normalValue" class="input pl-10">
                        </div>
                        <p class="mt-1 text-xs font-semibold text-accent" x-show="normalValue" x-text="rupiah(normalValue)"></p>
                    </x-field>
                    <x-field label="Minimal belanja" name="min_purchase" optional hint="Info untuk kasir, tidak dicek sistem.">
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-3 grid place-items-center text-sm text-ink-muted">Rp</span>
                            <input id="min_purchase" name="min_purchase" @aria('min_purchase', true) type="number" min="0" step="500" inputmode="numeric" x-model="minPurchase" class="input pl-10">
                        </div>
                        <p class="mt-1 text-xs font-semibold text-accent" x-show="minPurchase" x-text="rupiah(minPurchase)"></p>
                    </x-field>
                </div>

                <fieldset class="mt-5">
                    <legend class="mb-2 text-sm font-semibold">Jenis layanan</legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach (VoucherContent::SERVICE_TYPES as $value => $label)
                            <label class="cursor-pointer">
                                <input type="radio" name="service_type" value="{{ $value }}" class="peer sr-only" @checked($serviceType === $value)>
                                <span class="inline-flex min-h-10 items-center rounded-lg border border-line-strong bg-surface px-4 text-sm font-semibold text-ink-muted transition peer-checked:border-primary peer-checked:bg-primary-soft peer-checked:text-primary peer-focus-visible:ring-2 peer-focus-visible:ring-primary">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('service_type') <p class="mt-1.5 text-sm font-medium text-danger">{{ $message }}</p> @enderror
                </fieldset>
            </x-card>

            <x-card title="Masa berlaku" icon="calendar" description="Tanggal mengunci validitas voucher. Hari & jam hanya dicetak sebagai info.">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-field label="Mulai berlaku" name="starts_at">
                        <input id="starts_at" name="starts_at" @aria('starts_at') type="datetime-local" required class="input"
                               value="{{ old('starts_at', $campaign->starts_at?->format('Y-m-d\TH:i')) }}">
                    </x-field>
                    <x-field label="Berlaku hingga (expired)" name="expires_at" optional hint="Default otomatis 2 minggu jika dikosongkan">
                        <input id="expires_at" name="expires_at" @aria('expires_at') type="datetime-local" class="input"
                               value="{{ old('expires_at', $campaign->expires_at?->format('Y-m-d\TH:i')) }}">
                    </x-field>
                </div>

                <fieldset class="mt-5">
                    <legend class="mb-2 flex items-baseline gap-2 text-sm font-semibold">Hari berlaku <span class="text-xs font-normal text-ink-muted">kosongkan = setiap hari</span></legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach (VoucherContent::DAYS as $day => $label)
                            <label class="cursor-pointer">
                                <input type="checkbox" name="valid_days[]" value="{{ $day }}" class="peer sr-only" @checked(in_array($day, $validDays, true))>
                                <span class="grid size-11 place-items-center rounded-xl border border-line-strong bg-surface text-sm font-bold text-ink-muted transition peer-checked:border-primary peer-checked:bg-primary peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-primary">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('valid_days') <p class="mt-1.5 text-sm font-medium text-danger">{{ $message }}</p> @enderror
                    @error('valid_days.*') <p class="mt-1.5 text-sm font-medium text-danger">{{ $message }}</p> @enderror
                </fieldset>

                <div class="mt-5 grid grid-cols-2 gap-5 sm:max-w-sm">
                    <x-field label="Jam mulai" name="valid_time_from" optional>
                        <input id="valid_time_from" name="valid_time_from" @aria('valid_time_from') type="time" value="{{ old('valid_time_from', $campaign->valid_time_from) }}" class="input">
                    </x-field>
                    <x-field label="Jam selesai" name="valid_time_until" optional>
                        <input id="valid_time_until" name="valid_time_until" @aria('valid_time_until') type="time" value="{{ old('valid_time_until', $campaign->valid_time_until) }}" class="input">
                    </x-field>
                </div>
            </x-card>

            <x-card title="Syarat & foto" icon="image">
                <div class="space-y-5">
                    <x-field label="Syarat & ketentuan" name="terms" optional>
                        <textarea id="terms" name="terms" @aria('terms') rows="3" maxlength="1000" class="input" placeholder="Tidak dapat diuangkan. Satu voucher per transaksi.">{{ old('terms', $campaign->terms) }}</textarea>
                    </x-field>

                    <x-field label="Foto produk" name="product_image" optional hint="PNG transparan disarankan. Mengisi slot foto produk di template (tampil di pratinjau setelah disimpan).">
                        <input id="product_image" name="product_image" @aria('product_image', true) type="file" accept="image/png,image/jpeg" @change="pickImage($event)"
                               class="block w-full text-sm text-ink-muted file:mr-3 file:rounded-lg file:border-0 file:bg-primary-soft file:px-4 file:py-2 file:text-sm file:font-semibold file:text-primary hover:file:bg-primary-line">
                        <div x-show="pickedImage" x-cloak class="mt-3 flex items-center gap-3 text-sm">
                            <img :src="pickedImage?.url" alt="Foto produk yang dipilih" class="h-24 rounded-xl border border-line bg-canvas object-contain">
                            <p class="text-ink-muted"><span class="font-semibold text-ink" x-text="pickedImage?.name"></span><br>Belum disimpan. Tampil di pratinjau voucher setelah campaign disimpan.</p>
                        </div>
                        @if ($campaign->product_image_path)
                            <img src="{{ route('campaigns.product-image', $campaign) }}" alt="Foto produk saat ini" class="mt-3 h-32 rounded-xl border border-line bg-canvas object-contain">
                        @endif
                    </x-field>
                </div>
            </x-card>

            <x-card title="Kode voucher" icon="code">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-field label="Prefix" name="code_prefix" hint="Huruf besar/angka dipisah '-', mis. WN-VM">
                        <input id="code_prefix" name="code_prefix" @aria('code_prefix', true) x-model="prefix" @input="prefix = prefix.toUpperCase()" required maxlength="20"
                               class="input font-mono uppercase">
                    </x-field>

                    <x-field label="Format" name="code_preset">
                        <select id="code_preset" name="code_preset" @aria('code_preset') x-model="preset" class="input">
                            @foreach ($presets as $key => $p)
                                <option value="{{ $key }}">{{ $p['label'] }} ({{ $p['pattern'] }})</option>
                            @endforeach
                            <option value="custom">Kustom…</option>
                        </select>
                    </x-field>
                </div>

                <div x-show="preset === 'custom'" x-cloak class="mt-5">
                    <x-field label="Pola kustom" name="code_pattern"
                             hint="Token: {PREFIX} {DDMM} {DATE:dmY} {SEQ:n} {RAND:n}. Wajib ada {SEQ} atau {RAND}.">
                        <input id="code_pattern" name="code_pattern" @aria('code_pattern', true) x-model="pattern" maxlength="60" class="input font-mono uppercase">
                    </x-field>
                </div>
                @error('code_pattern')
                    <p x-show="preset !== 'custom'" class="mt-2 text-sm font-medium text-danger">{{ $message }}</p>
                @enderror

                <div class="mt-5 rounded-xl bg-canvas px-4 py-3 text-sm">
                    Contoh: <span class="font-mono font-bold" x-text="example"></span>
                    <template x-if="seqCapacity">
                        <p class="mt-1 text-ink-muted">Kapasitas nomor urut: <span x-text="seqCapacity.toLocaleString('id-ID')"></span> kode per kombinasi prefix + tanggal.</p>
                    </template>
                    <template x-if="guessable">
                        <p class="mt-1 flex gap-1.5 font-medium text-amber-800">
                            <x-icon name="alert" class="mt-0.5 size-4" />
                            Pola tanpa {RAND} mudah ditebak: siapa pun yang memegang satu voucher bisa menebak kode berikutnya.
                            Pastikan kasir memverifikasi voucher fisik/gambar, bukan hanya kode.
                        </p>
                    </template>
                </div>
            </x-card>

            <x-card title="Outlet & batas" icon="store">
                <label class="flex items-center gap-2 text-sm font-medium">
                    <input type="hidden" name="applies_to_all_outlets" value="0">
                    <input type="checkbox" name="applies_to_all_outlets" value="1" x-model="allOutlets" class="checkbox">
                    Berlaku di semua outlet brand
                </label>

                <div x-show="!allOutlets" x-cloak class="mt-4">
                    <x-field label="Outlet berlaku" name="outlet_ids">
                        <div class="grid max-h-56 gap-1 overflow-y-auto rounded-xl border border-line-strong p-3 sm:grid-cols-2">
                            <template x-for="o in filteredOutlets" :key="o.id">
                                <label class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-canvas">
                                    <input type="checkbox" name="outlet_ids[]" :value="o.id"
                                           :checked="@js($selectedOutlets).includes(o.id)" class="checkbox">
                                    <span x-text="o.name"></span>
                                </label>
                            </template>
                            <p x-show="!filteredOutlets.length" class="text-sm text-ink-muted">Pilih brand dulu / brand belum punya outlet.</p>
                        </div>
                    </x-field>
                </div>

                <x-field label="Batas total voucher" name="generation_limit" optional hint="Kosongkan bila tanpa batas." class="mt-5">
                    <input id="generation_limit" name="generation_limit" @aria('generation_limit', true) type="number" min="1" value="{{ old('generation_limit', $campaign->generation_limit) }}"
                           class="input sm:w-48">
                </x-field>
            </x-card>

            <div class="flex gap-3">
                <x-button>Simpan campaign</x-button>
                <x-button variant="secondary" :href="$editing ? route('campaigns.show', $campaign) : route('campaigns.index')">Batal</x-button>
            </div>
        </div>

        {{-- Live preview --}}
        <aside class="xl:sticky xl:top-8">
            <x-card title="Pratinjau voucher" icon="eye">
                @include('campaigns.partials.preview-frame')

                <div x-show="unprintedInputs.length" x-cloak role="status"
                     class="mt-4 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950">
                    <p class="flex items-center gap-2 font-bold"><x-icon name="alert" class="size-4 text-amber-700" /> Belum tampil di gambar</p>
                    <p class="mt-1">Template ini belum punya tempat untuk:</p>
                    <ul class="mt-2 flex flex-wrap gap-1.5">
                        <template x-for="label in unprintedInputs" :key="label">
                            <li class="rounded-md bg-amber-100 px-2 py-0.5 text-xs font-semibold" x-text="label"></li>
                        </template>
                    </ul>
                    <p class="mt-2 text-xs">Isinya tetap tersimpan dan tampil di halaman detail voucher. Agar tercetak, pilih versi template yang punya slot ini, atau pasang slotnya di editor.</p>
                    <a x-show="selectedTemplate" :href="selectedTemplate?.editor_url" target="_blank" rel="noopener"
                       class="mt-3 inline-flex items-center gap-1.5 font-semibold text-accent hover:underline">
                        <x-icon name="template" class="size-4" /> Buka editor template
                    </a>
                </div>
            </x-card>
        </aside>
    </form>
</x-layouts.app>

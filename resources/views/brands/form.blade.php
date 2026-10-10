@php($editing = $brand->exists)

<x-layouts.app :title="$editing ? 'Ubah brand' : 'Tambah brand'">
    <x-page-header :title="$editing ? 'Ubah brand' : 'Tambah brand'" :back="route('brands.index')"
                   subtitle="Atur identitas brand dan upload master template desain untuk 4 jenis voucher." />

    <form method="POST" enctype="multipart/form-data"
          action="{{ $editing ? route('brands.update', $brand) : route('brands.store') }}"
          class="max-w-5xl space-y-6"
          x-data="{
              prefix: @js(old('code_prefix', $brand->code_prefix ?? '')),
              previewPhysical: @js($brand->templateImageUrl('physical')),
              previewElectronic: @js($brand->templateImageUrl('electronic')),
              previewNominal: @js($brand->templateImageUrl('nominal')),
              previewElectronicNominal: @js($brand->templateImageUrl('electronic_nominal')),
          }">
        @csrf
        @if ($editing) @method('PUT') @endif

        {{-- Informasi Dasar Brand --}}
        <x-card title="Informasi Brand" icon="tag" description="Identitas brand dan aturan prefix kode voucher.">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-field label="Nama" name="name">
                    <input id="name" name="name" @aria('name') value="{{ old('name', $brand->name) }}" required maxlength="100" class="input" placeholder="Contoh: Bebek Boedjang">
                </x-field>

                <x-field label="Slug" name="slug" optional hint="Kosongkan untuk dibuat otomatis dari nama.">
                    <input id="slug" name="slug" @aria('slug', true) value="{{ old('slug', $brand->slug) }}" maxlength="120" class="input" placeholder="bebek-boedjang">
                </x-field>
            </div>

            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <x-field label="Prefix kode voucher" name="code_prefix" hint="Huruf besar/angka, boleh dipisah tanda '-'. Maks 10 karakter.">
                    <input id="code_prefix" name="code_prefix" @aria('code_prefix', true) x-model="prefix" @input="prefix = prefix.toUpperCase()" required maxlength="10"
                           class="input font-mono uppercase" placeholder="BB">
                    <p class="mt-2 text-xs text-ink-muted">Contoh kode: <span class="font-mono font-bold text-ink" x-text="(prefix || 'PREFIX') + '-XXXXXXXX'"></span></p>
                </x-field>

                <div class="flex items-center pt-2 sm:pt-6">
                    <label class="flex items-center gap-2.5 text-sm font-semibold text-ink">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $brand->is_active)) class="checkbox">
                        Brand Aktif
                    </label>
                </div>
            </div>
        </x-card>

        {{-- Upload 4 Jenis Voucher --}}
        <x-card title="Upload 4 Jenis Voucher" icon="template"
                description="Upload gambar desain master untuk 4 jenis voucher. Otomatis sinkron menjadi template saat generate voucher.">
            <div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-4">
                {{-- 1. Voucher Fisik --}}
                <div class="flex flex-col justify-between rounded-2xl border border-line bg-surface p-4 shadow-2xs transition hover:border-primary/40 hover:shadow-xs">
                    <div>
                        <div class="flex items-center justify-between gap-2 border-b border-line pb-3">
                            <div class="flex items-center gap-2">
                                <span class="grid size-7 place-items-center rounded-xl bg-primary-soft text-xs font-black text-primary">1</span>
                                <h3 class="text-sm font-bold text-ink">Voucher Fisik</h3>
                            </div>
                            <span class="rounded-full px-2.5 py-0.5 text-2xs font-bold"
                                  :class="previewPhysical ? 'bg-emerald-100 text-emerald-800' : 'bg-canvas text-ink-muted'"
                                  x-text="previewPhysical ? 'Tersedia' : 'Kosong'"></span>
                        </div>

                        <div class="mt-3">
                            <div class="relative aspect-[16/9] w-full overflow-hidden rounded-xl border border-line bg-canvas">
                                <img x-show="previewPhysical" :src="previewPhysical" alt="Desain Voucher Fisik"
                                     class="size-full object-contain p-1" x-cloak>
                                <div x-show="!previewPhysical" class="flex size-full flex-col items-center justify-center p-3 text-center text-ink-muted">
                                    <x-icon name="ticket" class="size-8 stroke-1 text-ink-muted/40" />
                                    <p class="mt-1.5 text-xs font-medium">Belum ada desain fisik</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 space-y-2">
                        <label for="template_physical"
                               class="flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl border-2 border-dashed border-line-strong bg-canvas px-3 py-2 text-xs font-bold text-ink-muted transition hover:border-primary hover:bg-primary-soft hover:text-primary">
                            <x-icon name="image" class="size-4" />
                            <span x-text="previewPhysical ? 'Ganti Desain Fisik' : 'Upload Desain Fisik'"></span>
                        </label>
                        <input id="template_physical" name="template_physical" type="file" accept="image/jpeg,image/png" class="sr-only"
                               @change="if ($event.target.files[0]) previewPhysical = URL.createObjectURL($event.target.files[0])">
                        <p class="text-center text-2xs text-ink-muted">PNG / JPG, maks 10 MB</p>
                        @error('template_physical') <p class="text-xs font-medium text-danger">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- 2. e-Voucher Fisik --}}
                <div class="flex flex-col justify-between rounded-2xl border border-line bg-surface p-4 shadow-2xs transition hover:border-primary/40 hover:shadow-xs">
                    <div>
                        <div class="flex items-center justify-between gap-2 border-b border-line pb-3">
                            <div class="flex items-center gap-2">
                                <span class="grid size-7 place-items-center rounded-xl bg-primary-soft text-xs font-black text-primary">2</span>
                                <h3 class="text-sm font-bold text-ink">e-Voucher Fisik</h3>
                            </div>
                            <span class="rounded-full px-2.5 py-0.5 text-2xs font-bold"
                                  :class="previewElectronic ? 'bg-emerald-100 text-emerald-800' : 'bg-canvas text-ink-muted'"
                                  x-text="previewElectronic ? 'Tersedia' : 'Kosong'"></span>
                        </div>

                        <div class="mt-3">
                            <div class="relative aspect-[16/9] w-full overflow-hidden rounded-xl border border-line bg-canvas">
                                <img x-show="previewElectronic" :src="previewElectronic" alt="Desain e-Voucher Fisik"
                                     class="size-full object-contain p-1" x-cloak>
                                <div x-show="!previewElectronic" class="flex size-full flex-col items-center justify-center p-3 text-center text-ink-muted">
                                    <x-icon name="scan" class="size-8 stroke-1 text-ink-muted/40" />
                                    <p class="mt-1.5 text-xs font-medium">Belum ada desain e-voucher fisik</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 space-y-2">
                        <label for="template_electronic"
                               class="flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl border-2 border-dashed border-line-strong bg-canvas px-3 py-2 text-xs font-bold text-ink-muted transition hover:border-primary hover:bg-primary-soft hover:text-primary">
                            <x-icon name="image" class="size-4" />
                            <span x-text="previewElectronic ? 'Ganti Desain e-Voucher Fisik' : 'Upload Desain e-Voucher Fisik'"></span>
                        </label>
                        <input id="template_electronic" name="template_electronic" type="file" accept="image/jpeg,image/png" class="sr-only"
                               @change="if ($event.target.files[0]) previewElectronic = URL.createObjectURL($event.target.files[0])">
                        <p class="text-center text-2xs text-ink-muted">PNG / JPG, maks 10 MB</p>
                        @error('template_electronic') <p class="text-xs font-medium text-danger">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- 3. Voucher Nominal --}}
                <div class="flex flex-col justify-between rounded-2xl border border-line bg-surface p-4 shadow-2xs transition hover:border-primary/40 hover:shadow-xs">
                    <div>
                        <div class="flex items-center justify-between gap-2 border-b border-line pb-3">
                            <div class="flex items-center gap-2">
                                <span class="grid size-7 place-items-center rounded-xl bg-primary-soft text-xs font-black text-primary">3</span>
                                <h3 class="text-sm font-bold text-ink">Voucher Nominal</h3>
                            </div>
                            <span class="rounded-full px-2.5 py-0.5 text-2xs font-bold"
                                  :class="previewNominal ? 'bg-emerald-100 text-emerald-800' : 'bg-canvas text-ink-muted'"
                                  x-text="previewNominal ? 'Tersedia' : 'Kosong'"></span>
                        </div>

                        <div class="mt-3">
                            <div class="relative aspect-[16/9] w-full overflow-hidden rounded-xl border border-line bg-canvas">
                                <img x-show="previewNominal" :src="previewNominal" alt="Desain Voucher Nominal"
                                     class="size-full object-contain p-1" x-cloak>
                                <div x-show="!previewNominal" class="flex size-full flex-col items-center justify-center p-3 text-center text-ink-muted">
                                    <x-icon name="wallet" class="size-8 stroke-1 text-ink-muted/40" />
                                    <p class="mt-1.5 text-xs font-medium">Belum ada desain nominal</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 space-y-2">
                        <label for="template_nominal"
                               class="flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl border-2 border-dashed border-line-strong bg-canvas px-3 py-2 text-xs font-bold text-ink-muted transition hover:border-primary hover:bg-primary-soft hover:text-primary">
                            <x-icon name="image" class="size-4" />
                            <span x-text="previewNominal ? 'Ganti Desain Nominal' : 'Upload Desain Nominal'"></span>
                        </label>
                        <input id="template_nominal" name="template_nominal" type="file" accept="image/jpeg,image/png" class="sr-only"
                               @change="if ($event.target.files[0]) previewNominal = URL.createObjectURL($event.target.files[0])">
                        <p class="text-center text-2xs text-ink-muted">PNG / JPG, maks 10 MB</p>
                        @error('template_nominal') <p class="text-xs font-medium text-danger">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- 4. e-Voucher Nominal --}}
                <div class="flex flex-col justify-between rounded-2xl border border-line bg-surface p-4 shadow-2xs transition hover:border-primary/40 hover:shadow-xs">
                    <div>
                        <div class="flex items-center justify-between gap-2 border-b border-line pb-3">
                            <div class="flex items-center gap-2">
                                <span class="grid size-7 place-items-center rounded-xl bg-primary-soft text-xs font-black text-primary">4</span>
                                <h3 class="text-sm font-bold text-ink">e-Voucher Nominal</h3>
                            </div>
                            <span class="rounded-full px-2.5 py-0.5 text-2xs font-bold"
                                  :class="previewElectronicNominal ? 'bg-emerald-100 text-emerald-800' : 'bg-canvas text-ink-muted'"
                                  x-text="previewElectronicNominal ? 'Tersedia' : 'Kosong'"></span>
                        </div>

                        <div class="mt-3">
                            <div class="relative aspect-[16/9] w-full overflow-hidden rounded-xl border border-line bg-canvas">
                                <img x-show="previewElectronicNominal" :src="previewElectronicNominal" alt="Desain e-Voucher Nominal"
                                     class="size-full object-contain p-1" x-cloak>
                                <div x-show="!previewElectronicNominal" class="flex size-full flex-col items-center justify-center p-3 text-center text-ink-muted">
                                    <x-icon name="scan" class="size-8 stroke-1 text-ink-muted/40" />
                                    <p class="mt-1.5 text-xs font-medium">Belum ada desain e-voucher nominal</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 space-y-2">
                        <label for="template_electronic_nominal"
                               class="flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl border-2 border-dashed border-line-strong bg-canvas px-3 py-2 text-xs font-bold text-ink-muted transition hover:border-primary hover:bg-primary-soft hover:text-primary">
                            <x-icon name="image" class="size-4" />
                            <span x-text="previewElectronicNominal ? 'Ganti Desain e-Voucher Nominal' : 'Upload Desain e-Voucher Nominal'"></span>
                        </label>
                        <input id="template_electronic_nominal" name="template_electronic_nominal" type="file" accept="image/jpeg,image/png" class="sr-only"
                               @change="if ($event.target.files[0]) previewElectronicNominal = URL.createObjectURL($event.target.files[0])">
                        <p class="text-center text-2xs text-ink-muted">PNG / JPG, maks 10 MB</p>
                        @error('template_electronic_nominal') <p class="text-xs font-medium text-danger">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        </x-card>

        <div class="flex gap-3 pt-2">
            <x-button>Simpan</x-button>
            <x-button variant="secondary" :href="route('brands.index')">Batal</x-button>
        </div>
    </form>
</x-layouts.app>

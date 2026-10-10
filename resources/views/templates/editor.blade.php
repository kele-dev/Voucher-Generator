@php($small = 'input py-1.5')

<x-layouts.app :title="'Editor · '.$template->name" wide>
    <div x-data="templateEditor(@js([
            'imageUrl' => route('templates.image', $template),
            'width' => $template->image_width,
            'height' => $template->image_height,
            'fields' => $template->fields(),
            'fieldConfig' => $fieldConfig,
            'fieldGroups' => config('voucher.field_groups'),
            'fonts' => $fonts,
            'samples' => $samples,
            'canEdit' => $canEdit,
            'saveUrl' => route('templates.fields', $template),
            'previewUrl' => route('templates.preview', $template),
            'sampleUploadUrl' => route('templates.sample-image.upload', $template),
        ]))" @keydown.window="nudge($event)">

        <x-page-header :title="$template->name" :back="route('editor.index')"
                       :subtitle="$template->brand->name.' · versi '.$template->version.' · '.$template->image_width.'×'.$template->image_height.'px'">
            <span x-show="dirty" x-cloak class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-900">
                <span class="size-1.5 rounded-full bg-current"></span> Belum disimpan
            </span>
            <x-button type="button" variant="secondary" icon="refresh" x-on:click="preview()" x-bind:disabled="previewing || pauseSeconds > 0">
                <span x-text="pauseSeconds > 0 ? `Render ulang (${pauseSeconds} dtk)` : 'Render ulang'">Render ulang</span>
            </x-button>
            @if ($canEdit)
                <x-button type="button" x-on:click="save()" x-bind:disabled="busy || !dirty">Simpan posisi</x-button>
            @endif
        </x-page-header>

        <template x-if="errors.length">
            <ul class="mb-4 list-inside list-disc rounded-2xl border border-danger-line bg-danger-soft px-4 py-3 text-sm text-danger-hover" role="alert">
                <template x-for="error in errors"><li x-text="error"></li></template>
            </ul>
        </template>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
            {{-- Stage --}}
            <div class="min-w-0">
                <div class="card p-3 sm:p-4">
                    <div x-ref="stage" class="relative w-full select-none overflow-hidden rounded-xl bg-canvas"
                         :style="`aspect-ratio: ${width} / ${height}`" @pointerdown.self="selectedIndex = null">
                        <img :src="imageUrl" alt="" class="pointer-events-none absolute inset-0 h-full w-full" draggable="false">

                        <template x-for="(field, index) in fields" :key="field.key">
                            <div class="absolute" :style="boxStyle(field)"
                                 :class="selectedIndex === index ? 'outline-2 outline-primary outline' : 'outline outline-1 outline-dashed outline-primary/60'"
                                 @pointerdown="startDrag($event, index, 'move')"
                                 :title="label(field.key)">
                                <template x-if="field.type === 'image'">
                                    <div class="flex h-full w-full items-center justify-center bg-primary/10">
                                        <template x-if="samples.product_image">
                                            <img :src="samples.product_image" alt="" draggable="false"
                                                 class="pointer-events-none h-full w-full" :class="field.fit === 'cover' ? 'object-cover' : 'object-contain'">
                                        </template>
                                        <span x-show="!samples.product_image" class="text-xs font-semibold text-primary" x-text="label(field.key)"></span>
                                    </div>
                                </template>
                                <template x-if="field.type === 'text'">
                                    <div class="flex h-full w-full overflow-hidden" :style="textStyle(field)" x-text="sampleText(field)"></div>
                                </template>
                                <span x-show="canEdit && selectedIndex === index"
                                      class="absolute -right-2 -bottom-2 size-4 cursor-se-resize rounded-md border-2 border-white bg-primary shadow-soft"
                                      @pointerdown.stop="startDrag($event, index, 'resize')"></span>
                            </div>
                        </template>

                        {{-- Snap guides while dragging. --}}
                        <template x-for="(guide, i) in guides" :key="i">
                            <div class="pointer-events-none absolute bg-accent" :style="guideStyle(guide)" aria-hidden="true"></div>
                        </template>
                    </div>
                </div>
                <p class="mt-3 text-xs text-ink-muted">
                    Geser kotak untuk memindah, tarik sudut kanan bawah untuk ubah ukuran. Kotak menempel ke tepi/tengah gambar dan slot lain,
                    tahan <kbd class="rounded border border-line-strong px-1">Alt</kbd> untuk menaruh bebas. Panah keyboard geser 1px (Shift = 10px).
                    Tampilan di sini perkiraan; hasil akhir ada di <em>Hasil render</em> di bawah.
                </p>

                <x-card title="Hasil render" icon="eye" description="Renderer yang sama dengan voucher asli, dari posisi saat ini (belum disimpan pun ikut)." class="mt-6">
                    <x-slot:actions>
                        <label class="inline-flex items-center gap-2 text-sm font-medium">
                            <input type="checkbox" x-model="autoPreview" class="checkbox"> Otomatis
                        </label>
                    </x-slot:actions>
                    <div class="relative overflow-hidden rounded-xl border border-line bg-canvas">
                        <a x-show="previewSrc" :href="previewSrc" target="_blank" rel="noopener" title="Buka di tab baru" x-cloak>
                            <img :src="previewSrc" alt="Hasil render template" class="block w-full">
                        </a>
                        <p x-show="!previewSrc && !previewing" class="grid aspect-[2/1] place-items-center px-4 text-center text-sm text-ink-muted"
                           x-text="fields.length ? (autoPreview ? 'Menunggu perubahan…' : 'Klik Render ulang untuk melihat hasil.') : 'Tambahkan field untuk melihat hasil render.'"></p>
                        <div x-show="previewing" x-cloak class="absolute inset-0 grid place-items-center bg-canvas/60">
                            <span class="rounded-full bg-surface px-3 py-1 text-xs font-semibold shadow-soft">Merender…</span>
                        </div>
                    </div>
                    {{-- Preview problems live here, apart from save/upload errors above. --}}
                    <ul x-show="previewErrors.length" x-cloak role="status"
                        class="mt-3 list-inside list-disc rounded-xl border border-amber-300 bg-amber-50 px-4 py-2 text-sm text-amber-950">
                        <template x-for="error in previewErrors"><li x-text="error"></li></template>
                    </ul>
                </x-card>
            </div>

            {{-- Panel --}}
            <aside class="space-y-5">
                @if ($canEdit)
                    <x-card title="Tambah field" description="Klik untuk menaruh slot di template.">
                        <div class="space-y-4">
                            <template x-for="group in unusedGroups" :key="group.key">
                                <div>
                                    <p class="mb-2 text-[11px] font-bold tracking-[0.12em] text-ink-muted uppercase" x-text="group.label"></p>
                                    <div class="flex flex-wrap gap-2">
                                        <template x-for="key in group.keys" :key="key">
                                            <button type="button" @click="addField(key)"
                                                    class="inline-flex min-h-11 items-center gap-1.5 rounded-lg border border-line-strong bg-surface px-3 text-xs font-semibold text-ink-muted transition hover:border-primary hover:bg-primary-soft hover:text-primary">
                                                <x-icon name="plus" class="size-3.5" /><span x-text="label(key)"></span>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </template>
                            <p x-show="!unusedKeys.length" class="text-sm text-ink-muted">Semua field sudah dipasang.</p>
                        </div>
                    </x-card>
                @endif

                <x-card title="Field terpasang">
                    <ul class="space-y-1 text-sm">
                        <template x-for="(field, index) in fields" :key="field.key">
                            <li>
                                <button type="button" class="flex min-h-10 w-full items-center gap-2 rounded-lg px-3 text-left transition hover:bg-primary-soft"
                                        :class="selectedIndex === index ? 'bg-primary-soft font-semibold text-primary' : 'text-ink'"
                                        @click="selectedIndex = index">
                                    <span class="size-2 rounded-full" :class="field.type === 'image' ? 'bg-accent' : 'bg-primary'"></span>
                                    <span x-text="label(field.key)"></span>
                                </button>
                            </li>
                        </template>
                        <li x-show="!fields.length" class="px-3 text-ink-muted">Belum ada field.</li>
                    </ul>
                </x-card>

                <template x-if="selected">
                    <div class="card space-y-4 p-5 text-sm">
                        <div class="flex items-center justify-between">
                            <h2 class="text-base font-bold" x-text="label(selected.key)"></h2>
                            @if ($canEdit)
                                <x-button type="button" variant="danger" size="sm" icon="trash" x-on:click="removeSelected()">Hapus</x-button>
                            @endif
                        </div>

                        <fieldset :disabled="!canEdit" class="space-y-4">
                            <div class="grid grid-cols-2 gap-2">
                                <template x-for="prop in ['x', 'y', 'width', 'height']" :key="prop">
                                    <label class="block">
                                        <span class="mb-1 block text-xs font-semibold text-ink-muted" x-text="prop + ' (px)'"></span>
                                        <input type="number" min="0" class="{{ $small }}"
                                               :value="px(selected, prop)" @change="setPx(selected, prop, $event.target.value)">
                                    </label>
                                </template>
                            </div>

                            <template x-if="selected.type === 'image'">
                                <div class="space-y-3">
                                    <label class="block">
                                        <span class="mb-1 block text-xs font-semibold text-ink-muted">Penyesuaian</span>
                                        <select x-model="selected.fit" @change="dirty = true" class="{{ $small }}">
                                            <option value="contain">Contain (utuh, transparan dipertahankan)</option>
                                            <option value="cover">Cover (penuhi kotak, terpotong)</option>
                                        </select>
                                    </label>
                                    @if ($canEdit)
                                        <label class="block">
                                            <span class="mb-1 block text-xs font-semibold text-ink-muted">Gambar contoh untuk preview (PNG transparan disarankan)</span>
                                            <input type="file" accept="image/png,image/jpeg" @change="uploadSample($event)" :disabled="uploadingSample"
                                                   class="block w-full text-xs file:mr-2 file:rounded-lg file:border-0 file:bg-primary-soft file:px-3 file:py-1.5 file:font-semibold file:text-primary disabled:opacity-60">
                                            <span x-show="uploadingSample" x-cloak class="mt-1 block text-xs font-semibold text-ink-muted" role="status">Mengunggah gambar…</span>
                                        </label>
                                    @endif
                                </div>
                            </template>

                            <template x-if="selected.type === 'text'">
                                <div class="space-y-4" @input="dirty = true" @change="dirty = true">
                                    <div class="grid grid-cols-2 gap-2">
                                        <label class="block">
                                            <span class="mb-1 block text-xs font-semibold text-ink-muted">Font</span>
                                            <select x-model="selected.font_family" class="{{ $small }}">
                                                <template x-for="(font, key) in fonts" :key="key">
                                                    <option :value="key" x-text="font.label"></option>
                                                </template>
                                            </select>
                                        </label>
                                        <label class="block">
                                            <span class="mb-1 block text-xs font-semibold text-ink-muted">Ketebalan</span>
                                            <select x-model="selected.font_weight" class="{{ $small }}">
                                                <template x-for="weight in fonts[selected.font_family]?.weights ?? []" :key="weight">
                                                    <option :value="weight" x-text="weight"></option>
                                                </template>
                                            </select>
                                        </label>
                                        <label class="block">
                                            <span class="mb-1 block text-xs font-semibold text-ink-muted">Ukuran (px)</span>
                                            <input type="number" min="6" max="400" x-model.number="selected.font_size" class="{{ $small }}">
                                        </label>
                                        <label class="block">
                                            <span class="mb-1 block text-xs font-semibold text-ink-muted">Warna</span>
                                            <input type="color" x-model="selected.color" class="h-9 w-full cursor-pointer rounded-lg border border-line-strong">
                                        </label>
                                        <label class="block">
                                            <span class="mb-1 block text-xs font-semibold text-ink-muted">Rata horizontal</span>
                                            <select x-model="selected.align" class="{{ $small }}">
                                                <option value="left">Kiri</option><option value="center">Tengah</option><option value="right">Kanan</option>
                                            </select>
                                        </label>
                                        <label class="block">
                                            <span class="mb-1 block text-xs font-semibold text-ink-muted">Rata vertikal</span>
                                            <select x-model="selected.vertical_align" class="{{ $small }}">
                                                <option value="top">Atas</option><option value="middle">Tengah</option><option value="bottom">Bawah</option>
                                            </select>
                                        </label>
                                    </div>

                                    <template x-if="fieldConfig[selected.key]?.type === 'date'">
                                        <label class="block">
                                            <span class="mb-1 block text-xs font-semibold text-ink-muted">Format tanggal (PHP, mis. <code>j F Y</code>)</span>
                                            <input x-model="selected.date_format" class="{{ $small }} font-mono">
                                        </label>
                                    </template>

                                    <div class="flex flex-wrap gap-x-4 gap-y-2">
                                        <label class="flex items-center gap-2"><input type="checkbox" x-model="selected.uppercase" class="checkbox"> Huruf besar</label>
                                        <label class="flex items-center gap-2"><input type="checkbox" x-model="selected.wrap" class="checkbox"> Bungkus baris</label>
                                        <label class="flex items-center gap-2"><input type="checkbox" x-model="selected.cover_enabled" class="checkbox"> Cover box</label>
                                    </div>

                                    <template x-if="selected.cover_enabled">
                                        <div class="grid grid-cols-2 gap-2">
                                            <label class="block">
                                                <span class="mb-1 block text-xs font-semibold text-ink-muted">Warna cover</span>
                                                <input type="color" x-model="selected.cover_color" class="h-9 w-full cursor-pointer rounded-lg border border-line-strong">
                                            </label>
                                            <label class="block">
                                                <span class="mb-1 block text-xs font-semibold text-ink-muted">Inset (px)</span>
                                                <input type="number" min="0" max="50" x-model.number="selected.cover_inset" class="{{ $small }}">
                                            </label>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </fieldset>
                    </div>
                </template>
            </aside>
        </div>
    </div>
</x-layouts.app>

@php($editing = $template->exists)

<x-layouts.app :title="$editing ? 'Ubah template' : 'Upload template'">
    <x-page-header :title="$editing ? 'Ubah template' : 'Upload template'" :back="route('templates.index')"
                   subtitle="Gambar voucher kosong. Posisi field diatur di editor setelah disimpan." />

    <form method="POST" enctype="multipart/form-data"
          action="{{ $editing ? route('templates.update', $template) : route('templates.store') }}"
          class="card max-w-2xl space-y-5 p-6"
          x-data="{ preview: @js($editing ? route('templates.image', $template) : null) }">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="grid gap-5 sm:grid-cols-2">
            <x-field label="Brand" name="brand_id">
                <select id="brand_id" name="brand_id" @aria('brand_id') required class="input">
                    <option value="">Pilih brand</option>
                    @foreach ($brands as $brand)
                        <option value="{{ $brand->id }}" @selected(old('brand_id', $template->brand_id) == $brand->id)>{{ $brand->name }}</option>
                    @endforeach
                </select>
            </x-field>

            <x-field label="Nama template" name="name">
                <input id="name" name="name" @aria('name') value="{{ old('name', $template->name) }}" required maxlength="100" class="input">
            </x-field>
        </div>

        <x-field label="Gambar template" name="image"
                 :hint="'JPG/PNG, maks '.(config('voucher.templates.max_upload_kb') / 1024).' MB.'.($editing ? ' Kosongkan bila tidak diganti.' : '')">
            <label for="image" class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-line-strong bg-canvas p-6 text-center text-sm text-ink-muted transition hover:border-primary hover:bg-primary-soft">
                <x-icon name="image" class="size-8 text-primary" />
                <span class="font-semibold text-ink">Pilih gambar</span>
            </label>
            <input id="image" name="image" @aria('image', true) type="file" accept="image/jpeg,image/png" @required(! $editing) class="sr-only"
                   @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : preview">
            <img x-show="preview" :src="preview" alt="Pratinjau gambar template" class="mt-3 w-full rounded-xl border border-line">
        </x-field>

        <label class="flex items-center gap-2 text-sm font-medium">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $template->is_active)) class="checkbox">
            Aktif (bisa dipilih campaign)
        </label>

        <div class="flex gap-3">
            <x-button>Simpan</x-button>
            <x-button variant="secondary" :href="route('templates.index')">Batal</x-button>
        </div>
    </form>
</x-layouts.app>

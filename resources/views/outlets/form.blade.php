@php($editing = $outlet->exists)

<x-layouts.app :title="$editing ? 'Ubah outlet' : 'Tambah outlet'">
    <x-page-header :title="$editing ? 'Ubah outlet' : 'Tambah outlet'" :back="route('outlets.index')" />

    <form method="POST" action="{{ $editing ? route('outlets.update', $outlet) : route('outlets.store') }}"
          class="card max-w-xl space-y-5 p-6">
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-field label="Brand" name="brand_id">
            <select id="brand_id" name="brand_id" @aria('brand_id') required class="input">
                <option value="">Pilih brand</option>
                @foreach ($brands as $brand)
                    <option value="{{ $brand->id }}" @selected(old('brand_id', $outlet->brand_id) == $brand->id)>{{ $brand->name }}</option>
                @endforeach
            </select>
        </x-field>

        <x-field label="Nama" name="name">
            <input id="name" name="name" @aria('name') value="{{ old('name', $outlet->name) }}" required maxlength="100" class="input">
        </x-field>

        <div class="grid gap-5 sm:grid-cols-2">
            <x-field label="Kode outlet" name="code" hint="Unik. Huruf besar, angka, tanda '-'.">
                <input id="code" name="code" @aria('code', true) value="{{ old('code', $outlet->code) }}" required maxlength="32" class="input font-mono uppercase">
            </x-field>

            <x-field label="Kota" name="city" optional>
                <input id="city" name="city" @aria('city') value="{{ old('city', $outlet->city) }}" maxlength="100" class="input">
            </x-field>
        </div>

        <label class="flex items-center gap-2 text-sm font-medium">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $outlet->is_active)) class="checkbox">
            Aktif
        </label>

        <div class="flex gap-3">
            <x-button>Simpan</x-button>
            <x-button variant="secondary" :href="route('outlets.index')">Batal</x-button>
        </div>
    </form>
</x-layouts.app>

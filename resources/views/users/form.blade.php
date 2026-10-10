@php
    $editing = $user->exists;
    $selectedOutlets = array_map('intval', old('outlet_ids', $user->outlets->pluck('id')->all()));
@endphp

<x-layouts.app :title="$editing ? 'Ubah user' : 'Tambah user'">
    <x-page-header :title="$editing ? 'Ubah user' : 'Tambah user'" :back="route('users.index')" />

    <form method="POST" action="{{ $editing ? route('users.update', $user) : route('users.store') }}"
          class="card max-w-xl space-y-5 p-6"
          x-data="{ role: @js(old('role', $user->role?->value)) }">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="grid gap-5 sm:grid-cols-2">
            <x-field label="Nama" name="name">
                <input id="name" name="name" @aria('name') value="{{ old('name', $user->name) }}" required maxlength="100" class="input">
            </x-field>

            <x-field label="Email" name="email">
                <input id="email" name="email" @aria('email') type="email" value="{{ old('email', $user->email) }}" required class="input">
            </x-field>
        </div>

        <x-field label="Role" name="role">
            <select id="role" name="role" @aria('role') x-model="role" required class="input">
                @foreach ($roles as $role)
                    <option value="{{ $role->value }}">{{ $role->label() }}</option>
                @endforeach
            </select>
        </x-field>

        <div x-show="role === 'outlet_staff'" x-cloak>
            <x-field label="Outlet yang ditugaskan" name="outlet_ids">
                <div class="max-h-56 space-y-1 overflow-y-auto rounded-xl border border-line-strong p-3">
                    @forelse ($outlets as $outlet)
                        <label class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-canvas">
                            <input type="checkbox" name="outlet_ids[]" value="{{ $outlet->id }}" @checked(in_array($outlet->id, $selectedOutlets, true))
                                   class="checkbox">
                            {{ $outlet->name }} <span class="text-ink-muted">· {{ $outlet->brand->name }}</span>
                        </label>
                    @empty
                        <p class="text-sm text-ink-muted">Belum ada outlet.</p>
                    @endforelse
                </div>
            </x-field>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <x-field label="Password" name="password" :hint="$editing ? 'Kosongkan bila tidak diganti.' : null">
                <input id="password" name="password" @aria('password', $editing) type="password" autocomplete="new-password" @required(! $editing) class="input">
            </x-field>

            <x-field label="Konfirmasi password" name="password_confirmation">
                <input id="password_confirmation" name="password_confirmation" @aria('password_confirmation') type="password" autocomplete="new-password" class="input">
            </x-field>
        </div>

        <div>
            <label class="flex items-center gap-2 text-sm font-medium">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active)) class="checkbox">
                Aktif
            </label>
            @error('is_active') <p class="mt-1 text-sm font-medium text-danger">{{ $message }}</p> @enderror
        </div>

        <div class="flex gap-3">
            <x-button>Simpan</x-button>
            <x-button variant="secondary" :href="route('users.index')">Batal</x-button>
        </div>
    </form>
</x-layouts.app>

<x-layouts.app title="User">
    <x-page-header title="User" subtitle="Akun internal dan penugasan outlet.">
        <x-button icon="plus" :href="route('users.create')">Tambah user</x-button>
    </x-page-header>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Role</th>
                    <th>Outlet</th>
                    <th>Status</th>
                    <th><span class="sr-only">Aksi</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($users as $user)
                    <tr class="group">
                        <td>
                            <div class="flex items-center gap-3">
                                <span class="grid size-9 shrink-0 place-items-center rounded-xl border border-primary/20 bg-primary-soft text-sm font-bold text-primary">
                                    {{ Str::upper(Str::substr($user->name, 0, 1)) }}
                                </span>
                                <div>
                                    <div class="font-bold text-ink group-hover:text-primary transition-colors">{{ $user->name }}</div>
                                    <div class="text-xs text-ink-muted">{{ $user->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="inline-flex items-center rounded-lg bg-canvas px-2.5 py-1 text-xs font-semibold text-ink border border-line">
                                {{ $user->role->label() }}
                            </span>
                        </td>
                        <td class="text-xs text-ink-muted">
                            {{ $user->role === App\Enums\Role::OutletStaff ? $user->outlets->pluck('name')->join(', ') : 'Semua Outlet' }}
                        </td>
                        <td><x-status-badge :active="$user->is_active" /></td>
                        <td class="text-right">
                            <div class="flex justify-end gap-2">
                                <x-button variant="secondary" size="sm" icon="pencil" :href="route('users.edit', $user)">Ubah</x-button>
                                @can('delete', $user)
                                    <x-delete-button :action="route('users.destroy', $user)" :confirm="'Hapus user '.$user->name.'?'" />
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $users->links() }}</div>
</x-layouts.app>

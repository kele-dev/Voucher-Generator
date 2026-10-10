@props(['title' => null, 'wide' => false])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    {{-- Resolve the theme before first paint so the page never flashes light. --}}
    <script>
        (function () {
            try {
                var stored = localStorage.getItem('theme');
                var dark = stored ? stored === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
                document.documentElement.classList.toggle('dark', dark);
            } catch (e) {}
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-canvas font-sans text-ink antialiased">
@auth
    @php
        $user = auth()->user();
        $groups = [
            'Voucher' => array_values(array_filter([
                ['label' => 'Editor', 'route' => 'editor.index', 'match' => ['editor.*', 'templates.editor'], 'icon' => 'pencil'],
                ['label' => 'Campaign', 'route' => 'campaigns.index', 'match' => 'campaigns.*', 'icon' => 'campaign'],
                ['label' => 'Voucher', 'route' => 'vouchers.index', 'match' => 'vouchers.*', 'icon' => 'ticket'],
            ])),
            'Laporan' => array_values(array_filter([
                ['label' => 'Dashboard', 'route' => 'dashboard', 'match' => 'dashboard', 'icon' => 'dashboard'],
                $user->can('viewActivity') ? ['label' => 'Aktivitas', 'route' => 'activity.index', 'match' => 'activity.*', 'icon' => 'activity'] : null,
            ])),
            'Master' => array_values(array_filter([
                ['label' => 'Brand', 'route' => 'brands.index', 'match' => 'brands.*', 'icon' => 'tag'],
                $user->can('viewAny', App\Models\User::class) ? ['label' => 'User', 'route' => 'users.index', 'match' => 'users.*', 'icon' => 'users'] : null,
            ])),
        ];
    @endphp

    {{-- Below lg the sidebar is an off-canvas drawer: while closed it is inert
         (no tab stops, hidden from screen readers); closing returns focus to
         the menu button. --}}
    <div x-data="{
            drawer: false,
            dark: document.documentElement.classList.contains('dark'),
            desktop: window.matchMedia('(min-width: 1024px)').matches,
            collapsed: (() => { try { return localStorage.getItem('sidebar-collapsed') === '1' } catch (e) { return false } })(),
            init() {
                const mq = window.matchMedia('(min-width: 1024px)');
                // Growing to desktop closes the drawer without moving focus:
                // the sidebar stays visible there, so focus inside it is still visible.
                mq.addEventListener('change', (e) => { this.desktop = e.matches; if (e.matches) this.drawer = false; });
            },
            toggle() { this.collapsed = ! this.collapsed; try { localStorage.setItem('sidebar-collapsed', this.collapsed ? '1' : '0') } catch (e) {} },
            toggleTheme() {
                this.dark = ! this.dark;
                document.documentElement.classList.toggle('dark', this.dark);
                try { localStorage.setItem('theme', this.dark ? 'dark' : 'light') } catch (e) {}
            },
            openDrawer() { this.drawer = true; this.$nextTick(() => this.$refs.closeDrawer?.focus()); },
            closeDrawer() { if (! this.drawer) return; this.drawer = false; this.$nextTick(() => this.$refs.menuButton?.focus()); },
         }"
         @keydown.escape.window="closeDrawer()"
         class="flex min-h-screen">

        {{-- Mobile drawer backdrop --}}
        <div x-show="drawer" x-cloak x-transition.opacity @click="closeDrawer()" class="fixed inset-0 z-30 bg-black/50 lg:hidden"></div>

        <aside id="sidebar"
               x-effect="$el.inert = ! drawer && ! desktop"
               :aria-modal="drawer && ! desktop ? 'true' : null"
               :role="drawer && ! desktop ? 'dialog' : null"
               aria-label="Menu"
               :class="[drawer && 'translate-x-0!', collapsed ? 'lg:w-[76px]' : 'lg:w-64']"
               class="fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full flex-col border-r border-line bg-sidebar transition-[transform,width] duration-200 lg:sticky lg:top-0 lg:h-screen lg:translate-x-0">

            <div class="flex h-16 items-center gap-3 border-b border-line px-4">
                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-primary font-bold text-white">B</span>
                <div class="min-w-0" :class="collapsed && 'lg:hidden'">
                    <p class="truncate font-sans text-base leading-tight font-extrabold tracking-tight text-ink">Boedjang</p>
                    <p class="text-[10px] font-semibold tracking-wider text-ink-muted uppercase">Voucher Studio</p>
                </div>
                <button type="button" x-ref="closeDrawer" @click="closeDrawer()" class="ml-auto rounded-lg p-2 text-ink-muted hover:bg-primary-soft hover:text-primary lg:hidden" aria-label="Tutup menu">
                    <x-icon name="x" />
                </button>
            </div>

            <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-5" aria-label="Navigasi utama">
                @foreach ($groups as $group => $items)
                    @continue(empty($items))
                    <div>
                        <p class="mb-2 px-3 text-[11px] font-bold tracking-[0.14em] text-ink-muted uppercase" :class="collapsed && 'lg:sr-only'">{{ $group }}</p>
                        <ul class="space-y-1">
                            @foreach ($items as $item)
                                @php $active = request()->routeIs($item['match']); @endphp
                                <li>
                                    <a href="{{ route($item['route']) }}" title="{{ $item['label'] }}"
                                       @if ($active) aria-current="page" @endif
                                       @class([
                                           'group flex min-h-11 items-center gap-3 rounded-xl px-3.5 text-sm transition-colors duration-150',
                                           'bg-primary text-white font-bold' => $active,
                                           'font-semibold text-ink-muted hover:bg-primary-soft/70 hover:text-primary' => ! $active,
                                       ])
                                       :class="collapsed && 'lg:justify-center lg:px-0'">
                                        <span @class([
                                            'grid size-7.5 place-items-center rounded-lg transition-colors',
                                            'bg-white/15 text-white' => $active,
                                            'bg-canvas text-ink-muted group-hover:bg-primary-soft group-hover:text-primary' => ! $active,
                                        ])>
                                            <x-icon :name="$item['icon']" class="size-4" />
                                        </span>
                                        <span :class="collapsed && 'lg:sr-only'">{{ $item['label'] }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </nav>

            <div class="border-t border-line/80 p-3">
                <div class="flex items-center gap-3 rounded-xl px-2 py-2" :class="collapsed && 'lg:justify-center'">
                    <div class="shrink-0">
                        <span class="grid size-9.5 place-items-center rounded-full bg-primary text-sm font-bold text-white">{{ Str::upper(Str::substr($user->name, 0, 1)) }}</span>
                    </div>
                    <div class="min-w-0 flex-1" :class="collapsed && 'lg:hidden'">
                        <p class="truncate text-sm font-bold text-ink">{{ $user->name }}</p>
                        <p class="truncate text-xs font-medium text-ink-muted">{{ $user->role->label() }}</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" :class="collapsed && 'lg:hidden'">
                        @csrf
                        <button class="rounded-lg p-2 text-ink-muted transition-colors hover:bg-danger-soft hover:text-danger" title="Keluar" aria-label="Keluar">
                            <x-icon name="logout" class="size-[18px]" />
                        </button>
                    </form>
                </div>
                <button type="button" @click="toggle()" :aria-expanded="(! collapsed).toString()" aria-controls="sidebar"
                        class="mt-1 hidden w-full items-center gap-2.5 rounded-lg px-3 py-2 text-xs font-semibold text-ink-muted transition-colors hover:bg-primary-soft/60 hover:text-ink lg:flex"
                        :class="collapsed && 'justify-center px-0'">
                    <x-icon name="collapse" class="size-4" x-show="! collapsed" />
                    <x-icon name="expand" class="size-4" x-show="collapsed" x-cloak />
                    <span x-show="! collapsed">Ciutkan menu</span>
                </button>
            </div>
        </aside>

        {{-- Focus trap for the open mobile drawer: everything behind it is inert. --}}
        <div class="flex min-w-0 flex-1 flex-col" x-effect="$el.inert = drawer && ! desktop">
            {{-- Mobile top bar --}}
            <header class="sticky top-0 z-20 flex h-14 items-center justify-between border-b border-line bg-surface/95 px-4 backdrop-blur lg:hidden">
                <div class="flex items-center gap-2">
                    <button type="button" x-ref="menuButton" @click="openDrawer()" class="rounded-lg p-2 text-ink hover:bg-primary-soft" aria-label="Buka menu" aria-controls="sidebar" :aria-expanded="drawer.toString()">
                        <x-icon name="menu" />
                    </button>
                    <span class="font-sans text-lg font-extrabold text-ink">Boedjang</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <button type="button" @click="toggleTheme()" class="rounded-lg p-2 text-ink-muted hover:bg-primary-soft hover:text-primary" :aria-label="dark ? 'Mode terang' : 'Mode gelap'">
                        <x-icon name="sun" class="size-5" x-show="dark" x-cloak />
                        <x-icon name="moon" class="size-5" x-show="! dark" />
                    </button>
                    <span class="grid size-8 place-items-center rounded-full bg-primary text-xs font-bold text-white">{{ Str::upper(Str::substr($user->name, 0, 1)) }}</span>
                </div>
            </header>

            {{-- Desktop top bar --}}
            <header class="sticky top-0 z-20 hidden h-16 items-center justify-between border-b border-line bg-surface/85 px-8 backdrop-blur-md lg:flex">
                <p class="text-sm font-medium text-ink-muted">{{ now()->translatedFormat('l, j F Y') }}</p>
                <div class="flex items-center gap-2">
                    <button type="button" @click="toggleTheme()" class="rounded-lg p-2 text-ink-muted transition-colors hover:bg-primary-soft hover:text-primary" :aria-label="dark ? 'Mode terang' : 'Mode gelap'">
                        <x-icon name="sun" class="size-5" x-show="dark" x-cloak />
                        <x-icon name="moon" class="size-5" x-show="! dark" />
                    </button>
                    @can('create', App\Models\Campaign::class)
                        <a href="{{ route('campaigns.create') }}" class="inline-flex items-center gap-1.5 rounded-xl bg-primary px-3.5 py-2 text-xs font-bold text-white transition-colors hover:bg-primary-hover">
                            <x-icon name="plus" class="size-3.5" />
                            <span>Buat Voucher</span>
                        </a>
                    @endcan
                    <span class="inline-flex items-center gap-1.5 rounded-xl border border-line bg-canvas px-3 py-2 text-xs font-bold text-ink-muted">
                        <x-icon name="users" class="size-3.5 text-primary" />
                        <span>{{ $user->role->label() }}</span>
                    </span>
                </div>
            </header>

            <main @class(['mx-auto w-full flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8', 'max-w-6xl' => ! $wide, 'max-w-[1500px]' => $wide])>
                @include('components.layouts.partials.alerts')
                {{ $slot }}
            </main>
        </div>
    </div>
@else
    <main class="mx-auto w-full max-w-6xl px-4 py-8">
        @include('components.layouts.partials.alerts')
        {{ $slot }}
    </main>
@endauth

{{-- Outside the drawer/content wrappers so neither is ever made inert with them. --}}
<x-toast-stack />
<x-confirm-dialog />
</body>
</html>

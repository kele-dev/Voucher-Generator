<x-layouts.app title="Masuk">
    <div class="relative mx-auto flex min-h-[calc(100vh-8rem)] max-w-6xl items-center justify-center py-6">
        <div class="relative grid w-full items-center gap-12 lg:grid-cols-12 lg:gap-8">
            {{-- Left column: brand and value --}}
            <div class="hidden space-y-8 lg:col-span-7 lg:block lg:pr-6">
                <div>
                    <h1 class="font-display text-4xl leading-[1.18] font-bold tracking-tight text-ink xl:text-5xl">
                        Voucher makan resto,<br>
                        <span class="text-primary">dirancang &amp; dicetak</span> sekali klik.
                    </h1>

                    <p class="mt-4 max-w-lg text-base leading-relaxed text-ink-muted">
                        Atur template visual, menu gratis, dan masa berlaku secara terpusat, lalu generate ratusan hingga ribuan voucher unik siap bagi untuk setiap cabang outlet Boedjang Group.
                    </p>
                </div>

                <div class="max-w-lg space-y-3">
                    <div class="flex items-start gap-3.5 rounded-2xl border border-line bg-surface p-4">
                        <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-primary-soft text-primary">
                            <x-icon name="zap" class="size-5" />
                        </span>
                        <div>
                            <p class="text-sm font-bold text-ink">Batch Generator Cepat</p>
                            <p class="text-xs leading-relaxed text-ink-muted">Penerbitan kode unik acak atau berurutan secara otomatis dengan ekspor file ZIP siap cetak.</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3.5 rounded-2xl border border-line bg-surface p-4">
                        <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-primary-soft text-primary">
                            <x-icon name="template" class="size-5" />
                        </span>
                        <div>
                            <p class="text-sm font-bold text-ink">Editor Visual Template</p>
                            <p class="text-xs leading-relaxed text-ink-muted">Atur posisi porsi menu gratis, masa berlaku, dan detail voucher langsung di atas desain grafis.</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3.5 rounded-2xl border border-line bg-surface p-4">
                        <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-primary-soft text-primary">
                            <x-icon name="shield-check" class="size-5" />
                        </span>
                        <div>
                            <p class="text-sm font-bold text-ink">Keamanan &amp; Kontrol Multi-Outlet</p>
                            <p class="text-xs leading-relaxed text-ink-muted">Batasi jangkauan voucher ke cabang tertentu dengan pencatatan audit log lengkap.</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Right column: login card --}}
            <div class="w-full lg:col-span-5">
                <div class="card mx-auto w-full max-w-md p-7 shadow-lift sm:p-9">
                    <div class="flex items-center gap-3">
                        <span class="grid size-12 shrink-0 place-items-center rounded-xl bg-primary text-lg font-black text-white">B</span>
                        <div>
                            <h2 class="font-display text-2xl font-bold tracking-tight text-ink">{{ config('app.name') }}</h2>
                            <p class="text-xs font-medium text-ink-muted">Sistem Akses Internal</p>
                        </div>
                    </div>

                    <div class="mt-6 border-t border-line/70 pt-6">
                        <h3 class="text-base font-bold text-ink">Masuk ke Akun</h3>
                        <p class="mt-0.5 text-xs text-ink-muted">Masukkan email dan kata sandi yang terdaftar.</p>
                    </div>

                    <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4.5" x-data="{ showPassword: false }">
                        @csrf

                        <x-field label="Email Akun" name="email">
                            <div class="relative">
                                <input id="email" name="email" @aria('email') type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                                       placeholder="nama@boedjang.com" class="input">
                            </div>
                        </x-field>

                        <x-field label="Password" name="password">
                            <div class="relative">
                                <input id="password" name="password" @aria('password') :type="showPassword ? 'text' : 'password'" required autocomplete="current-password"
                                       placeholder="Masukkan kata sandi" class="input pr-10">
                                <button type="button" @click="showPassword = !showPassword"
                                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-ink-muted hover:text-ink"
                                        tabindex="-1" :aria-label="showPassword ? 'Sembunyikan password' : 'Lihat password'">
                                    <x-icon name="eye" class="size-4" />
                                </button>
                            </div>
                        </x-field>

                        <div class="flex items-center justify-between pt-1">
                            <label class="flex cursor-pointer items-center gap-2.5 text-sm text-ink-muted select-none">
                                <input type="checkbox" name="remember" class="checkbox">
                                <span>Ingat saya</span>
                            </label>
                        </div>

                        <div class="pt-2">
                            <x-button class="w-full" size="lg">Masuk</x-button>
                        </div>
                    </form>

                    <div class="mt-6 border-t border-line/70 pt-4 text-center">
                        <p class="text-[11px] text-ink-muted">
                            Akses terbatas hanya untuk staf &amp; otorisator Boedjang Group.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>

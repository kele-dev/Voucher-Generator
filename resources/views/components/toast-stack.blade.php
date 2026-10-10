{{-- Toasts from Alpine.store('toasts'). Server flash (session status) is pushed on load. --}}
<div x-data @if (session('status')) x-init="$store.toasts.push(@js(session('status')))" @endif
     class="pointer-events-none fixed right-4 bottom-4 z-50 flex w-[min(24rem,calc(100vw-2rem))] flex-col gap-2"
     role="status" aria-live="polite" aria-atomic="false">
    {{-- Also in the HTML for no-JS and for tests. --}}
    @if (session('status'))
        <noscript><p class="rounded-xl bg-emerald-50 px-4 py-3 text-sm">{{ session('status') }}</p></noscript>
    @endif

    <template x-for="toast in $store.toasts.items" :key="toast.id + ':' + toast.rev">
        <div class="pointer-events-auto relative flex items-start gap-3 overflow-hidden rounded-xl border bg-surface px-4 py-3 text-sm shadow-lift"
             :class="toast.type === 'error' ? 'border-danger-line' : 'border-emerald-200 dark:border-emerald-900/60'"
             x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-y-2 opacity-0"
             x-transition:leave="transition duration-150 ease-in" x-transition:leave-end="opacity-0"
             @mouseenter="$store.toasts.pause(toast.id)" @mouseleave="$store.toasts.resume(toast.id)"
             @focusin="$store.toasts.pause(toast.id)" @focusout="$store.toasts.resume(toast.id)">
            <span :class="toast.type === 'error' ? 'text-danger' : 'text-emerald-700 dark:text-emerald-300'">
                <x-icon name="check" class="size-5" x-show="toast.type !== 'error'" />
                <x-icon name="alert" class="size-5" x-show="toast.type === 'error'" />
            </span>
            <p class="flex-1 font-medium" x-text="toast.message"></p>
            <button type="button" @click="$store.toasts.dismiss(toast.id)" class="rounded p-0.5 text-ink-muted hover:text-ink" aria-label="Tutup notifikasi">
                <x-icon name="x" class="size-4" />
            </button>
            {{-- MI-07: a visible countdown; it pauses with the same hover/focus. --}}
            <span class="absolute bottom-0 left-0 h-0.5"
                  :class="toast.type === 'error' ? 'bg-danger' : 'bg-primary'"
                  :style="`animation: as-toast-bar ${toast.remaining}ms linear forwards; animation-play-state: ${toast.paused ? 'paused' : 'running'}`"></span>
        </div>
    </template>
</div>

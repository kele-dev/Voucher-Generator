{{-- One shared confirmation dialog, driven by Alpine.store('confirm'). --}}
<dialog id="confirm-dialog" x-data
        aria-labelledby="confirm-dialog-title" aria-describedby="confirm-dialog-body"
        @close="$store.confirm.answer(false)"
        @click.self="$store.confirm.answer(false)"
        class="m-auto w-[min(28rem,calc(100vw-2rem))] rounded-2xl border border-line bg-surface p-0 text-ink shadow-lift backdrop:bg-black/50">
    <div class="p-6">
        <div class="flex items-start gap-4">
            <span class="grid size-11 shrink-0 place-items-center rounded-full"
                  :class="$store.confirm.tone === 'danger' ? 'bg-danger-soft text-danger' : 'bg-primary-soft text-primary'">
                <x-icon name="alert" />
            </span>
            <div class="min-w-0">
                <h2 id="confirm-dialog-title" class="text-lg font-bold" x-text="$store.confirm.title"></h2>
                <p id="confirm-dialog-body" class="mt-1 text-sm text-ink-muted" x-text="$store.confirm.body" x-show="$store.confirm.body"></p>
            </div>
        </div>

        <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            {{-- Cancel first in tab order and autofocused: the safe default. --}}
            <button type="button" autofocus @click="$store.confirm.answer(false)"
                    class="inline-flex min-h-10 items-center justify-center rounded-xl border border-line-strong bg-surface px-4 text-sm font-semibold transition-colors hover:bg-canvas"
                    x-text="$store.confirm.cancelLabel"></button>
            <button type="button" @click="$store.confirm.answer(true)"
                    class="inline-flex min-h-10 items-center justify-center rounded-xl px-4 text-sm font-semibold text-white transition-colors"
                    :class="$store.confirm.tone === 'danger' ? 'bg-danger hover:bg-danger-hover' : 'bg-primary hover:bg-primary-hover'"
                    x-text="$store.confirm.confirmLabel"></button>
        </div>
    </div>
</dialog>

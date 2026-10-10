{{-- Expects an enclosing x-data with voucherPreview (src, loading, error). --}}
<div class="relative overflow-hidden rounded-xl border border-line bg-canvas">
    <a x-show="src" :href="src" target="_blank" rel="noopener" class="group block" x-cloak title="Buka pratinjau di tab baru">
        <img :src="src" alt="Pratinjau voucher" class="block w-full">
        <span class="absolute right-2 bottom-2 inline-flex items-center gap-1.5 rounded-full bg-black/70 px-3 py-1 text-xs font-semibold text-white opacity-90 transition group-hover:opacity-100">
            <x-icon name="eye" class="size-3.5" /> Buka di tab baru
        </span>
    </a>
    <div x-show="!src && !error" class="aspect-[2/1] w-full"
         :class="loading ? 'shimmer' : 'grid place-items-center text-sm text-ink-muted'">
        <span x-show="!loading" class="inline-flex items-center gap-2"><x-icon name="image" /> Belum ada pratinjau.</span>
    </div>
    <div x-show="loading && src" x-cloak class="absolute inset-0 grid place-items-center bg-canvas/60">
        <span class="rounded-full bg-surface px-3 py-1 text-xs font-semibold shadow-2xs">Memperbarui…</span>
    </div>
    <p x-show="error" x-text="error" x-cloak class="p-4 text-sm text-danger"></p>
</div>
<p class="mt-2 text-xs text-ink-muted">Pratinjau dari isian yang belum disimpan. Slot yang belum dipasang di editor template tidak tampil.</p>

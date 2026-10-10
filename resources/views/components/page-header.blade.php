@props(['title', 'subtitle' => null, 'back' => null])

<div class="mb-6 flex flex-wrap items-center justify-between gap-4 sm:mb-8">
    <div class="min-w-0">
        @if ($back)
            <a href="{{ $back }}" class="group mb-2.5 inline-flex items-center gap-1.5 text-xs font-semibold text-ink-muted transition-colors hover:text-primary">
                <span class="grid size-6 place-items-center rounded-lg border border-line bg-surface text-ink-muted transition-transform group-hover:-translate-x-0.5 group-hover:border-primary/40 group-hover:text-primary">
                    <x-icon name="collapse" class="size-3.5" />
                </span>
                <span>Kembali</span>
            </a>
        @endif
        <h1 class="font-sans text-2xl font-black tracking-tight text-ink sm:text-3xl">{{ $title }}</h1>
        @if ($subtitle) <p class="mt-1 text-sm text-ink-muted max-w-2xl leading-relaxed">{{ $subtitle }}</p> @endif
    </div>
    @if ($slot->isNotEmpty())
        <div class="flex flex-wrap items-center gap-2.5">{{ $slot }}</div>
    @endif
</div>

@props(['icon' => 'ticket', 'title', 'description' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center px-6 py-14 text-center']) }}>
    {{-- MI-09: the mark settles once on mount; no loop. --}}
    <span class="motion-enter mb-4 grid size-16 place-items-center rounded-2xl bg-primary-soft text-primary ring-8 ring-primary-soft/50">
        <x-icon :name="$icon" class="size-8" />
    </span>
    <p class="text-base font-bold text-ink">{{ $title }}</p>
    @if ($description) <p class="mt-1.5 max-w-sm text-sm text-ink-muted leading-relaxed">{{ $description }}</p> @endif
    @if ($slot->isNotEmpty()) <div class="mt-6 flex flex-wrap justify-center gap-3">{{ $slot }}</div> @endif
</div>

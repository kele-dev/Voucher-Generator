@props(['title' => null, 'description' => null, 'icon' => null])

<section {{ $attributes->merge(['class' => 'card p-6 sm:p-7']) }}>
    @if ($title || isset($actions))
        <header class="mb-5 flex flex-wrap items-start justify-between gap-3">
            <div class="flex items-center gap-3">
                @if ($icon)
                    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-primary-soft text-primary">
                        <x-icon :name="$icon" class="size-5" />
                    </span>
                @endif
                <div>
                    @if ($title) <h2 class="text-base font-extrabold tracking-tight text-ink">{{ $title }}</h2> @endif
                    @if ($description) <p class="mt-0.5 text-xs font-medium text-ink-muted">{{ $description }}</p> @endif
                </div>
            </div>
            @isset($actions) <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div> @endisset
        </header>
    @endif

    {{ $slot }}
</section>

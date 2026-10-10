@props(['variant' => 'primary', 'href' => null, 'icon' => null, 'size' => 'md'])

@php
    // MOTION dial: feedback 140ms. Press feedback is a 2% scale, nothing else.
    $base = 'inline-flex items-center justify-center gap-2 rounded-xl font-bold tracking-tight transition-all duration-[var(--motion-fast)] active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-50 disabled:active:scale-100';
    $sizes = [
        'sm' => 'px-3.5 py-1.5 text-xs',
        'md' => 'px-4.5 py-2.5 text-sm min-h-[42px]',
        'lg' => 'px-6 py-3 text-base min-h-12',
    ];
    $variants = [
        'primary' => 'bg-primary text-white hover:bg-primary-hover',
        'accent' => 'bg-accent text-white hover:bg-accent-hover',
        'secondary' => 'border border-line-strong bg-surface text-ink hover:border-primary/40 hover:bg-primary-soft/60 hover:text-primary',
        'ghost' => 'text-ink-muted hover:bg-primary-soft hover:text-primary',
        'danger' => 'border border-danger-line bg-danger-soft text-danger hover:border-danger/40 hover:bg-danger/10',
    ];
    $classes = $base.' '.$sizes[$size].' '.$variants[$variant];
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon) <x-icon :name="$icon" class="size-4" /> @endif
        {{ $slot }}
    </a>
@else
    <button {{ $attributes->merge(['type' => 'submit', 'class' => $classes]) }}>
        @if ($icon) <x-icon :name="$icon" class="size-4" /> @endif
        {{ $slot }}
    </button>
@endif

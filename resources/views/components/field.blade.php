@props(['label', 'name', 'hint' => null, 'optional' => false])

@php
    // "menu_items.0.name" style names map to "menu_items[0][name]" ids.
    $errorKey = str_replace(['[', ']'], ['.', ''], $name);
@endphp

{{-- The control inside the slot points at these ids with @aria('name'); see App\Support\FieldAria. --}}
<div {{ $attributes->merge(['class' => 'field-group']) }}>
    <label for="{{ $name }}" class="field-label">
        <span>{{ $label }}</span>
        @if ($optional) <span class="rounded bg-canvas px-1.5 py-0.5 text-[11px] font-normal text-ink-muted">opsional</span> @endif
    </label>
    {{ $slot }}
    @if ($hint) <p id="{{ $name }}-hint" class="mt-1.5 text-xs text-ink-muted">{{ $hint }}</p> @endif
    @error($errorKey) <p id="{{ $name }}-error" class="mt-1.5 text-sm font-medium text-danger">{{ $message }}</p> @enderror
</div>

@props(['active'])

<span @class([
    'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-semibold transition-colors',
    'border-emerald-200/70 bg-emerald-50 text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-300' => $active,
    'border-line bg-canvas text-ink-muted' => ! $active,
])>
    <span @class([
        'size-1.5 rounded-full',
        'bg-emerald-500' => $active,
        'bg-ink-muted/60' => ! $active,
    ])></span>
    {{ $active ? 'Aktif' : 'Nonaktif' }}
</span>

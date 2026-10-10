@props(['status'])

<span @class([
    'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-semibold tracking-wide transition-colors',
    'border-line bg-canvas text-ink-muted' => $status === App\Enums\CampaignStatus::Draft,
    'border-emerald-200/70 bg-emerald-50 text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-300' => $status === App\Enums\CampaignStatus::Active,
    'border-amber-200/70 bg-amber-50 text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-200' => $status === App\Enums\CampaignStatus::Paused,
    'border-rose-200/70 bg-rose-50 text-rose-700 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-300' => $status === App\Enums\CampaignStatus::Closed,
])>
    <span @class([
        'size-1.5 rounded-full',
        'bg-ink-muted/60' => $status === App\Enums\CampaignStatus::Draft,
        'bg-emerald-500' => $status === App\Enums\CampaignStatus::Active,
        'bg-amber-500' => $status === App\Enums\CampaignStatus::Paused,
        'bg-rose-500' => $status === App\Enums\CampaignStatus::Closed,
    ])></span>
    {{ $status->label() }}
</span>

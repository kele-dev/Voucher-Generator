<?php

namespace App\Models;

use App\Enums\VoucherKind;
use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Voucher extends Model
{
    /** @use HasFactory<\Database\Factories\VoucherFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'campaign_id',
        'generation_batch_id',
        'code',
        'status',
        'voucher_kind',
        'voucher_type',
        'voucher_template_id',
        'valid_from',
        'valid_until',
        'benefit_title',
        'benefit_description',
        'terms',
        'outlet_scope_text',
        'campaign_title',
        'product_image_path',
        'content_snapshot',
        'issued_at',
        'void_reason',
        'voided_by',
        'voided_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => VoucherStatus::class,
            'voucher_kind' => VoucherKind::class,
            'voucher_type' => VoucherType::class,
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'issued_at' => 'datetime',
            'voided_at' => 'datetime',
            'content_snapshot' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * @return BelongsTo<VoucherTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(VoucherTemplate::class, 'voucher_template_id');
    }

    /**
     * @return BelongsTo<GenerationBatch, $this>
     */
    public function generationBatch(): BelongsTo
    {
        return $this->belongsTo(GenerationBatch::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function isExpired(): bool
    {
        return $this->status === VoucherStatus::Issued
            && $this->valid_until !== null
            && $this->valid_until->isPast();
    }

    /**
     * Stored status, or the derived "expired" when the window has passed.
     */
    public function effectiveStatus(): VoucherStatus
    {
        if ($this->status === VoucherStatus::Voided) {
            return VoucherStatus::Voided;
        }

        return $this->isExpired() ? VoucherStatus::Expired : VoucherStatus::Issued;
    }

    /**
     * Every value a template field can print, keyed by slot name.
     *
     * @return array<string, mixed>
     */
    public function renderValues(?VoucherTemplate $template = null): array
    {
        $content = \App\Services\Voucher\VoucherContent::texts($this->content_snapshot);

        return array_merge($content, [
            'voucher_code' => $this->code,
            'campaign_title' => $this->campaign_title,
            'benefit_title' => $this->benefit_title,
            'benefit_description' => $this->benefit_description,
            'outlet_scope_text' => $this->outlet_scope_text,
            'expires_at' => $this->valid_until,
            'valid_from' => $this->valid_from,
            'valid_until' => $this->valid_until,
            'product_image' => $this->product_image_path,
        ]);
    }

    /**
     * Structured content lines shown on the detail page.
     *
     * @return array<int, array{label: string, value: string}>
     */
    public function contentLines(): array
    {
        return array_filter(
            \App\Services\Voucher\VoucherContent::texts($this->content_snapshot ?? []),
            fn ($value) => $value !== '',
        );
    }

    /**
     * @param  Builder<Voucher>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->role !== \App\Enums\Role::OutletStaff) {
            return $query;
        }

        $outletIds = $user->outlets()->pluck('outlets.id');
        $brandIds = $user->outlets()->pluck('brand_id')->unique()->values();

        return $query->whereHas('campaign', function (Builder $c) use ($outletIds, $brandIds): void {
            $c->where(fn (Builder $all) => $all
                ->where('applies_to_all_outlets', true)
                ->whereIn('brand_id', $brandIds))
                ->orWhereHas('outlets', fn (Builder $o) => $o->whereIn('outlets.id', $outletIds));
        });
    }

    public function isVisibleTo(User $user): bool
    {
        if ($user->role !== \App\Enums\Role::OutletStaff) {
            return true;
        }

        return $this->campaign?->isVisibleTo($user) ?? false;
    }
}

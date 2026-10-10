<?php

namespace App\Models;

use App\Enums\CampaignStatus;
use App\Enums\VoucherKind;
use App\Enums\VoucherType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    /** @use HasFactory<\Database\Factories\CampaignFactory> */
    use HasFactory;

    protected $table = 'voucher_campaigns';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'brand_id',
        'voucher_template_id',
        'name',
        'description',
        'benefit_title',
        'benefit_description',
        'terms',
        'starts_at',
        'expires_at',
        'status',
        'voucher_kind',
        'voucher_type',
        'code_prefix',
        'code_pattern',
        'applies_to_all_outlets',
        'generation_limit',
        'product_image_path',
        'created_by',
        'menu_items',
        'min_purchase',
        'normal_value',
        'service_type',
        'valid_days',
        'valid_time_from',
        'valid_time_until',
    ];

    protected function casts(): array
    {
        return [
            'status' => CampaignStatus::class,
            'voucher_kind' => VoucherKind::class,
            'voucher_type' => VoucherType::class,
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'applies_to_all_outlets' => 'boolean',
            'menu_items' => 'array',
            'valid_days' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * @return BelongsTo<VoucherTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(VoucherTemplate::class, 'voucher_template_id');
    }

    /**
     * @return BelongsToMany<Outlet, $this>
     */
    public function outlets(): BelongsToMany
    {
        return $this->belongsToMany(Outlet::class, 'campaign_outlets', 'campaign_id', 'outlet_id')->withTimestamps();
    }

    /**
     * @return HasMany<Voucher, $this>
     */
    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }

    /**
     * @return HasMany<GenerationBatch, $this>
     */
    public function batches(): HasMany
    {
        return $this->hasMany(GenerationBatch::class);
    }

    /**
     * A human sentence for the outlets a voucher may be used at.
     */
    public function outletScopeText(): string
    {
        if ($this->applies_to_all_outlets) {
            $brand = $this->brand?->name;

            return $brand
                ? 'BERLAKU DI SEMUA CABANG '.mb_strtoupper($brand)
                : 'BERLAKU DI SEMUA CABANG';
        }

        $names = $this->outlets()->pluck('name')->all();

        return $names === []
            ? 'BERLAKU DI OUTLET TERPILIH'
            : 'BERLAKU DI: '.implode(', ', $names);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function canGenerate(): bool
    {
        return $this->status?->canGenerate() === true && ! $this->isExpired();
    }

    public function hasIssuedVouchers(): bool
    {
        return $this->vouchers()->exists();
    }

    /**
     * Total vouchers requested but not yet generated: the floor the limit
     * cannot drop below.
     */
    public function committedCount(): int
    {
        $issued = $this->vouchers()->count();

        $pending = $this->batches()
            ->whereIn('status', ['pending', 'processing'])
            ->sum('requested_count');

        return $issued + (int) $pending;
    }

    /**
     * Limit to campaigns an outlet staff member can see.
     *
     * @param  Builder<Campaign>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->role !== \App\Enums\Role::OutletStaff) {
            return $query;
        }

        $outletIds = $user->outlets()->pluck('outlets.id');
        $brandIds = $user->outlets()->pluck('brand_id')->unique()->values();

        return $query->where(function (Builder $q) use ($outletIds, $brandIds): void {
            $q->where(fn (Builder $all) => $all
                ->where('applies_to_all_outlets', true)
                ->whereIn('brand_id', $brandIds))
                ->orWhereHas('outlets', fn (Builder $o) => $o->whereIn('outlets.id', $outletIds));
        });
    }

    /**
     * A staff member may view a campaign only when it applies to one of their
     * outlets (or to all outlets).
     */
    public function isVisibleTo(User $user): bool
    {
        if ($user->role !== \App\Enums\Role::OutletStaff) {
            return true;
        }

        if ($this->applies_to_all_outlets) {
            $brandIds = $user->outlets()->pluck('brand_id')->all();

            return in_array($this->brand_id, $brandIds, true);
        }

        $outletIds = $user->outlets()->pluck('outlets.id')->all();

        return $this->outlets()->whereIn('outlets.id', $outletIds)->exists();
    }
}

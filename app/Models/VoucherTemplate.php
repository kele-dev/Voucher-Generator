<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VoucherTemplate extends Model
{
    /** @use HasFactory<\Database\Factories\VoucherTemplateFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'brand_id',
        'name',
        'version',
        'image_path',
        'image_width',
        'image_height',
        'fields_json',
        'sample_image_path',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'fields_json' => 'array',
            'is_active' => 'boolean',
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
     * @return HasMany<Campaign, $this>
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class, 'voucher_template_id');
    }

    /**
     * Normalise a flat list of field boxes into the stored JSON shape.
     *
     * @param  array<int, array<string, mixed>>  $fields
     * @return array{fields: array<int, array<string, mixed>>}
     */
    public static function wrapFields(array $fields): array
    {
        return ['fields' => array_values($fields)];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fields(): array
    {
        $data = $this->fields_json ?? [];

        return $data['fields'] ?? [];
    }

    /**
     * @return array<int, string>
     */
    public function fieldKeys(): array
    {
        return array_column($this->fields(), 'key');
    }

    public function hasField(string $key): bool
    {
        return in_array($key, $this->fieldKeys(), true);
    }

    public function nextVersionNumber(): int
    {
        $max = static::query()
            ->where('brand_id', $this->brand_id)
            ->where('name', $this->name)
            ->max('version');

        return ((int) $max) + 1;
    }

    /**
     * A template that has issued at least one voucher can no longer be edited
     * or deleted; a new version must be created instead.
     */
    public function isLocked(): bool
    {
        return Voucher::query()->where('voucher_template_id', $this->id)->exists();
    }

    public function isLatestVersion(): bool
    {
        return (int) $this->version === (int) static::query()
            ->where('brand_id', $this->brand_id)
            ->where('name', $this->name)
            ->max('version');
    }
}

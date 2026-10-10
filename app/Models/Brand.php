<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Brand extends Model
{
    /** @use HasFactory<\Database\Factories\BrandFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'code_prefix',
        'logo_path',
        'template_physical_path',
        'template_electronic_path',
        'template_nominal_path',
        'template_electronic_nominal_path',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Brand $brand): void {
            if (blank($brand->slug)) {
                $brand->slug = Str::slug($brand->name);
            }
            if (filled($brand->code_prefix)) {
                $brand->code_prefix = Str::upper(trim($brand->code_prefix));
            }
        });
    }

    /**
     * @return HasMany<Outlet, $this>
     */
    public function outlets(): HasMany
    {
        return $this->hasMany(Outlet::class);
    }

    /**
     * @return HasMany<Campaign, $this>
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    /**
     * @return HasMany<VoucherTemplate, $this>
     */
    public function templates(): HasMany
    {
        return $this->hasMany(VoucherTemplate::class);
    }

    public function templatePath(string $kind): ?string
    {
        return $this->getAttribute('template_'.$kind.'_path');
    }

    public function templateImageUrl(string $kind): ?string
    {
        return filled($this->templatePath($kind))
            ? route('brands.template-image', [$this, $kind])
            : null;
    }
}

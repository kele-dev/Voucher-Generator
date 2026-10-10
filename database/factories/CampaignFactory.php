<?php

namespace Database\Factories;

use App\Enums\CampaignStatus;
use App\Models\Brand;
use App\Models\Campaign;
use App\Models\VoucherTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'brand_id' => Brand::factory(),
            'voucher_template_id' => fn (array $attributes) => VoucherTemplate::factory()->create(['brand_id' => $attributes['brand_id']])->id,
            'name' => 'Promo '.fake()->monthName().' '.fake()->unique()->numberBetween(1, 99999),
            'benefit_title' => '1 PORSI',
            'benefit_description' => 'NASI BEBEK + ES TEH',
            'terms' => 'Untuk makan di tempat.',
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
            'status' => CampaignStatus::Active,
            'code_prefix' => 'BB-VM',
            'code_pattern' => '{PREFIX}-{RAND:8}',
            'applies_to_all_outlets' => true,
        ];
    }

    public function kind(\App\Enums\VoucherKind $kind): static
    {
        return $this->state(fn () => ['voucher_kind' => $kind]);
    }

    public function type(\App\Enums\VoucherType $type): static
    {
        return $this->state(fn () => ['voucher_type' => $type]);
    }

    public function pattern(string $pattern): static
    {
        return $this->state(fn () => ['code_pattern' => $pattern]);
    }

    public function status(CampaignStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}

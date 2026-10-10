<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\VoucherTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Points at templates/test.jpg; tests that render must put a real image
 * there on the faked disk.
 *
 * @extends Factory<VoucherTemplate>
 */
class VoucherTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'brand_id' => Brand::factory(),
            'name' => fake()->unique()->words(2, true),
            'version' => 1,
            'image_path' => 'templates/test.jpg',
            'image_width' => 1600,
            'image_height' => 750,
            'fields_json' => VoucherTemplate::wrapFields([]),
            'is_active' => true,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Outlet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Outlet>
 */
class OutletFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'brand_id' => Brand::factory(),
            'name' => fake()->streetName(),
            'code' => strtoupper(fake()->unique()->bothify('OUT-####')),
            'city' => fake()->city(),
            'is_active' => true,
        ];
    }
}

<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Outlet;
use Illuminate\Database\Seeder;

/**
 * Sample brands matching the three template images in the spec kit. Local
 * environment only; prefixes are placeholders until confirmed per brand.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // Handle legacy BTS slug migration if exists
        Brand::where('slug', 'bts')->update(['name' => 'Bakso Tyga Sapi', 'slug' => 'bakso-tyga-sapi']);

        $brands = [
            ['name' => 'Bebek Boedjang', 'slug' => 'bebek-boedjang', 'code_prefix' => 'BB', 'outlet' => 'BB-JKT-01'],
            ['name' => 'Bakso Tyga Sapi', 'slug' => 'bakso-tyga-sapi', 'code_prefix' => 'BTS', 'outlet' => 'BTS-JKT-01'],
            ['name' => 'Sop Mak Garang', 'slug' => 'sop-mak-garang', 'code_prefix' => 'SMG', 'outlet' => 'SMG-JKT-01'],
            ['name' => 'Soto Semar', 'slug' => 'soto-semar', 'code_prefix' => 'SS', 'outlet' => 'SS-JKT-01'],
            ['name' => 'Kokotuku', 'slug' => 'kokotuku', 'code_prefix' => 'KT', 'outlet' => 'KT-JKT-01'],
            ['name' => 'Mie Ayam & Bakso Muntjul', 'slug' => 'mie-ayam-bakso-muntjul', 'code_prefix' => 'BM', 'outlet' => 'BM-JKT-01'],
            ['name' => 'Ikan Bakar Muara', 'slug' => 'ikan-bakar-muara', 'code_prefix' => 'IBM', 'outlet' => 'IBM-JKT-01'],
            ['name' => 'Warung Nini', 'slug' => 'warung-nini', 'code_prefix' => 'WN', 'outlet' => 'WN-JKT-01'],
            ['name' => 'Ayam Goreng Makmur', 'slug' => 'ayam-goreng-makmur', 'code_prefix' => 'AGM', 'outlet' => 'AGM-JKT-01'],
            ['name' => 'Sambal Camat', 'slug' => 'sambal-camat', 'code_prefix' => 'SC', 'outlet' => 'SC-JKT-01'],
        ];

        foreach ($brands as $data) {
            $brand = Brand::updateOrCreate(
                ['slug' => $data['slug']],
                ['name' => $data['name'], 'code_prefix' => $data['code_prefix'], 'is_active' => true],
            );

            Outlet::firstOrCreate(
                ['code' => $data['outlet']],
                ['brand_id' => $brand->id, 'name' => $data['name'].' Jakarta', 'city' => 'Jakarta', 'is_active' => true],
            );
        }
    }
}

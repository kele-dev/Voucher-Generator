<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\VoucherTemplate;
use Illuminate\Database\Seeder;

/**
 * Adds a new version of the seeded "Voucher Makan FREE" templates with the
 * structured-content slots (menu list, valid days/hours) already placed in
 * the free space of the artwork. A new version rather than an edit: version 1
 * may already have issued vouchers and is locked. Idempotent — skipped when
 * the latest version already maps the menu slot. Positions are a starting
 * point; fine-tune them in the editor.
 */
class TemplateContentSlotsSeeder extends Seeder
{
    private const NAME = 'Voucher Makan FREE';

    public function run(): void
    {
        $slots = [
            // Right column, between the outlet line and "Berlaku hingga".
            'bebek-boedjang' => [
                $this->text('menu_items', 0.580, 0.728, 0.400, 0.052, '#3A1D00', 24, 'bold'),
                $this->text('valid_schedule', 0.180, 0.790, 0.360, 0.045, '#3A1D00', 20, 'semibold', ['align' => 'left']),
            ],
            'bts' => [
                $this->text('menu_items', 0.620, 0.715, 0.360, 0.052, '#FFFFFF', 24, 'bold'),
                $this->text('valid_schedule', 0.140, 0.790, 0.380, 0.045, '#FFFFFF', 20, 'semibold', ['align' => 'left']),
            ],
        ];

        foreach ($slots as $slug => $extra) {
            $brand = Brand::where('slug', $slug)->first();
            $latest = $brand
                ? VoucherTemplate::where('brand_id', $brand->id)->where('name', self::NAME)->orderByDesc('version')->first()
                : null;

            if (! $latest) {
                $this->command?->warn("Lewati {$slug}: template ".self::NAME.' belum ada.');

                continue;
            }

            if (in_array('menu_items', array_column($latest->fields(), 'key'), true)) {
                continue;
            }

            $copy = $latest->replicate();
            $copy->version = $latest->nextVersionNumber();
            $copy->fields_json = VoucherTemplate::wrapFields([...$latest->fields(), ...$extra]);
            $copy->save();

            $this->command?->info("{$brand->name}: template v{$copy->version} dengan slot menu & jadwal.");
        }
    }

    private function text(string $key, float $x, float $y, float $w, float $h, string $color, int $size, string $weight, array $extra = []): array
    {
        return array_merge([
            'key' => $key, 'type' => 'text', 'x' => $x, 'y' => $y, 'width' => $w, 'height' => $h,
            'font_family' => 'poppins', 'font_weight' => $weight, 'font_size' => $size, 'color' => $color,
            'align' => 'center', 'vertical_align' => 'middle', 'uppercase' => false, 'wrap' => false,
            'line_height' => 1.25, 'date_format' => null,
            'cover_enabled' => false, 'cover_color' => '#FFFFFF', 'cover_inset' => 0,
        ], $extra);
    }
}

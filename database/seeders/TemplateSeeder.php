<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\VoucherTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Seeds the blank designs from the spec kit as templates. Field boxes are
 * first estimates from the artwork and are meant to be fine-tuned in the
 * editor. warung-nini.jpg is a finished example voucher (reference for how
 * a filled voucher should look), not a template, so it is not seeded.
 */
class TemplateSeeder extends Seeder
{
    private const SOURCE_DIR = 'boedjang-voucher-agent-kit/resources/voucher-templates';

    public function run(): void
    {
        $templates = [
            'bebek-boedjang' => [
                'file' => 'bebek-boedjang.jpg',
                'fields' => [
                    $this->image('product_image', 0.135, 0.25, 0.39, 0.56),
                    $this->text('benefit_title', 0.572, 0.540, 0.128, 0.078, '#FFFFFF', 30, 'extrabold'),
                    $this->text('benefit_description', 0.706, 0.540, 0.272, 0.078, '#3A1D00', 26, 'bold'),
                    $this->text('expires_at', 0.802, 0.787, 0.190, 0.054, '#FFFFFF', 24, 'bold', ['uppercase' => true, 'date_format' => 'j F Y']),
                    $this->text('voucher_code', 0.745, 0.915, 0.250, 0.075, '#E30613', 38, 'bold'),
                ],
            ],
            'bts' => [
                'file' => 'bts.jpg',
                'fields' => [
                    $this->image('product_image', 0.135, 0.22, 0.42, 0.58),
                    $this->text('benefit_title', 0.629, 0.532, 0.121, 0.064, '#7A0C0C', 28, 'extrabold'),
                    $this->text('benefit_description', 0.754, 0.532, 0.200, 0.064, '#FFFFFF', 24, 'bold'),
                    $this->text('expires_at', 0.795, 0.798, 0.190, 0.053, '#7A0C0C', 24, 'bold', ['uppercase' => true, 'date_format' => 'j F Y']),
                    $this->text('voucher_code', 0.765, 0.915, 0.232, 0.075, '#8B0D0D', 36, 'bold'),
                ],
            ],
        ];

        $disk = Storage::disk(config('voucher.templates.disk'));

        foreach ($templates as $slug => $data) {
            $brand = Brand::where('slug', $slug)->first();
            $source = base_path(self::SOURCE_DIR.'/'.$data['file']);

            if (! $brand || ! is_file($source)) {
                $this->command?->warn("Lewati template {$slug}: brand atau file sumber tidak ada.");

                continue;
            }

            $path = 'templates/seed-'.$data['file'];
            if (! $disk->exists($path)) {
                $disk->put($path, file_get_contents($source));
            }

            [$width, $height] = getimagesize($source);

            VoucherTemplate::firstOrCreate(
                ['brand_id' => $brand->id, 'name' => 'Voucher Makan FREE', 'version' => 1],
                [
                    'image_path' => $path,
                    'image_width' => $width,
                    'image_height' => $height,
                    'fields_json' => VoucherTemplate::wrapFields($data['fields']),
                    'is_active' => true,
                ],
            );
        }
    }

    private function image(string $key, float $x, float $y, float $w, float $h): array
    {
        return ['key' => $key, 'type' => 'image', 'x' => $x, 'y' => $y, 'width' => $w, 'height' => $h, 'fit' => 'contain'];
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

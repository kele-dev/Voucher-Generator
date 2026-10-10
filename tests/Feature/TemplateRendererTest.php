<?php

namespace Tests\Feature;

use App\Models\VoucherTemplate;
use App\Services\Voucher\TemplateRenderer;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TemplateRendererTest extends TestCase
{
    use RefreshDatabase;

    private TemplateRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->renderer = new TemplateRenderer;
        Storage::fake('local');
    }

    /**
     * A plain white template so drawn pixels are easy to detect.
     */
    private function whiteTemplate(array $fields, int $width = 800, int $height = 400): VoucherTemplate
    {
        $gd = imagecreatetruecolor($width, $height);
        imagefill($gd, 0, 0, imagecolorallocate($gd, 255, 255, 255));
        ob_start();
        imagepng($gd);
        Storage::disk('local')->put('templates/white.png', ob_get_clean());

        return VoucherTemplate::factory()->create([
            'image_path' => 'templates/white.png',
            'image_width' => $width,
            'image_height' => $height,
            'fields_json' => VoucherTemplate::wrapFields($fields),
        ]);
    }

    private function field(array $overrides = []): array
    {
        return array_merge([
            'key' => 'voucher_code', 'type' => 'text',
            'x' => 0.25, 'y' => 0.25, 'width' => 0.5, 'height' => 0.5,
            'font_family' => 'poppins', 'font_weight' => 'bold', 'font_size' => 40,
            'color' => '#000000', 'align' => 'center', 'vertical_align' => 'middle',
        ], $overrides);
    }

    public function test_normalized_box_maps_to_native_pixels(): void
    {
        $box = $this->renderer->box(['x' => 0.5, 'y' => 0.25, 'width' => 0.25, 'height' => 0.1], 1600, 750);

        $this->assertSame(['x' => 800, 'y' => 188, 'w' => 400, 'h' => 75], $box);
    }

    public function test_dates_use_indonesian_month_names_and_uppercase(): void
    {
        $date = CarbonImmutable::create(2026, 10, 15);

        $this->assertSame('15 Oktober 2026', $this->renderer->formatText(['date_format' => 'j F Y'], $date));
        $this->assertSame('15 OKTOBER 2026', $this->renderer->formatText(['date_format' => 'j F Y', 'uppercase' => true], $date));
        $this->assertSame('', $this->renderer->formatText([], null));
    }

    public function test_long_text_shrinks_to_fit_its_box(): void
    {
        $field = $this->field(['font_size' => 120]);
        $box = ['x' => 0, 'y' => 0, 'w' => 300, 'h' => 60];

        $size = $this->renderer->fitFontSize($field, 'BB-VM-7K3Q9XZA', $box, false);

        $this->assertLessThan(120, $size);
        $this->assertGreaterThanOrEqual(8, $size);
    }

    public function test_text_is_drawn_inside_its_box_only(): void
    {
        $template = $this->whiteTemplate([$this->field()]);

        $image = $this->renderer->render($template, ['voucher_code' => 'BB-VM-TEST']);
        $gd = $image->core()->native();

        $inside = $outside = 0;
        for ($x = 0; $x < 800; $x += 2) {
            for ($y = 0; $y < 400; $y += 2) {
                if ((imagecolorat($gd, $x, $y) & 0xFFFFFF) === 0xFFFFFF) {
                    continue;
                }
                $inBox = $x >= 200 && $x < 600 && $y >= 100 && $y < 300;
                $inBox ? $inside++ : $outside++;
            }
        }

        $this->assertGreaterThan(0, $inside, 'text should be drawn');
        $this->assertSame(0, $outside, 'text must not spill outside its box');
    }

    public function test_cover_box_paints_the_slot(): void
    {
        $template = $this->whiteTemplate([$this->field(['cover_enabled' => true, 'cover_color' => '#FF0000'])]);

        $gd = $this->renderer->render($template, [])->core()->native();

        // Empty value: cover still drawn, no text.
        $this->assertSame(0xFF0000, imagecolorat($gd, 210, 110) & 0xFFFFFF);
        $this->assertSame(0xFFFFFF, imagecolorat($gd, 10, 10) & 0xFFFFFF);
    }

    public function test_missing_values_leave_template_untouched(): void
    {
        $template = $this->whiteTemplate([
            $this->field(),
            ['key' => 'product_image', 'type' => 'image', 'x' => 0, 'y' => 0, 'width' => 0.2, 'height' => 0.2, 'fit' => 'contain'],
        ]);

        $gd = $this->renderer->render($template, [])->core()->native();

        $this->assertSame(0xFFFFFF, imagecolorat($gd, 400, 200) & 0xFFFFFF);
        $this->assertSame(0xFFFFFF, imagecolorat($gd, 20, 20) & 0xFFFFFF);
    }

    public function test_product_image_is_contained_within_its_box(): void
    {
        $template = $this->whiteTemplate([
            ['key' => 'product_image', 'type' => 'image', 'x' => 0, 'y' => 0, 'width' => 0.5, 'height' => 0.5, 'fit' => 'contain'],
        ]);

        // A solid blue square, 1000px, must be scaled into the 400x200 box.
        $square = imagecreatetruecolor(1000, 1000);
        imagefill($square, 0, 0, imagecolorallocate($square, 0, 0, 255));
        $path = tempnam(sys_get_temp_dir(), 'sq').'.png';
        imagepng($square, $path);

        $gd = $this->renderer->render($template, ['product_image' => $path])->core()->native();
        @unlink($path);

        $this->assertSame(0x0000FF, imagecolorat($gd, 200, 100) & 0xFFFFFF, 'centre of box is the image');
        $this->assertSame(0xFFFFFF, imagecolorat($gd, 20, 100) & 0xFFFFFF, 'contain leaves side margins');
        $this->assertSame(0xFFFFFF, imagecolorat($gd, 500, 300) & 0xFFFFFF, 'nothing outside the box');
    }
}

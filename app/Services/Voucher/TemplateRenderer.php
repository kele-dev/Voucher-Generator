<?php

namespace App\Services\Voucher;

use App\Models\VoucherTemplate;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use GdImage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Image;
use Intervention\Image\ImageManager;
use Illuminate\Support\Facades\Storage;

/**
 * Draws a voucher template's image and text fields.
 *
 * Field boxes are stored normalised (0..1) and mapped to native pixels at
 * render time, so a template renders correctly at any export scale. Text is
 * shrunk to fit its box and never drawn outside it.
 */
class TemplateRenderer
{
    private const MIN_FONT_SIZE = 8;

    private ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver());
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array{x: int, y: int, w: int, h: int}
     */
    public function box(array $field, int $width, int $height): array
    {
        return [
            'x' => (int) round(((float) ($field['x'] ?? 0)) * $width),
            'y' => (int) round(((float) ($field['y'] ?? 0)) * $height),
            'w' => (int) round(((float) ($field['width'] ?? 0)) * $width),
            'h' => (int) round(((float) ($field['height'] ?? 0)) * $height),
        ];
    }

    /**
     * Format a field value: dates use the Indonesian month names and an
     * optional uppercase transform. Empty values print nothing.
     *
     * @param  array<string, mixed>  $field
     */
    public function formatText(array $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if ($value instanceof DateTimeInterface) {
            $format = (string) ($field['date_format'] ?? 'j F Y');
            $text = CarbonImmutable::instance($value)->locale('id')->translatedFormat($format);
        } else {
            $text = (string) $value;
        }

        if (! empty($field['uppercase'])) {
            $text = mb_strtoupper($text);
        }

        return $text;
    }

    /**
     * Largest font size (<= the field's size) at which the text fits the box.
     *
     * @param  array<string, mixed>  $field
     * @param  array{x: int, y: int, w: int, h: int}  $box
     */
    public function fitFontSize(array $field, string $text, array $box, bool $multiline = false): int
    {
        $max = max(self::MIN_FONT_SIZE, (int) ($field['font_size'] ?? 24));
        $font = $this->fontPath($field);
        $lines = $multiline ? explode("\n", $text) : [$text];

        for ($size = $max; $size > self::MIN_FONT_SIZE; $size--) {
            if ($this->fits($size, $lines, $font, $box)) {
                return $size;
            }
        }

        return self::MIN_FONT_SIZE;
    }

    /**
     * Render the template with the given values into an image.
     *
     * @param  array<string, mixed>  $values
     */
    public function render(VoucherTemplate $template, array $values): Image
    {
        $image = $this->manager->decode($this->templatePath($template));
        $gd = $image->core()->native();
        $width = $image->width();
        $height = $image->height();

        foreach ($template->fields() as $field) {
            $key = $field['key'] ?? null;

            if (! is_string($key) || $key === '') {
                continue;
            }

            $box = $this->box($field, $width, $height);
            $value = $values[$key] ?? null;

            if (($field['type'] ?? 'text') === 'image') {
                $this->drawImage($gd, $field, $box, $value);

                continue;
            }

            if (! empty($field['cover_enabled'])) {
                $this->paintCover($gd, $field, $box);
            }

            $text = $this->formatText($field, $value);

            if ($text !== '') {
                $this->drawText($gd, $field, $box, $text);
            }
        }

        return $image;
    }

    /**
     * @param  array{x: int, y: int, w: int, h: int}  $box
     */
    private function drawText(GdImage $gd, array $field, array $box, string $text): void
    {
        $font = $this->fontPath($field);
        $lines = explode("\n", $text);
        $size = $this->fitFontSize($field, $text, $box, count($lines) > 1);
        $color = $this->color($gd, (string) ($field['color'] ?? '#000000'));
        $lineHeight = (int) round($size * 1.2);
        $totalHeight = $lineHeight * count($lines);

        $y = match ($field['vertical_align'] ?? 'middle') {
            'top' => $box['y'],
            'bottom' => $box['y'] + $box['h'] - $totalHeight,
            default => $box['y'] + (int) round(($box['h'] - $totalHeight) / 2),
        };

        foreach ($lines as $line) {
            if ($line !== '') {
                $bbox = imagettfbbox($size, 0, $font, $line);
                $textWidth = abs($bbox[2] - $bbox[0]);
                $ascent = abs($bbox[7]);

                $x = match ($field['align'] ?? 'center') {
                    'left' => $box['x'],
                    'right' => $box['x'] + $box['w'] - $textWidth,
                    default => $box['x'] + (int) round(($box['w'] - $textWidth) / 2),
                };

                imagettftext($gd, $size, 0, (int) $x, (int) ($y + $ascent), $color, $font, $line);
            }

            $y += $lineHeight;
        }
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array{x: int, y: int, w: int, h: int}  $box
     */
    private function drawImage(GdImage $gd, array $field, array $box, mixed $value): void
    {
        $path = $this->resolveImagePath($value);

        if ($path === null) {
            return;
        }

        $data = @file_get_contents($path);
        $source = $data === false ? false : @imagecreatefromstring($data);

        if ($source === false) {
            return;
        }

        $sw = imagesx($source);
        $sh = imagesy($source);

        if ($sw > 0 && $sh > 0 && $box['w'] > 0 && $box['h'] > 0) {
            $fit = $field['fit'] ?? 'contain';
            $scale = $fit === 'cover'
                ? max($box['w'] / $sw, $box['h'] / $sh)
                : min($box['w'] / $sw, $box['h'] / $sh);

            $dw = max(1, (int) round($sw * $scale));
            $dh = max(1, (int) round($sh * $scale));
            $dx = $box['x'] + (int) round(($box['w'] - $dw) / 2);
            $dy = $box['y'] + (int) round(($box['h'] - $dh) / 2);

            imagecopyresampled($gd, $source, $dx, $dy, 0, 0, $dw, $dh, $sw, $sh);
        }

        imagedestroy($source);
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array{x: int, y: int, w: int, h: int}  $box
     */
    private function paintCover(GdImage $gd, array $field, array $box): void
    {
        $color = $this->color($gd, (string) ($field['cover_color'] ?? '#FFFFFF'));
        imagefilledrectangle($gd, $box['x'], $box['y'], $box['x'] + $box['w'] - 1, $box['y'] + $box['h'] - 1, $color);
    }

    /**
     * @param  array<int, string>  $lines
     * @param  array{x: int, y: int, w: int, h: int}  $box
     */
    private function fits(int $size, array $lines, string $font, array $box): bool
    {
        $lineHeight = (int) round($size * 1.2);

        if ($lineHeight * count($lines) > $box['h']) {
            return false;
        }

        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }

            $bbox = imagettfbbox($size, 0, $font, $line);
            $textWidth = abs($bbox[2] - $bbox[0]);
            $textHeight = abs($bbox[1] - $bbox[7]);

            if ($textWidth > $box['w'] || $textHeight > $box['h']) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function fontPath(array $field): string
    {
        $family = (string) ($field['font_family'] ?? 'poppins');
        $weight = (string) ($field['font_weight'] ?? 'regular');

        $path = config("voucher.fonts.{$family}.{$weight}")
            ?? config("voucher.fonts.{$family}.regular")
            ?? config('voucher.fonts.poppins.regular');

        if (! is_string($path) || ! is_file($path)) {
            $fallback = config('voucher.fonts.poppins.regular');

            if (is_string($fallback) && is_file($fallback)) {
                return $fallback;
            }
        }

        return (string) $path;
    }

    private function color(GdImage $gd, string $hex): int
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (strlen($hex) !== 6 || ! ctype_xdigit($hex)) {
            $hex = '000000';
        }

        return (int) imagecolorallocate(
            $gd,
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        );
    }

    private function templatePath(VoucherTemplate $template): string
    {
        return $this->resolveImagePath($template->image_path) ?? $template->image_path;
    }

    private function resolveImagePath(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        if (is_file($value)) {
            return $value;
        }

        $disk = (string) config('voucher.templates.disk', 'local');

        if (Storage::disk($disk)->exists($value)) {
            return Storage::disk($disk)->path($value);
        }

        return null;
    }
}

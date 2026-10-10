<?php

namespace App\Services\Voucher;

use App\Models\Voucher;
use App\Models\VoucherTemplate;
use Intervention\Image\Interfaces\ImageInterface;

/**
 * Renders a voucher (or an unsaved preview) to an image.
 */
class VoucherRenderer
{
    public function __construct(private readonly TemplateRenderer $templates)
    {
    }

    public function render(Voucher $voucher, ?VoucherTemplate $template = null): ImageInterface
    {
        $template ??= $voucher->template;

        return $this->templates->render($template, $voucher->renderValues($template));
    }

    public function encode(Voucher $voucher, string $format = 'jpg', ?VoucherTemplate $template = null, ?int $scale = null): string
    {
        $template ??= $voucher->template;

        return (string) $this->scaled($this->render($voucher, $template), $template, $scale)
            ->encodeUsingFileExtension($format);
    }

    /**
     * Render arbitrary values (an unsaved form) onto a template.
     *
     * @param  array<string, mixed>  $values
     */
    public function encodeValues(VoucherTemplate $template, array $values, string $format = 'jpg', ?int $scale = null): string
    {
        return (string) $this->scaled($this->templates->render($template, $values), $template, $scale)
            ->encodeUsingFileExtension($format);
    }

    private function scaled(ImageInterface $image, ?VoucherTemplate $template, ?int $scale): ImageInterface
    {
        if ($template === null || $scale === null || $scale === 100 || $scale < 1) {
            return $image;
        }

        $width = (int) round($template->image_width * $scale / 100);

        return $image->scale(width: max(1, $width));
    }
}

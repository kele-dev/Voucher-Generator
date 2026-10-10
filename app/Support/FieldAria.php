<?php

namespace App\Support;

use Illuminate\Support\HtmlString;
use Illuminate\Support\ViewErrorBag;

/**
 * Builds the aria-describedby / aria-invalid attributes for a form control,
 * linking it to the hint and error paragraphs that <x-field> renders.
 */
class FieldAria
{
    public static function attrs(string $name, bool $hint = false): HtmlString
    {
        $errors = view()->shared('errors');

        // "menu_items[0][name]" maps to the "menu_items.0.name" error key.
        $errorKey = str_replace(['[', ']'], ['.', ''], $name);
        $hasError = $errors instanceof ViewErrorBag && $errors->has($errorKey);

        $described = [];

        if ($hint) {
            $described[] = $name.'-hint';
        }

        if ($hasError) {
            $described[] = $name.'-error';
        }

        if ($described === []) {
            return new HtmlString('');
        }

        $attrs = 'aria-describedby="'.implode(' ', $described).'"';

        if ($hasError) {
            $attrs .= ' aria-invalid="true"';
        }

        return new HtmlString($attrs);
    }
}

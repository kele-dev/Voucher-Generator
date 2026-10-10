<?php

namespace App\Services\Voucher;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * A parsed voucher-code pattern.
 *
 * Tokens:
 *   {PREFIX}    brand code prefix, e.g. "WN-VM"
 *   {DDMM}      day + month of the issue date
 *   {DATE:Ymd}  arbitrary PHP date format (only d, m, y, Y allowed)
 *   {SEQ:n}     zero-padded sequence, n >= 2
 *   {RAND:n}    n random chars from the non-ambiguous charset
 *   {ALPHA:n}   n random uppercase letters
 *
 * A pattern must contain exactly one {SEQ:n}, or at least one random token,
 * so every generated code is unique.
 */
class CodePattern
{
    /**
     * @param  array<int, array<string, mixed>>  $tokens
     */
    private function __construct(private readonly array $tokens)
    {
    }

    public static function parse(string $pattern): self
    {
        if (trim($pattern) === '') {
            throw new InvalidArgumentException('Pola kode tidak boleh kosong.');
        }

        $tokens = [];
        $sequenceCount = 0;
        $randomCount = 0;
        $offset = 0;
        $length = strlen($pattern);

        while ($offset < $length) {
            if ($pattern[$offset] === '{') {
                $close = strpos($pattern, '}', $offset);

                if ($close === false) {
                    throw new InvalidArgumentException('Token tidak ditutup: kurung kurawal hilang.');
                }

                $inner = substr($pattern, $offset + 1, $close - $offset - 1);
                $token = self::parseToken($inner);

                if ($token === null) {
                    throw new InvalidArgumentException("Token tidak dikenal: {{$inner}}.");
                }

                if ($token['type'] === 'seq') {
                    $sequenceCount++;
                }

                if ($token['type'] === 'rand' || $token['type'] === 'alpha') {
                    $randomCount++;
                }

                $tokens[] = $token;
                $offset = $close + 1;

                continue;
            }

            $next = strpos($pattern, '{', $offset);
            $literal = $next === false ? substr($pattern, $offset) : substr($pattern, $offset, $next - $offset);

            if (preg_match('/[^A-Z0-9\-]/', $literal)) {
                throw new InvalidArgumentException('Teks tetap hanya boleh huruf besar, angka, dan tanda hubung.');
            }

            $tokens[] = ['type' => 'literal', 'value' => $literal];
            $offset = $next === false ? $length : $next;
        }

        if ($sequenceCount > 1) {
            throw new InvalidArgumentException('Pola hanya boleh punya satu {SEQ:n}.');
        }

        if ($sequenceCount === 0 && $randomCount === 0) {
            throw new InvalidArgumentException('Pola butuh {SEQ:n} atau bagian acak agar kode unik.');
        }

        return new self($tokens);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function parseToken(string $inner): ?array
    {
        if ($inner === 'PREFIX') {
            return ['type' => 'prefix'];
        }

        if ($inner === 'DDMM') {
            return ['type' => 'ddmm'];
        }

        if (str_starts_with($inner, 'DATE:')) {
            $format = substr($inner, 5);

            if (! preg_match('/^[dmyY]{1,6}$/', $format)) {
                throw new InvalidArgumentException("Format tanggal tidak didukung: {{$inner}}.");
            }

            return ['type' => 'date', 'format' => $format];
        }

        if (str_starts_with($inner, 'SEQ:')) {
            $size = substr($inner, 4);

            if (! ctype_digit($size) || (int) $size < 2 || (int) $size > 9) {
                throw new InvalidArgumentException('Panjang {SEQ:n} harus antara 2 dan 9.');
            }

            return ['type' => 'seq', 'length' => (int) $size];
        }

        if (str_starts_with($inner, 'RAND:')) {
            $size = substr($inner, 5);

            if (! ctype_digit($size) || (int) $size < 1 || (int) $size > 16) {
                throw new InvalidArgumentException('Panjang {RAND:n} harus antara 1 dan 16.');
            }

            return ['type' => 'rand', 'length' => (int) $size];
        }

        if (str_starts_with($inner, 'ALPHA:')) {
            $size = substr($inner, 6);

            if (! ctype_digit($size) || (int) $size < 1 || (int) $size > 16) {
                throw new InvalidArgumentException('Panjang {ALPHA:n} harus antara 1 dan 16.');
            }

            return ['type' => 'alpha', 'length' => (int) $size];
        }

        return null;
    }

    public function render(string $prefix, CarbonImmutable $date, ?int $sequence = null): string
    {
        $out = '';

        foreach ($this->tokens as $token) {
            $out .= match ($token['type']) {
                'literal' => $token['value'],
                'prefix' => $prefix,
                'ddmm' => $date->format('dm'),
                'date' => $date->format($token['format']),
                'seq' => $sequence === null
                    ? str_repeat('0', $token['length'])
                    : str_pad((string) $sequence, $token['length'], '0', STR_PAD_LEFT),
                'rand' => $this->random($token['length'], (string) config('voucher.codes.charset')),
                'alpha' => $this->random($token['length'], (string) config('voucher.codes.alpha_charset')),
                default => '',
            };
        }

        return $out;
    }

    /**
     * The code shape with variable parts blanked: "#" for each sequence
     * digit, "*" for each random character. Used as the sequence scope key.
     */
    public function skeleton(string $prefix, CarbonImmutable $date): string
    {
        $out = '';

        foreach ($this->tokens as $token) {
            $out .= match ($token['type']) {
                'literal' => $token['value'],
                'prefix' => $prefix,
                'ddmm' => $date->format('dm'),
                'date' => $date->format($token['format']),
                'seq' => str_repeat('#', $token['length']),
                'rand', 'alpha' => str_repeat('*', $token['length']),
                default => '',
            };
        }

        return $out;
    }

    public function hasSequence(): bool
    {
        return $this->firstTokenOfType('seq') !== null;
    }

    public function hasRandom(): bool
    {
        return $this->firstTokenOfType('rand') !== null || $this->firstTokenOfType('alpha') !== null;
    }

    public function sequenceLength(): int
    {
        return (int) ($this->firstTokenOfType('seq')['length'] ?? 0);
    }

    /**
     * Sequence-only patterns are guessable/sequential; anything with a random
     * token is not.
     */
    public function isGuessable(): bool
    {
        return ! $this->hasRandom();
    }

    /**
     * How many distinct codes this pattern can produce in one sequence scope.
     */
    public function capacity(): int
    {
        if ($this->hasSequence()) {
            return (10 ** $this->sequenceLength()) - 1;
        }

        $charset = strlen((string) config('voucher.codes.charset'));
        $alpha = strlen((string) config('voucher.codes.alpha_charset'));
        $capacity = 1;

        foreach ($this->tokens as $token) {
            if ($token['type'] === 'rand') {
                $capacity *= $charset ** $token['length'];
            } elseif ($token['type'] === 'alpha') {
                $capacity *= $alpha ** $token['length'];
            }
        }

        return $capacity;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function firstTokenOfType(string $type): ?array
    {
        foreach ($this->tokens as $token) {
            if ($token['type'] === $type) {
                return $token;
            }
        }

        return null;
    }

    private function random(int $length, string $charset): string
    {
        $max = strlen($charset) - 1;
        $out = '';

        for ($i = 0; $i < $length; $i++) {
            $out .= $charset[random_int(0, $max)];
        }

        return $out;
    }
}

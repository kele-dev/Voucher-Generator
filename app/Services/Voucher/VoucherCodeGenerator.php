<?php

namespace App\Services\Voucher;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Issues unique voucher codes for a pattern.
 *
 * Sequential patterns share a persistent counter keyed by the code skeleton
 * (`code_sequences`), so consecutive batches continue the same run and a new
 * visible date starts a fresh run. Random collisions against existing rows or
 * the current batch are re-rolled a handful of times; a pure sequence that
 * collides cannot be re-rolled and fails the batch instead of issuing a
 * duplicate.
 */
class VoucherCodeGenerator
{
    private const MAX_REROLLS = 5;

    /**
     * @return array<int, string>
     */
    public function generate(CodePattern $pattern, string $prefix, CarbonImmutable $date, int $count): array
    {
        if ($count < 1) {
            return [];
        }

        return DB::transaction(function () use ($pattern, $prefix, $date, $count): array {
            $sequence = 0;

            if ($pattern->hasSequence()) {
                $key = $pattern->skeleton($prefix, $date);
                $last = (int) DB::table('code_sequences')->where('key', $key)->lockForUpdate()->value('last_value');
                $available = $pattern->capacity() - $last;

                if ($count > $available) {
                    throw new CodeGenerationException(
                        "Rentang kode hampir habis: sisa {$available} kode untuk pola ini."
                    );
                }

                $sequence = $last + 1;

                DB::table('code_sequences')->updateOrInsert(
                    ['key' => $key],
                    ['last_value' => $last + $count, 'updated_at' => now(), 'created_at' => now()],
                );
            }

            $codes = [];
            $seen = [];

            for ($i = 0; $i < $count; $i++) {
                $code = $this->draw($pattern, $prefix, $date, $pattern->hasSequence() ? $sequence : null, $seen);
                $seen[$code] = true;
                $codes[] = $code;
                $sequence++;
            }

            return $codes;
        });
    }

    /**
     * @param  array<string, bool>  $seen
     */
    private function draw(CodePattern $pattern, string $prefix, CarbonImmutable $date, ?int $sequence, array $seen): string
    {
        $code = $pattern->render($prefix, $date, $sequence);

        if (! $pattern->hasRandom()) {
            if ($this->exists($code) || isset($seen[$code])) {
                throw new CodeGenerationException("Kode {$code} sudah dipakai dan pola ini tidak bisa diacak ulang.");
            }

            return $code;
        }

        for ($attempt = 0; $attempt < self::MAX_REROLLS; $attempt++) {
            if (! $this->exists($code) && ! isset($seen[$code])) {
                return $code;
            }

            $code = $pattern->render($prefix, $date, $sequence);
        }

        if ($this->exists($code) || isset($seen[$code])) {
            throw new CodeGenerationException("Gagal menemukan kode acak unik setelah beberapa percobaan ({$code}).");
        }

        return $code;
    }

    private function exists(string $code): bool
    {
        return DB::table('vouchers')->where('code', $code)->exists();
    }
}

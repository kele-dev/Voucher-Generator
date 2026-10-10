<?php

namespace Tests\Feature;

use App\Services\Voucher\CodePattern;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CodePatternTest extends TestCase
{
    private CarbonImmutable $date;

    protected function setUp(): void
    {
        parent::setUp();
        $this->date = CarbonImmutable::create(2026, 10, 8);
    }

    public function test_exact_example_preset_reproduces_the_reference_code(): void
    {
        $pattern = CodePattern::parse(config('voucher.codes.presets.example_exact.pattern'));

        $this->assertSame('WN-VM0810005', $pattern->render('WN-VM', $this->date, 5));
        $this->assertTrue($pattern->isGuessable());
        $this->assertSame(999, $pattern->capacity());
    }

    public function test_example_random_preset_adds_unguessable_suffix(): void
    {
        $pattern = CodePattern::parse(config('voucher.codes.presets.example_random.pattern'));
        $code = $pattern->render('WN-VM', $this->date, 5);

        $this->assertMatchesRegularExpression('/^WN-VM08100005[A-Z2-9]{3}$/', $code);
        $this->assertFalse($pattern->isGuessable());
        $this->assertSame(9999, $pattern->capacity());
    }

    public function test_random_part_never_uses_ambiguous_characters(): void
    {
        $pattern = CodePattern::parse('{PREFIX}-{RAND:16}');

        for ($i = 0; $i < 200; $i++) {
            $this->assertDoesNotMatchRegularExpression('/[ILO01]/', substr($pattern->render('X', $this->date), 2));
        }
    }

    public function test_date_tokens_and_skeleton(): void
    {
        $pattern = CodePattern::parse('{PREFIX}-{DATE:Ymd}-{SEQ:3}{RAND:2}');

        $this->assertMatchesRegularExpression('/^BB-20261008-042[A-Z2-9]{2}$/', $pattern->render('BB', $this->date, 42));
        $this->assertSame('BB-20261008-###**', $pattern->skeleton('BB', $this->date));
    }

    public function test_skeleton_without_year_is_shared_across_years(): void
    {
        $pattern = CodePattern::parse('{PREFIX}{DDMM}{SEQ:3}');

        $this->assertSame(
            $pattern->skeleton('WN-VM', $this->date),
            $pattern->skeleton('WN-VM', $this->date->addYear()),
        );
    }

    public static function invalidPatterns(): array
    {
        return [
            'no unique part' => ['{PREFIX}{DDMM}'],
            'two sequences' => ['{PREFIX}{SEQ:3}{SEQ:3}'],
            'lowercase literal' => ['{PREFIX}-abc{RAND:4}'],
            'misspelled token' => ['{PREFIX}{RANDOM:4}'],
            'sequence too short' => ['{PREFIX}{SEQ:1}'],
            'random too long' => ['{PREFIX}{RAND:20}'],
            'bad date format' => ['{PREFIX}{DATE:H}{RAND:4}'],
        ];
    }

    #[DataProvider('invalidPatterns')]
    public function test_invalid_patterns_are_rejected(string $pattern): void
    {
        $this->expectException(InvalidArgumentException::class);

        CodePattern::parse($pattern);
    }
}

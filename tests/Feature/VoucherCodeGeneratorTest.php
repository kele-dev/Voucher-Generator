<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Services\Voucher\CodeGenerationException;
use App\Services\Voucher\CodePattern;
use App\Services\Voucher\VoucherCodeGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VoucherCodeGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private VoucherCodeGenerator $generator;

    private CarbonImmutable $date;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new VoucherCodeGenerator;
        $this->date = CarbonImmutable::create(2026, 10, 8, 10);
    }

    private function generate(string $pattern, int $count, ?CarbonImmutable $date = null): array
    {
        return DB::transaction(fn () => $this->generator->generate(CodePattern::parse($pattern), 'WN-VM', $date ?? $this->date, $count));
    }

    private ?Campaign $campaign = null;

    private function insertVoucher(string $code): void
    {
        $campaign = $this->campaign ??= Campaign::factory()->create();

        DB::table('vouchers')->insert([
            'campaign_id' => $campaign->id, 'code' => $code, 'status' => 'issued',
            'voucher_template_id' => $campaign->voucher_template_id,
            'valid_from' => now(), 'valid_until' => now()->addDay(), 'benefit_title' => 'X',
            'issued_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_sequence_continues_between_batches(): void
    {
        $first = $this->generate('{PREFIX}{DDMM}{SEQ:3}', 3);
        $second = $this->generate('{PREFIX}{DDMM}{SEQ:3}', 2);

        $this->assertSame(['WN-VM0810001', 'WN-VM0810002', 'WN-VM0810003'], $first);
        $this->assertSame(['WN-VM0810004', 'WN-VM0810005'], $second);
    }

    public function test_sequence_without_year_does_not_restart_next_year(): void
    {
        $this->generate('{PREFIX}{DDMM}{SEQ:3}', 2);
        $nextYear = $this->generate('{PREFIX}{DDMM}{SEQ:3}', 1, $this->date->addYear());

        // Same visible code space, so the counter must continue, not reset.
        $this->assertSame(['WN-VM0810003'], $nextYear);
    }

    public function test_sequence_restarts_when_visible_date_changes(): void
    {
        $this->generate('{PREFIX}{DDMM}{SEQ:3}', 2);

        $this->assertSame(['WN-VM0910001'], $this->generate('{PREFIX}{DDMM}{SEQ:3}', 1, $this->date->addDay()));
    }

    public function test_exhausted_sequence_is_refused_without_consuming_numbers(): void
    {
        $this->generate('{PREFIX}{DDMM}{SEQ:2}', 98);

        try {
            $this->generate('{PREFIX}{DDMM}{SEQ:2}', 5);
            $this->fail('Expected exhaustion.');
        } catch (CodeGenerationException $e) {
            $this->assertStringContainsString('sisa 1', $e->getMessage());
        }

        $this->assertSame(['WN-VM081099'], $this->generate('{PREFIX}{DDMM}{SEQ:2}', 1));
    }

    public function test_bulk_random_codes_are_unique_and_well_formed(): void
    {
        $codes = $this->generate('{PREFIX}-{RAND:8}', 1000);

        $this->assertCount(1000, array_unique($codes));
        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/^WN-VM-[ABCDEFGHJKMNPQRSTUVWXYZ23456789]{8}$/', $code);
        }
    }

    public function test_random_collisions_with_existing_codes_are_rerolled(): void
    {
        // RAND:2 over 31 chars = 961 codes. Occupy ~10% so 50 draws are
        // near-certain to hit some (P(no clash) ≈ 0.4%) while five re-rolls
        // still clear every clash (P(fail) ≈ 0.06%).
        $charset = config('voucher.codes.charset');
        $taken = [];
        for ($i = 0; $i < 100; $i++) {
            $code = 'WN-VM-'.$charset[intdiv($i, 31)].$charset[$i % 31];
            $this->insertVoucher($code);
            $taken[$code] = true;
        }

        $codes = $this->generate('{PREFIX}-{RAND:2}', 50);

        $this->assertCount(50, array_unique($codes));
        foreach ($codes as $code) {
            $this->assertArrayNotHasKey($code, $taken);
        }
    }

    public function test_pure_sequence_collision_cannot_be_rerolled(): void
    {
        $this->insertVoucher('WN-VM0810001');

        $this->expectException(CodeGenerationException::class);
        $this->generate('{PREFIX}{DDMM}{SEQ:3}', 1);
    }

    public function test_alpha_token_generates_pure_uppercase_alphabets(): void
    {
        $codes = $this->generate('{PREFIX}{DDMM}{SEQ:3}{ALPHA:2}', 20);

        $this->assertCount(20, array_unique($codes));
        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/^WN-VM0810\d{3}[A-Z]{2}$/', $code);
        }
        $this->assertMatchesRegularExpression('/^WN-VM0810001[A-Z]{2}$/', $codes[0]);
    }
}

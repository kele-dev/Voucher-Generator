<?php

namespace Tests\Feature;

use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesVouchers;
use Tests\TestCase;

class RemoveRedeemMigrationTest extends TestCase
{
    use CreatesVouchers, RefreshDatabase;

    public function test_redeem_schema_is_gone_and_void_columns_remain(): void
    {
        $this->assertFalse(Schema::hasTable('voucher_redemptions'));

        foreach (['redeemed_at', 'redeemed_by', 'redeemed_outlet_id', 'transaction_reference'] as $column) {
            $this->assertFalse(Schema::hasColumn('vouchers', $column), $column);
        }
        foreach (['status', 'void_reason', 'voided_by', 'voided_at'] as $column) {
            $this->assertTrue(Schema::hasColumn('vouchers', $column), $column);
        }
    }

    /**
     * Upgrade path: roll the migration back, put redeem-era data in, migrate
     * again. Redeemed vouchers (and their redemption rows) are deleted on
     * purpose; every other voucher survives untouched.
     */
    public function test_upgrade_deletes_redeemed_vouchers_and_keeps_the_rest(): void
    {
        while (! Schema::hasTable('voucher_redemptions')) {
            $this->artisan('migrate:rollback', ['--step' => 1])->assertSuccessful();
        }
        $this->assertTrue(Schema::hasTable('voucher_redemptions'));

        $campaign = $this->activeCampaign();
        $outlet = Outlet::factory()->create(['brand_id' => $campaign->brand_id]);
        $user = User::factory()->create();
        $base = [
            'campaign_id' => $campaign->id,
            'voucher_template_id' => $campaign->voucher_template_id,
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addMonth(),
            'benefit_title' => '1 PORSI',
            'issued_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('vouchers')->insert([...$base, 'code' => 'KEEP-ISSUED', 'status' => 'issued']);
        DB::table('vouchers')->insert([...$base, 'code' => 'KEEP-VOIDED', 'status' => 'voided', 'void_reason' => 'Dicetak ganda']);
        $redeemedId = DB::table('vouchers')->insertGetId([
            ...$base, 'code' => 'DROP-REDEEMED', 'status' => 'redeemed',
            'redeemed_at' => now(), 'redeemed_by' => $user->id, 'redeemed_outlet_id' => $outlet->id,
        ]);
        DB::table('voucher_redemptions')->insert([
            'voucher_id' => $redeemedId, 'outlet_id' => $outlet->id, 'user_id' => $user->id,
            'redeemed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('migrate')->assertSuccessful();

        $this->assertSame(
            ['KEEP-ISSUED' => 'issued', 'KEEP-VOIDED' => 'voided'],
            DB::table('vouchers')->orderBy('code')->pluck('status', 'code')->all(),
        );
        $this->assertSame('Dicetak ganda', DB::table('vouchers')->where('code', 'KEEP-VOIDED')->value('void_reason'));
        $this->assertFalse(Schema::hasTable('voucher_redemptions'));
        $this->assertFalse(Schema::hasColumn('vouchers', 'redeemed_at'));
    }
}

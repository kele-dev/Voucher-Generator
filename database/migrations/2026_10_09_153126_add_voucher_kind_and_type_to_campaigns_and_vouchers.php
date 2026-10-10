<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voucher_campaigns', function (Blueprint $table) {
            $table->string('voucher_kind', 20)->default('physical')->after('status');
            $table->string('voucher_type', 10)->default('VM')->after('voucher_kind');
        });

        Schema::table('vouchers', function (Blueprint $table) {
            $table->string('voucher_kind', 20)->nullable()->after('status');
            $table->string('voucher_type', 10)->nullable()->after('voucher_kind');
        });

        // Clean brand code_prefixes from legacy -VM suffix
        $prefixes = [
            'bebek-boedjang' => 'BB',
            'bakso-tyga-sapi' => 'BTS',
            'bts' => 'BTS',
            'sop-mak-garang' => 'SMG',
            'soto-semar' => 'SS',
            'kokotuku' => 'KT',
            'mie-ayam-bakso-muntjul' => 'BM',
            'ikan-bakar-muara' => 'IBM',
            'warung-nini' => 'WN',
            'ayam-goreng-makmur' => 'AGM',
            'sambal-camat' => 'SC',
        ];

        foreach ($prefixes as $slug => $code) {
            DB::table('brands')->where('slug', $slug)->update(['code_prefix' => $code]);
        }
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropColumn(['voucher_kind', 'voucher_type']);
        });

        Schema::table('voucher_campaigns', function (Blueprint $table) {
            $table->dropColumn(['voucher_kind', 'voucher_type']);
        });
    }
};

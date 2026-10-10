<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Redeem and redemption reports are no longer part of the product.
 *
 * DESTRUCTIVE: vouchers with status 'redeemed' are deleted, and the
 * voucher_redemptions table plus the redeem columns on vouchers are dropped.
 * Run `php artisan voucher:backup` and `voucher:backup:verify` first.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Redemptions reference vouchers with restrictOnDelete, so they go first.
        Schema::dropIfExists('voucher_redemptions');

        DB::table('vouchers')->where('status', 'redeemed')->delete();

        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropForeign(['redeemed_by']);
            $table->dropForeign(['redeemed_outlet_id']);
        });

        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropColumn(['redeemed_at', 'redeemed_by', 'redeemed_outlet_id', 'transaction_reference']);
        });
    }

    /**
     * Schema only; deleted vouchers and redemption history cannot be restored.
     */
    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dateTime('redeemed_at')->nullable();
            $table->foreignId('redeemed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('redeemed_outlet_id')->nullable()->constrained('outlets')->nullOnDelete();
            $table->string('transaction_reference')->nullable();
        });

        Schema::create('voucher_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('outlet_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('transaction_reference', 100)->nullable();
            $table->dateTime('redeemed_at');
            $table->timestamps();

            $table->index(['outlet_id', 'redeemed_at']);
            $table->index('redeemed_at');
        });
    }
};

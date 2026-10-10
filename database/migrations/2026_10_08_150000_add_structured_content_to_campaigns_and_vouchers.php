<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voucher_campaigns', function (Blueprint $table) {
            $table->json('menu_items')->nullable();
            $table->unsignedInteger('min_purchase')->nullable();
            $table->unsignedInteger('normal_value')->nullable();
            $table->string('service_type', 20)->default('any');
            $table->json('valid_days')->nullable();
            $table->string('valid_time_from', 5)->nullable();
            $table->string('valid_time_until', 5)->nullable();
        });

        // Issue-time copy of the structured content above. Null on vouchers
        // issued before this existed: they keep rendering exactly as before.
        Schema::table('vouchers', function (Blueprint $table) {
            $table->json('content_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', fn (Blueprint $table) => $table->dropColumn('content_snapshot'));

        Schema::table('voucher_campaigns', function (Blueprint $table) {
            $table->dropColumn(['menu_items', 'min_purchase', 'normal_value', 'service_type', 'valid_days', 'valid_time_from', 'valid_time_until']);
        });
    }
};

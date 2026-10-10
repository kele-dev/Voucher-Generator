<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('voucher_campaigns')->restrictOnDelete();
            $table->foreignId('generation_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 40)->unique();
            // Stored: issued | redeemed | voided. "expired" is derived from valid_until.
            $table->string('status', 16)->default('issued');

            // Snapshot taken at issue time. Vouchers are re-rendered on demand,
            // so everything that shapes the image is frozen here, not read
            // from the (editable) campaign.
            $table->foreignId('voucher_template_id')->constrained()->restrictOnDelete();
            $table->dateTime('valid_from');
            $table->dateTime('valid_until');
            $table->string('benefit_title');
            $table->string('benefit_description')->nullable();
            $table->text('terms')->nullable();
            $table->string('outlet_scope_text')->nullable();
            $table->string('campaign_title')->nullable();
            $table->string('product_image_path')->nullable();

            $table->dateTime('issued_at');
            $table->dateTime('redeemed_at')->nullable();
            $table->foreignId('redeemed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('redeemed_outlet_id')->nullable()->constrained('outlets')->nullOnDelete();
            $table->string('transaction_reference')->nullable();
            $table->string('void_reason')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('voided_at')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'status']);
            $table->index('generation_batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};

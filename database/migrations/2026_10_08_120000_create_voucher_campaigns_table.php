<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voucher_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->restrictOnDelete();
            $table->foreignId('voucher_template_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('benefit_title');
            $table->string('benefit_description')->nullable();
            $table->text('terms')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('expires_at');
            $table->string('status', 16)->default('draft');
            $table->string('code_prefix', 20);
            $table->string('code_pattern', 60);
            $table->boolean('applies_to_all_outlets')->default(true);
            // Maximum number of vouchers this campaign may ever issue; null = no cap.
            $table->unsignedInteger('generation_limit')->nullable();
            $table->string('product_image_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['brand_id', 'status']);
        });

        Schema::create('campaign_outlets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('voucher_campaigns')->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['campaign_id', 'outlet_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_outlets');
        Schema::dropIfExists('voucher_campaigns');
    }
};

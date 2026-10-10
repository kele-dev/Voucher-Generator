<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generation_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('voucher_campaigns')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('idempotency_key', 64)->unique();
            $table->unsignedInteger('requested_count');
            $table->unsignedInteger('generated_count')->default(0);
            $table->string('status', 16)->default('pending');
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            // ZIP export runs after codes exist and can be repeated without
            // touching the vouchers themselves.
            $table->string('export_status', 16)->nullable();
            $table->string('export_format', 4)->default('jpg');
            $table->unsignedTinyInteger('export_scale')->default(100);
            $table->unsignedInteger('exported_count')->default(0);
            $table->string('export_path')->nullable();
            $table->text('export_error')->nullable();
            $table->timestamp('exported_at')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'status']);
        });

        Schema::create('code_sequences', function (Blueprint $table) {
            $table->id();
            // Code skeleton with SEQ/RAND blanked out, e.g. "WN-VM0810####***".
            $table->string('key', 80)->unique();
            $table->unsignedBigInteger('last_value')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('code_sequences');
        Schema::dropIfExists('generation_batches');
    }
};

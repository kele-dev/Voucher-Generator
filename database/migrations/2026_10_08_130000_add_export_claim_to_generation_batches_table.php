<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generation_batches', function (Blueprint $table) {
            // Token of the export job currently allowed to write results.
            $table->string('export_claim', 40)->nullable()->after('export_status');
        });
    }

    public function down(): void
    {
        Schema::table('generation_batches', function (Blueprint $table) {
            $table->dropColumn('export_claim');
        });
    }
};

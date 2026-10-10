<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->string('template_physical_path')->nullable()->after('logo_path');
            $table->string('template_electronic_path')->nullable()->after('template_physical_path');
            $table->string('template_nominal_path')->nullable()->after('template_electronic_path');
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn([
                'template_physical_path',
                'template_electronic_path',
                'template_nominal_path',
            ]);
        });
    }
};

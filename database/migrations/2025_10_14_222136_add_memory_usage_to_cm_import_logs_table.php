<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cm_import_logs', function (Blueprint $table) {
            $table->string('memory_peak')->nullable()->after('failed_count');
            $table->string('memory_current')->nullable()->after('memory_peak');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cm_import_logs', function (Blueprint $table) {
            $table->dropColumn(['memory_peak', 'memory_current']);
        });
    }
};

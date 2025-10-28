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
        Schema::table('cm_custom_fields', function (Blueprint $table) {
            $table->string('external_key')->nullable()->after('last_seen_at');
            $table->index('external_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cm_custom_fields', function (Blueprint $table) {
            $table->dropIndex(['external_key']);
            $table->dropColumn('external_key');
        });
    }
};

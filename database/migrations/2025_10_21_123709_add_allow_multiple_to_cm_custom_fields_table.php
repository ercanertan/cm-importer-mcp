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
            $table->boolean('allow_multiple')->default(false)->after('data_type')
                ->comment('Allow multiple selections for multi_select fields');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cm_custom_fields', function (Blueprint $table) {
            $table->dropColumn('allow_multiple');
        });
    }
};

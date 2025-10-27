<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, update existing data to match new enum values
        DB::table('cm_custom_fields')->where('data_type', 'text')->update(['data_type' => 'Text']);
        DB::table('cm_custom_fields')->where('data_type', 'number')->update(['data_type' => 'Number']);
        DB::table('cm_custom_fields')->where('data_type', 'date')->update(['data_type' => 'Date']);
        DB::table('cm_custom_fields')->where('data_type', 'country')->update(['data_type' => 'Country']);
        DB::table('cm_custom_fields')->where('data_type', 'multi_select')->update(['data_type' => 'MultiSelectOne']);

        // Now alter the column to use new enum values
        Schema::table('cm_custom_fields', function (Blueprint $table) {
            $table->enum('data_type', ['Text', 'Number', 'Date', 'MultiSelectOne', 'MultiSelectMany', 'Country'])->default('Text')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert data to lowercase values
        DB::table('cm_custom_fields')->where('data_type', 'Text')->update(['data_type' => 'text']);
        DB::table('cm_custom_fields')->where('data_type', 'Number')->update(['data_type' => 'number']);
        DB::table('cm_custom_fields')->where('data_type', 'Date')->update(['data_type' => 'date']);
        DB::table('cm_custom_fields')->where('data_type', 'Country')->update(['data_type' => 'country']);
        DB::table('cm_custom_fields')->whereIn('data_type', ['MultiSelectOne', 'MultiSelectMany'])->update(['data_type' => 'multi_select']);

        // Revert column to old enum values
        Schema::table('cm_custom_fields', function (Blueprint $table) {
            $table->enum('data_type', ['text', 'number', 'date', 'multi_select', 'country'])->default('text')->change();
        });
    }
};

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
            $table->integer('total_chunks')->default(1)->after('total_rows');
            $table->integer('completed_chunks')->default(0)->after('total_chunks');
            $table->integer('failed_chunks')->default(0)->after('completed_chunks');
            $table->boolean('is_chunked')->default(false)->after('failed_chunks');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cm_import_logs', function (Blueprint $table) {
            $table->dropColumn(['total_chunks', 'completed_chunks', 'failed_chunks', 'is_chunked']);
        });
    }
};

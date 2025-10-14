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
            $table->string('file_hash', 64)->nullable()->after('filename');
            $table->string('storage_path')->nullable()->after('file_hash');
            $table->index('file_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cm_import_logs', function (Blueprint $table) {
            // Drop columns if they exist (indexes are automatically dropped with columns)
            if (Schema::hasColumn('cm_import_logs', 'file_hash')) {
                $table->dropColumn('file_hash');
            }
            if (Schema::hasColumn('cm_import_logs', 'storage_path')) {
                $table->dropColumn('storage_path');
            }
        });
    }
};

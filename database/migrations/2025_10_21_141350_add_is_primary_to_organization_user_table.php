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
        Schema::table('organization_user', function (Blueprint $table) {
            $table->boolean('is_primary')->default(false)->after('is_manual')
                ->comment('Indicates if this is the primary organization for the user');

            // Index for faster queries on primary organization
            $table->index(['user_id', 'is_primary'], 'idx_user_primary_org');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organization_user', function (Blueprint $table) {
            $table->dropIndex('idx_user_primary_org');
            $table->dropColumn('is_primary');
        });
    }
};

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
            // Composite index for filtering by organization_id and is_manual together
            // This speeds up queries like: WHERE organization_id = X AND is_manual = false
            $table->index(['organization_id', 'is_manual'], 'org_user_org_id_is_manual_idx');

            // Index on user_id for reverse lookups (finding all orgs for a user)
            // This is useful for user detail pages
            $table->index('user_id', 'org_user_user_id_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organization_user', function (Blueprint $table) {
            $table->dropIndex('org_user_org_id_is_manual_idx');
            $table->dropIndex('org_user_user_id_idx');
        });
    }
};

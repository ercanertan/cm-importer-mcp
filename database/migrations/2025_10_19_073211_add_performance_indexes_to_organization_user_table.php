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
            // Note: We can't drop org_user_org_id_is_manual_idx because organization_id
            // is part of a foreign key constraint. MySQL creates its own index for foreign keys.
            // If you really need to drop this, you would need to:
            // 1. Drop the foreign key constraint first
            // 2. Drop the index
            // 3. Recreate the foreign key constraint
            // But for performance indexes, it's generally safe to leave them.

            // Only drop the user_id index as it's not part of any foreign key
            $table->dropIndex('org_user_user_id_idx');
        });
    }
};

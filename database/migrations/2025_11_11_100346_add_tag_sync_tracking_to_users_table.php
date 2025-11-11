<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add fields to track when users need tag sync to Campaign Monitor.
     * This enables bulk tag syncing to avoid API call storms.
     *
     * Strategy:
     * - When user tier/engagement/status changes, set cm_tags_need_sync = true
     * - Scheduled job runs every 5-15 minutes to batch sync tags
     * - Result: 2,847 users = 6 API calls (not 2,847!)
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Flag to mark user needs tag sync
            $table->boolean('cm_tags_need_sync')->default(false)->after('temp_campaign_tag');

            // Timestamp of last successful tag sync
            $table->timestamp('cm_tags_synced_at')->nullable()->after('cm_tags_need_sync');

            // Index for efficient querying of users needing sync
            $table->index('cm_tags_need_sync', 'idx_users_cm_tags_need_sync');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_cm_tags_need_sync');
            $table->dropColumn(['cm_tags_need_sync', 'cm_tags_synced_at']);
        });
    }
};

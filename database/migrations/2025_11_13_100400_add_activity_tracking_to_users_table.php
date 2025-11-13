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
        Schema::table('users', function (Blueprint $table) {
            // Activity scoring fields
            if (!Schema::hasColumn('users', 'activity_score_7d')) {
                $table->integer('activity_score_7d')->default(0)->after('cm_unsubscribed_at');
            }
            if (!Schema::hasColumn('users', 'activity_score_30d')) {
                $table->integer('activity_score_30d')->default(0)->after('activity_score_7d');
            }
            if (!Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('activity_score_30d');
            }

            // Campaign Monitor sync tracking
            if (!Schema::hasColumn('users', 'cm_synced_at')) {
                $table->timestamp('cm_synced_at')->nullable()->after('last_login_at');
            }

            // Account status for CM
            if (!Schema::hasColumn('users', 'account_status')) {
                $table->string('account_status')->default('active')->after('cm_synced_at');
            }
        });

        // Add indexes separately to avoid duplicate index errors
        Schema::table('users', function (Blueprint $table) {
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexes = $sm->listTableIndexes('users');

            if (!isset($indexes['users_activity_score_7d_index'])) {
                $table->index('activity_score_7d');
            }
            if (!isset($indexes['users_activity_score_30d_index'])) {
                $table->index('activity_score_30d');
            }
            if (!isset($indexes['users_last_login_at_index'])) {
                $table->index('last_login_at');
            }
            if (!isset($indexes['users_cm_synced_at_index'])) {
                $table->index('cm_synced_at');
            }
            if (!isset($indexes['users_account_status_index'])) {
                $table->index('account_status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['activity_score_7d']);
            $table->dropIndex(['activity_score_30d']);
            $table->dropIndex(['last_login_at']);
            $table->dropIndex(['cm_synced_at']);
            $table->dropIndex(['account_status']);

            $table->dropColumn([
                'activity_score_7d',
                'activity_score_30d',
                'last_login_at',
                'cm_synced_at',
                'account_status',
            ]);
        });
    }
};

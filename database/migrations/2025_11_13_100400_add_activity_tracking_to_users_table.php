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
            $table->integer('activity_score_7d')->default(0)->after('cm_unsubscribed_at');
            $table->integer('activity_score_30d')->default(0)->after('activity_score_7d');
            $table->timestamp('last_login_at')->nullable()->after('activity_score_30d');
            $table->timestamp('cm_synced_at')->nullable()->after('last_login_at');
            $table->string('account_status')->default('active')->after('cm_synced_at');

            $table->index('activity_score_7d');
            $table->index('activity_score_30d');
            $table->index('last_login_at');
            $table->index('cm_synced_at');
            $table->index('account_status');
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

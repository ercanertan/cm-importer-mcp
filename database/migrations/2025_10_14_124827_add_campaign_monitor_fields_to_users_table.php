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
            if (!Schema::hasColumn('users', 'cm_subscriber_id')) {
                $table->string('cm_subscriber_id')->nullable()->unique()->comment('Campaign Monitor Subscriber ID');
            }
            if (!Schema::hasColumn('users', 'cm_status')) {
                $table->enum('cm_status', ['active', 'unsubscribed', 'bounced', 'deleted'])->default('active');
            }
            if (!Schema::hasColumn('users', 'cm_subscribed_at')) {
                $table->timestamp('cm_subscribed_at')->nullable();
            }
            if (!Schema::hasColumn('users', 'cm_unsubscribed_at')) {
                $table->timestamp('cm_unsubscribed_at')->nullable();
            }
            if (!Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // Add indexes separately to avoid duplicate index errors
        Schema::table('users', function (Blueprint $table) {
            // Check if index exists using raw SQL query (Laravel 11+ compatible)
            $connection = Schema::getConnection();
            $schemaName = $connection->getDatabaseName();

            // Check cm_subscriber_id index
            $indexExists = $connection->select(
                "SELECT COUNT(*) as count FROM information_schema.statistics
                WHERE table_schema = ? AND table_name = 'users' AND index_name = 'users_cm_subscriber_id_index'",
                [$schemaName]
            );

            if ($indexExists[0]->count == 0) {
                $table->index('cm_subscriber_id');
            }

            // Check cm_status index
            $indexExists = $connection->select(
                "SELECT COUNT(*) as count FROM information_schema.statistics
                WHERE table_schema = ? AND table_name = 'users' AND index_name = 'users_cm_status_index'",
                [$schemaName]
            );

            if ($indexExists[0]->count == 0) {
                $table->index('cm_status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['cm_subscriber_id']);
            $table->dropIndex(['cm_status']);
            $table->dropColumn(['cm_subscriber_id', 'cm_status', 'cm_subscribed_at', 'cm_unsubscribed_at', 'deleted_at']);
        });
    }
};

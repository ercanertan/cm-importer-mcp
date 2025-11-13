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
            $table->string('cm_subscriber_id')->nullable()->unique()->comment('Campaign Monitor Subscriber ID');
            $table->enum('cm_status', ['active', 'unsubscribed', 'bounced', 'deleted'])->default('active');
            $table->timestamp('cm_subscribed_at')->nullable();
            $table->timestamp('cm_unsubscribed_at')->nullable();
            $table->softDeletes();

            $table->index('cm_subscriber_id');
            $table->index('cm_status');
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

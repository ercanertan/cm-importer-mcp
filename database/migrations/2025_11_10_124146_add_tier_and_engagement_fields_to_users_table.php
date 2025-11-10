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
            // Tier field for subscription level
            $table->string('tier')->nullable()->after('permission_to_track')->index()
                ->comment('Subscription tier: free, paid_pro, paid_premium, enterprise');

            // Email engagement tracking metrics
            $table->integer('total_emails_sent')->default(0)->after('tier')
                ->comment('Total number of emails sent to this user');
            $table->integer('total_opens')->default(0)->after('total_emails_sent')
                ->comment('Total number of email opens');
            $table->integer('total_clicks')->default(0)->after('total_opens')
                ->comment('Total number of email clicks');
            $table->integer('total_bounces')->default(0)->after('total_clicks')
                ->comment('Total number of email bounces');

            // Engagement score (0-100)
            $table->decimal('engagement_score', 5, 2)->default(0)->after('total_bounces')->index()
                ->comment('Calculated engagement score (0-100)');

            // Engagement timestamps
            $table->timestamp('last_email_opened_at')->nullable()->after('engagement_score')->index()
                ->comment('Last time user opened an email');
            $table->timestamp('last_email_clicked_at')->nullable()->after('last_email_opened_at')
                ->comment('Last time user clicked a link in an email');
            $table->timestamp('last_activity_at')->nullable()->after('last_email_clicked_at')->index()
                ->comment('Last time user had any activity (open, click, etc.)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'tier',
                'total_emails_sent',
                'total_opens',
                'total_clicks',
                'total_bounces',
                'engagement_score',
                'last_email_opened_at',
                'last_email_clicked_at',
                'last_activity_at',
            ]);
        });
    }
};

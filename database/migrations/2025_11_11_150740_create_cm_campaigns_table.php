<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Create table to store Campaign Monitor campaign statistics.
     * This allows us to track campaign performance and link opens/clicks to specific campaigns.
     */
    public function up(): void
    {
        Schema::create('cm_campaigns', function (Blueprint $table) {
            $table->id();

            // Campaign Monitor IDs
            $table->string('cm_campaign_id')->unique()->comment('Campaign Monitor campaign ID');
            $table->string('cm_list_id')->nullable()->comment('Campaign Monitor list ID this campaign was sent to');

            // Campaign details
            $table->string('name')->comment('Campaign name');
            $table->string('subject')->nullable()->comment('Email subject line');
            $table->string('from_name')->nullable()->comment('Sender name');
            $table->string('from_email')->nullable()->comment('Sender email');
            $table->string('reply_to')->nullable()->comment('Reply-to email');

            // Campaign metadata
            $table->timestamp('sent_at')->nullable()->comment('When campaign was sent');
            $table->string('status')->default('draft')->comment('Campaign status: draft, scheduled, sent, bounced');
            $table->text('web_version_url')->nullable()->comment('URL to web version of campaign');
            $table->text('web_version_text_url')->nullable()->comment('URL to text version');

            // Statistics
            $table->integer('total_recipients')->default(0)->comment('Total number of recipients');
            $table->integer('total_opens')->default(0)->comment('Total opens (including multiple opens)');
            $table->integer('unique_opens')->default(0)->comment('Unique recipients who opened');
            $table->integer('total_clicks')->default(0)->comment('Total clicks (including multiple clicks)');
            $table->integer('unique_clicks')->default(0)->comment('Unique recipients who clicked');
            $table->integer('total_bounces')->default(0)->comment('Total bounced emails');
            $table->integer('total_unsubscribes')->default(0)->comment('Total unsubscribes');
            $table->integer('total_spam_complaints')->default(0)->comment('Total spam complaints');
            $table->integer('forwards')->default(0)->comment('Number of times forwarded');
            $table->integer('likes')->default(0)->comment('Number of likes (social)');
            $table->integer('mentions')->default(0)->comment('Number of mentions (social)');

            // Calculated metrics
            $table->decimal('open_rate', 5, 2)->default(0)->comment('Open rate percentage');
            $table->decimal('click_rate', 5, 2)->default(0)->comment('Click rate percentage');
            $table->decimal('bounce_rate', 5, 2)->default(0)->comment('Bounce rate percentage');
            $table->decimal('unsubscribe_rate', 5, 2)->default(0)->comment('Unsubscribe rate percentage');

            // Sync tracking
            $table->timestamp('stats_last_synced_at')->nullable()->comment('When stats were last synced from CM');
            $table->boolean('is_active')->default(true)->comment('Whether to actively track this campaign');

            $table->timestamps();

            // Indexes
            $table->index('sent_at');
            $table->index('status');
            $table->index('cm_list_id');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cm_campaigns');
    }
};

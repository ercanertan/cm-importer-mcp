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
        Schema::create('email_engagements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade')
                ->comment('User who engaged with the email');

            // Campaign information
            $table->string('campaign_id')->nullable()->index()
                ->comment('Campaign Monitor campaign ID');
            $table->string('campaign_name')->nullable()
                ->comment('Human-readable campaign name');

            // Event type
            $table->enum('event_type', ['sent', 'open', 'click', 'bounce', 'unsubscribe'])->index()
                ->comment('Type of email engagement event');

            // Click-specific data
            $table->text('url')->nullable()
                ->comment('URL clicked (for click events)');

            // Bounce-specific data
            $table->enum('bounce_type', ['hard', 'soft'])->nullable()
                ->comment('Type of bounce (hard or soft)');
            $table->text('bounce_reason')->nullable()
                ->comment('Reason for bounce');

            // Additional metadata
            $table->string('ip_address', 45)->nullable()
                ->comment('IP address of the engagement');
            $table->string('user_agent')->nullable()
                ->comment('Browser/email client user agent');
            $table->json('event_data')->nullable()
                ->comment('Full webhook payload for debugging');

            // When the engagement occurred
            $table->timestamp('occurred_at')->index()
                ->comment('When the engagement event occurred');

            $table->timestamps();

            // Indexes for common queries
            $table->index(['user_id', 'event_type']);
            $table->index(['campaign_id', 'event_type']);
            $table->index(['user_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_engagements');
    }
};

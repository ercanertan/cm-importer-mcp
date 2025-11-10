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
        Schema::create('events', function (Blueprint $table) {
            $table->id();

            // Event details
            $table->string('name')->index()
                ->comment('Event name (e.g., "Annual Conference 2024")');
            $table->text('description')->nullable()
                ->comment('Detailed event description');
            $table->string('type')->index()
                ->comment('Event type (webinar, conference, workshop, training, etc.)');
            $table->string('slug')->unique()
                ->comment('URL-friendly identifier');

            // Event timing
            $table->timestamp('starts_at')->index()
                ->comment('When the event starts');
            $table->timestamp('ends_at')->nullable()->index()
                ->comment('When the event ends');
            $table->string('timezone')->default('UTC')
                ->comment('Event timezone');

            // Event location (can be virtual or physical)
            $table->string('location_type')->default('virtual')
                ->comment('virtual, physical, or hybrid');
            $table->string('location_name')->nullable()
                ->comment('Venue name or platform (e.g., "Zoom", "Convention Center")');
            $table->text('location_address')->nullable()
                ->comment('Physical address if applicable');
            $table->text('location_url')->nullable()
                ->comment('Virtual meeting URL or event page URL');

            // Registration settings
            $table->boolean('requires_registration')->default(true)
                ->comment('Whether users must register to attend');
            $table->integer('max_attendees')->nullable()
                ->comment('Maximum number of attendees (null = unlimited)');
            $table->timestamp('registration_opens_at')->nullable()->index()
                ->comment('When registration opens');
            $table->timestamp('registration_closes_at')->nullable()->index()
                ->comment('When registration closes');

            // Event status
            $table->enum('status', ['draft', 'published', 'cancelled', 'completed'])->default('draft')->index()
                ->comment('Current event status');

            // Organizer information
            $table->foreignId('organizer_id')->nullable()->constrained('users')->nullOnDelete()
                ->comment('User who created/manages this event');
            $table->string('organizer_name')->nullable()
                ->comment('External organizer name if not a system user');
            $table->string('organizer_email')->nullable()
                ->comment('Contact email for event organizer');

            // Additional metadata
            $table->json('metadata')->nullable()
                ->comment('Additional event data (speakers, agenda, etc.)');

            $table->timestamps();
            $table->softDeletes();

            // Composite indexes for common queries
            $table->index(['status', 'starts_at']);
            $table->index(['type', 'starts_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};

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
        Schema::create('event_attendances', function (Blueprint $table) {
            $table->id();

            // Foreign keys
            $table->foreignId('event_id')->constrained()->onDelete('cascade')
                ->comment('Event being attended');
            $table->foreignId('user_id')->constrained()->onDelete('cascade')
                ->comment('User attending the event');

            // Registration status
            $table->enum('status', ['registered', 'attended', 'cancelled', 'no_show'])->default('registered')->index()
                ->comment('Attendance status');

            // Timestamps for attendance tracking
            $table->timestamp('registered_at')->nullable()->index()
                ->comment('When the user registered for the event');
            $table->timestamp('attended_at')->nullable()->index()
                ->comment('When the user actually attended (checked in)');
            $table->timestamp('cancelled_at')->nullable()
                ->comment('When the registration was cancelled');

            // Attendance confirmation
            $table->boolean('attendance_confirmed')->default(false)->index()
                ->comment('Whether attendance was verified (e.g., via check-in)');

            // Additional metadata
            $table->string('registration_source')->nullable()
                ->comment('How they registered (web, api, admin, import, etc.)');
            $table->json('metadata')->nullable()
                ->comment('Additional attendance data (notes, questions answered, etc.)');

            $table->timestamps();

            // Prevent duplicate registrations
            $table->unique(['event_id', 'user_id'], 'unique_event_user');

            // Composite indexes for common queries
            $table->index(['event_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['event_id', 'attendance_confirmed']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_attendances');
    }
};

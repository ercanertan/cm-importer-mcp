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
        Schema::create('cm_import_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('filename')->nullable();
            $table->string('file_hash', 64)->nullable();
            $table->string('storage_path')->nullable();
            $table->enum('file_type', ['all', 'active', 'bounced', 'deleted', 'unsubscribed'])->default('all');
            $table->enum('status', ['pending', 'queued', 'processing', 'completed', 'failed'])->default('pending');
            $table->integer('total_rows')->default(0);
            $table->integer('total_chunks')->default(1);
            $table->integer('completed_chunks')->default(0);
            $table->integer('failed_chunks')->default(0);
            $table->boolean('is_chunked')->default(false);
            $table->integer('processed_rows')->default(0);
            $table->integer('created_count')->default(0);
            $table->integer('updated_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->string('memory_peak')->nullable();
            $table->string('memory_current')->nullable();
            $table->json('custom_fields_detected')->nullable();
            $table->json('error_details')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
            $table->index('created_at');
            $table->index('file_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cm_import_logs');
    }
};

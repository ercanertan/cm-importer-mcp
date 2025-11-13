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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "Newsletter", "Weekly Digest"
            $table->string('slug')->unique(); // e.g., "newsletter", "weekly-digest"
            $table->text('description')->nullable();
            $table->string('category')->nullable(); // e.g., "Email", "Reports", "Notifications"
            $table->boolean('is_active')->default(true);
            $table->boolean('default_opt_in')->default(false); // Auto-subscribe new users?
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('slug');
            $table->index('is_active');
            $table->index(['category', 'is_active']);
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};

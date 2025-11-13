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
        Schema::create('tiers', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "Free", "Pro", "Enterprise"
            $table->string('slug')->unique(); // e.g., "free", "pro", "enterprise"
            $table->decimal('price', 10, 2)->default(0.00); // Monthly price
            $table->text('description')->nullable();
            $table->json('features')->nullable(); // JSON array of feature names
            $table->integer('max_users')->nullable(); // Max users per org, null = unlimited
            $table->integer('max_products')->nullable(); // Max products, null = unlimited
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0); // For display ordering
            $table->timestamps();

            $table->index('slug');
            $table->index('is_active');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tiers');
    }
};

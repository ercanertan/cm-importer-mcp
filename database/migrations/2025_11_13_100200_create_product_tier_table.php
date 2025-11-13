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
        Schema::create('product_tier', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('tier_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            // Ensure unique product-tier combinations
            $table->unique(['product_id', 'tier_id']);

            // Indexes for efficient lookups
            $table->index('product_id');
            $table->index('tier_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_tier');
    }
};

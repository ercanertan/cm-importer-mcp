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
        Schema::create('user_product_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->boolean('is_active')->default(true);
            $table->timestamp('subscribed_at')->useCurrent();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->string('source')->default('manual'); // manual, auto, import, admin
            $table->text('notes')->nullable();
            $table->timestamps();

            // Ensure unique user-product combinations
            $table->unique(['user_id', 'product_id']);

            // Composite indexes for efficient queries
            $table->index(['user_id', 'is_active']);
            $table->index(['product_id', 'is_active']);
            $table->index(['user_id', 'product_id', 'is_active'], 'ups_user_product_active_idx');

            // Index for filtering opted-out users
            $table->index('unsubscribed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_product_subscriptions');
    }
};

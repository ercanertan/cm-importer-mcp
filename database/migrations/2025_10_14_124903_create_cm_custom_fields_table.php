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
        Schema::create('cm_custom_fields', function (Blueprint $table) {
            $table->id();
            $table->string('field_key')->unique()->comment('Campaign Monitor field key');
            $table->string('field_name')->comment('Human readable field name');
            $table->enum('data_type', ['text', 'number', 'date', 'multi_select', 'country'])->default('text');
            $table->json('options')->nullable()->comment('For multi-select fields');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable()->comment('Last time this field appeared in import');
            $table->timestamps();

            $table->index('field_key');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cm_custom_fields');
    }
};

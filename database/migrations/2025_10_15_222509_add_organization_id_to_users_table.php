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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained()->onDelete('set null');
            $table->foreignId('domain_id')->nullable()->after('organization_id')->constrained()->onDelete('set null');

            // Indexes
            $table->index('organization_id');
            $table->index('domain_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropForeign(['domain_id']);
            $table->dropIndex(['organization_id']);
            $table->dropIndex(['domain_id']);
            $table->dropColumn(['organization_id', 'domain_id']);
        });
    }
};

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
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('tier')->nullable()->after('is_active')->index()
                ->comment('Organization tier: free, paid_pro, paid_premium, enterprise');
        });

        // Sync user tiers from their primary organization
        // This will be done via a separate command to avoid timeout
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('tier');
        });
    }
};

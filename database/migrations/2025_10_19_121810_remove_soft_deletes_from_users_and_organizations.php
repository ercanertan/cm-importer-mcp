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
        // Drop deleted_at column from users table if it exists
        if (Schema::hasColumn('users', 'deleted_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('deleted_at');
            });
        }

        // Drop deleted_at column from organizations table if it exists
        if (Schema::hasColumn('organizations', 'deleted_at')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->dropColumn('deleted_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add deleted_at column back to users table
        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Add deleted_at column back to organizations table
        Schema::table('organizations', function (Blueprint $table) {
            $table->softDeletes();
        });
    }
};

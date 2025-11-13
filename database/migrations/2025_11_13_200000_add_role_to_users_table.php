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
            $table->enum('role', ['user', 'org_admin', 'super_admin'])
                  ->default('user')
                  ->after('email')
                  ->comment('User role for authorization');

            $table->foreignId('organization_id')
                  ->nullable()
                  ->after('role')
                  ->constrained()
                  ->nullOnDelete()
                  ->comment('Primary organization for user');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropColumn(['role', 'organization_id']);
        });
    }
};

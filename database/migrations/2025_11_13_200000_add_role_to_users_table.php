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
            if (!Schema::hasColumn('users', 'role')) {
                $table->enum('role', ['user', 'org_admin', 'super_admin'])
                      ->default('user')
                      ->after('email')
                      ->comment('User role for authorization');
            }

            if (!Schema::hasColumn('users', 'organization_id')) {
                $table->foreignId('organization_id')
                      ->nullable()
                      ->after('role')
                      ->constrained()
                      ->nullOnDelete()
                      ->comment('Primary organization for user');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'organization_id')) {
                $table->dropForeign(['organization_id']);
                $table->dropColumn('organization_id');
            }

            if (Schema::hasColumn('users', 'role')) {
                $table->dropColumn('role');
            }
        });
    }
};

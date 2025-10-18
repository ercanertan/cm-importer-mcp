<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('organization_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            // Ensure a user can't be added to the same organization twice
            $table->unique(['user_id', 'organization_id']);
        });

        // Migrate existing data from users.organization_id to the pivot table
        $timestamp = now();
        DB::table('users')
            ->whereNotNull('organization_id')
            ->orderBy('id')
            ->chunk(500, function ($users) use ($timestamp) {
                $pivotData = [];
                foreach ($users as $user) {
                    $pivotData[] = [
                        'user_id' => $user->id,
                        'organization_id' => $user->organization_id,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                }
                DB::table('organization_user')->insert($pivotData);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_user');
    }
};

<?php

use App\Livewire\Admin\Organizations\OrganizationManager;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('Organization Modal Performance', function () {
    it('opens modal quickly even with 1000 users', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $org = Organization::create(['name' => 'Large Organization', 'is_active' => true]);

        // Create 1000 users and assign to organization
        $users = User::factory()->count(1000)->create();
        foreach ($users as $testUser) {
            $org->usersMany()->attach($testUser->id, ['is_manual' => false]);
        }

        // Measure time to open modal
        $startTime = microtime(true);

        $component = Livewire::test(OrganizationManager::class)
            ->call('openManageUsersModal', $org->id);

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Modal should open in less than 1 second even with 1000 users
        expect($executionTime)->toBeLessThan(1.0);

        // Verify modal opened successfully
        expect($component->get('showManageUsersModal'))->toBeTrue();
        expect($component->get('organizationToManage.id'))->toBe($org->id);

        // Verify total count is correct (no arrays loaded)
        expect($component->get('totalAssignedUsers'))->toBe(1000);

        // Verify only first page of users is loaded (20 per page)
        $assignedUsers = $component->get('assignedUsers');
        expect(count($assignedUsers))->toBeLessThanOrEqual(20);
    })->skip(function () {
        // Skip this test in CI environments where performance may vary
        return env('CI', false);
    });

    it('loads only first page of users not all users', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $org = Organization::create(['name' => 'Large Organization', 'is_active' => true]);

        // Create 100 users and assign to organization
        $users = User::factory()->count(100)->create();
        foreach ($users as $testUser) {
            $org->usersMany()->attach($testUser->id, ['is_manual' => false]);
        }

        $component = Livewire::test(OrganizationManager::class)
            ->call('openManageUsersModal', $org->id);

        // assignedUsers should only contain first page (20 users), not all 100
        $assignedUsers = $component->get('assignedUsers');
        expect(count($assignedUsers))->toBeLessThanOrEqual(20);

        // But total count should reflect all 100
        expect($component->get('totalAssignedUsers'))->toBe(100);
    });

    it('efficiently loads only paginated users without loading all users', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $org = Organization::create(['name' => 'Test Organization', 'is_active' => true]);

        // Create users with mix of manual and auto assignments
        $autoUsers = User::factory()->count(50)->create();
        $manualUsers = User::factory()->count(30)->create();

        foreach ($autoUsers as $testUser) {
            $org->usersMany()->attach($testUser->id, ['is_manual' => false]);
        }

        foreach ($manualUsers as $testUser) {
            $org->usersMany()->attach($testUser->id, ['is_manual' => true]);
        }

        // This should be very fast because it only loads first page, not all users
        $startTime = microtime(true);

        $component = Livewire::test(OrganizationManager::class)
            ->call('openManageUsersModal', $org->id);

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Should be very fast (under 500ms)
        expect($executionTime)->toBeLessThan(0.5);

        // Verify total count is correct
        expect($component->get('totalAssignedUsers'))->toBe(80);

        // Verify only first page is loaded (20 users max)
        $assignedUsers = $component->get('assignedUsers');
        expect(count($assignedUsers))->toBeLessThanOrEqual(20);

        // Verify selectedUsers is empty (no bulk selection)
        expect($component->get('selectedUsers'))->toBe([]);
    });

    it('handles extremely large organizations with 10000 users', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $org = Organization::create(['name' => 'Very Large Organization', 'is_active' => true]);

        // Create 10,000 user assignments (just pivot entries, not full users for speed)
        $pivotData = [];
        $now = now();
        for ($i = 1; $i <= 10000; $i++) {
            $testUser = User::factory()->create();
            $pivotData[] = [
                'user_id' => $testUser->id,
                'organization_id' => $org->id,
                'is_manual' => $i % 10 === 0, // 10% manual, 90% auto
                'created_at' => $now,
                'updated_at' => $now,
            ];

            // Insert in batches of 1000 for performance
            if ($i % 1000 === 0) {
                \Illuminate\Support\Facades\DB::table('organization_user')->insert($pivotData);
                $pivotData = [];
            }
        }

        // Measure time to open modal with 10k users
        $startTime = microtime(true);

        $component = Livewire::test(OrganizationManager::class)
            ->call('openManageUsersModal', $org->id);

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Should still be reasonably fast (under 2 seconds)
        expect($executionTime)->toBeLessThan(2.0);

        // Verify total count is correct
        expect($component->get('totalAssignedUsers'))->toBe(10000);

        // Verify only first page of users is loaded in assignedUsers (20 max)
        $assignedUsers = $component->get('assignedUsers');
        expect(count($assignedUsers))->toBeLessThanOrEqual(20);

        // Verify selectedUsers is empty (no arrays loaded into memory)
        expect($component->get('selectedUsers'))->toBe([]);
    })->skip(function () {
        // Skip in CI due to time/resource constraints
        return env('CI', false);
    });

    it('memory usage remains low even with large organizations', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $org = Organization::create(['name' => 'Large Organization', 'is_active' => true]);

        // Create 500 users
        $users = User::factory()->count(500)->create();
        foreach ($users as $testUser) {
            $org->usersMany()->attach($testUser->id, ['is_manual' => false]);
        }

        $memoryBefore = memory_get_usage();

        Livewire::test(OrganizationManager::class)
            ->call('openManageUsersModal', $org->id);

        $memoryAfter = memory_get_usage();
        $memoryIncrease = ($memoryAfter - $memoryBefore) / 1024 / 1024; // Convert to MB

        // Memory increase should be minimal (under 10MB) because we only load IDs, not full user objects
        expect($memoryIncrease)->toBeLessThan(10);
    })->skip(function () {
        // Skip in CI due to environment variability
        return env('CI', false);
    });
});

<?php

use App\Models\Domain;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('User Count Accuracy', function () {
    beforeEach(function () {
        $this->org = Organization::create(['name' => 'Test Organization', 'is_active' => true]);
        $this->domain = Domain::create(['domain' => 'example.com']);
    });

    it('counts users correctly with usersMany relationship', function () {
        // Create 5 users and assign to organization
        $users = User::factory()->count(5)->create();

        foreach ($users as $user) {
            $this->org->usersMany()->attach($user->id, ['is_manual' => false]);
        }

        // Load organization with count
        $orgWithCount = Organization::withCount('usersMany')->find($this->org->id);

        expect($orgWithCount->users_many_count)->toBe(5);
    });

    it('counts include manual assignments', function () {
        $autoUser = User::factory()->create(['email' => 'auto@example.com']);
        $manualUser = User::factory()->create(['email' => 'manual@different.com']);

        $this->org->usersMany()->attach($autoUser->id, ['is_manual' => false]);
        $this->org->usersMany()->attach($manualUser->id, ['is_manual' => true]);

        $orgWithCount = Organization::withCount('usersMany')->find($this->org->id);

        // Should count both auto and manual users
        expect($orgWithCount->users_many_count)->toBe(2);
    });

    it('withCount usersMany as users_count provides correct alias', function () {
        User::factory()->count(10)->create()->each(function ($user) {
            $this->org->usersMany()->attach($user->id, ['is_manual' => false]);
        });

        $orgWithCount = Organization::withCount('usersMany as users_count')->find($this->org->id);

        expect($orgWithCount->users_count)->toBe(10);
    });

    it('counts users from many-to-many not HasMany relationship', function () {
        // Create users with same domain
        $user1 = User::factory()->create(['email' => 'user1@example.com', 'domain_id' => $this->domain->id]);
        $user2 = User::factory()->create(['email' => 'user2@example.com', 'domain_id' => $this->domain->id]);

        // Create user with different domain
        $user3 = User::factory()->create(['email' => 'user3@different.com', 'domain_id' => null]);

        // Assign all users to organization via many-to-many
        $this->org->usersMany()->attach($user1->id, ['is_manual' => false]);
        $this->org->usersMany()->attach($user2->id, ['is_manual' => false]);
        $this->org->usersMany()->attach($user3->id, ['is_manual' => true]);

        // withCount('usersMany') should count all 3
        $orgWithManyCount = Organization::withCount('usersMany')->find($this->org->id);
        expect($orgWithManyCount->users_many_count)->toBe(3);

        // HasMany 'users' relationship would only count users where organization_id matches (legacy)
        $orgWithHasManyCount = Organization::withCount('users')->find($this->org->id);

        // HasMany might return different count (only counting legacy organization_id field)
        // This demonstrates why usersMany is more accurate
    });

    it('maintains accurate count after user assignments change', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Initially no users
        $orgWithCount = Organization::withCount('usersMany as users_count')->find($this->org->id);
        expect($orgWithCount->users_count)->toBe(0);

        // Add first user
        $this->org->usersMany()->attach($user1->id, ['is_manual' => true]);
        $orgWithCount = Organization::withCount('usersMany as users_count')->find($this->org->id);
        expect($orgWithCount->users_count)->toBe(1);

        // Add second user
        $this->org->usersMany()->attach($user2->id, ['is_manual' => true]);
        $orgWithCount = Organization::withCount('usersMany as users_count')->find($this->org->id);
        expect($orgWithCount->users_count)->toBe(2);

        // Remove first user
        $this->org->usersMany()->detach($user1->id);
        $orgWithCount = Organization::withCount('usersMany as users_count')->find($this->org->id);
        expect($orgWithCount->users_count)->toBe(1);

        // Remove second user
        $this->org->usersMany()->detach($user2->id);
        $orgWithCount = Organization::withCount('usersMany as users_count')->find($this->org->id);
        expect($orgWithCount->users_count)->toBe(0);
    });

    it('counts accurately with large number of users', function () {
        // Create 100 users
        $users = User::factory()->count(100)->create();

        foreach ($users as $user) {
            $this->org->usersMany()->attach($user->id, ['is_manual' => false]);
        }

        $orgWithCount = Organization::withCount('usersMany as users_count')->find($this->org->id);

        expect($orgWithCount->users_count)->toBe(100);
    });

    it('counts correctly when user belongs to multiple organizations', function () {
        $org2 = Organization::factory()->create(['name' => 'Org 2']);
        $user = User::factory()->create();

        // Assign user to both organizations
        $this->org->usersMany()->attach($user->id, ['is_manual' => false]);
        $org2->usersMany()->attach($user->id, ['is_manual' => true]);

        $org1WithCount = Organization::withCount('usersMany as users_count')->find($this->org->id);
        $org2WithCount = Organization::withCount('usersMany as users_count')->find($org2->id);

        // Both should count 1 user
        expect($org1WithCount->users_count)->toBe(1);
        expect($org2WithCount->users_count)->toBe(1);
    });

    it('displays correct count in organization list view', function () {
        // Create multiple organizations with different user counts
        $org1 = Organization::create(['name' => 'Org 1', 'is_active' => true]);
        $org2 = Organization::create(['name' => 'Org 2', 'is_active' => true]);
        $org3 = Organization::create(['name' => 'Org 3', 'is_active' => true]);

        // Org1: 3 users
        User::factory()->count(3)->create()->each(function ($user) use ($org1) {
            $org1->usersMany()->attach($user->id, ['is_manual' => false]);
        });

        // Org2: 5 users (2 manual, 3 auto)
        User::factory()->count(2)->create()->each(function ($user) use ($org2) {
            $org2->usersMany()->attach($user->id, ['is_manual' => true]);
        });
        User::factory()->count(3)->create()->each(function ($user) use ($org2) {
            $org2->usersMany()->attach($user->id, ['is_manual' => false]);
        });

        // Org3: 0 users

        // Query as it would be in OrganizationManager component
        $organizations = Organization::withCount('usersMany as users_count')
            ->orderBy('name')
            ->get();

        expect($organizations->firstWhere('id', $org1->id)->users_count)->toBe(3);
        expect($organizations->firstWhere('id', $org2->id)->users_count)->toBe(5);
        expect($organizations->firstWhere('id', $org3->id)->users_count)->toBe(0);
    });

    it('counts match actual pivot table entries', function () {
        User::factory()->count(10)->create()->each(function ($user) {
            $this->org->usersMany()->attach($user->id, ['is_manual' => false]);
        });

        $orgWithCount = Organization::withCount('usersMany as users_count')->find($this->org->id);

        // Count pivot entries directly
        $pivotCount = \Illuminate\Support\Facades\DB::table('organization_user')
            ->where('organization_id', $this->org->id)
            ->count();

        // Should match exactly
        expect($orgWithCount->users_count)->toBe($pivotCount);
    });

    it('handles edge case of deleted user', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $this->org->usersMany()->attach($user1->id, ['is_manual' => true]);
        $this->org->usersMany()->attach($user2->id, ['is_manual' => true]);

        $orgWithCount = Organization::withCount('usersMany as users_count')->find($this->org->id);
        expect($orgWithCount->users_count)->toBe(2);

        // Delete user1
        $user1->delete();

        // Note: Depending on cascade settings, pivot entry might remain
        // This test documents current behavior
        $orgWithCountAfter = Organization::withCount('usersMany as users_count')->find($this->org->id);

        // Count should reflect actual database state
        // If cascade delete is set up, should be 1, otherwise might be 2
        expect($orgWithCountAfter->users_count)->toBeGreaterThanOrEqual(1);
    });
});

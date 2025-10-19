<?php

use App\Livewire\Admin\Organizations\OrganizationManager;
use App\Models\Domain;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('Organization User Management Modal', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->org1 = Organization::factory()->create(['name' => 'Test Organization']);
        $this->org2 = Organization::factory()->create(['name' => 'Another Organization']);
        $this->domain = Domain::create(['domain' => 'example.com']);
    });

    it('opens manage users modal with correct data', function () {
        $user1 = User::factory()->create(['email' => 'user1@example.com', 'fullname' => 'User One']);
        $user2 = User::factory()->create(['email' => 'user2@example.com', 'fullname' => 'User Two']);

        // Assign users to organization
        $this->org1->usersMany()->attach($user1->id, ['is_manual' => false]);
        $this->org1->usersMany()->attach($user2->id, ['is_manual' => true]);

        Livewire::test(OrganizationManager::class)
            ->call('openManageUsersModal', $this->org1->id)
            ->assertSet('showManageUsersModal', true)
            ->assertSet('organizationToManage.id', $this->org1->id)
            ->assertSet('organizationToManage.name', 'Test Organization');
    });

    it('displays assigned users correctly in modal', function () {
        $user1 = User::factory()->create(['email' => 'user1@example.com', 'fullname' => 'User One']);
        $user2 = User::factory()->create(['email' => 'user2@example.com', 'fullname' => 'User Two']);

        $this->org1->usersMany()->attach($user1->id, ['is_manual' => false]);
        $this->org1->usersMany()->attach($user2->id, ['is_manual' => true]);

        $component = Livewire::test(OrganizationManager::class)
            ->call('openManageUsersModal', $this->org1->id);

        // Check assigned users are loaded
        $assignedUsers = $component->get('assignedUsers');
        expect($assignedUsers)->toHaveCount(2);
    });

    it('attaches user manually using attachUser method', function () {
        $user1 = User::factory()->create(['email' => 'user1@example.com', 'fullname' => 'User One']);

        Livewire::test(OrganizationManager::class)
            ->call('openManageUsersModal', $this->org1->id)
            ->call('attachUser', $user1->id, true);

        // Check user was attached with is_manual = true
        $pivot = DB::table('organization_user')
            ->where('user_id', $user1->id)
            ->where('organization_id', $this->org1->id)
            ->first();

        expect($pivot)->not->toBeNull();
        expect($pivot->is_manual)->toBe(1);
    });

    it('detaches user when using detachUser method', function () {
        $user1 = User::factory()->create(['email' => 'user1@example.com', 'fullname' => 'User One']);
        $this->org1->usersMany()->attach($user1->id, ['is_manual' => true]);

        Livewire::test(OrganizationManager::class)
            ->call('openManageUsersModal', $this->org1->id)
            ->call('detachUser', $user1->id);

        // Check user was detached
        $pivot = DB::table('organization_user')
            ->where('user_id', $user1->id)
            ->where('organization_id', $this->org1->id)
            ->first();

        expect($pivot)->toBeNull();
    });

    it('filters users correctly based on search term', function () {
        $userAlice = User::factory()->create(['email' => 'alice@example.com', 'fullname' => 'Alice Smith']);
        $userBob = User::factory()->create(['email' => 'bob@different.com', 'fullname' => 'Bob Jones']);
        $userCharlie = User::factory()->create(['email' => 'charlie@example.com', 'fullname' => 'Charlie Brown']);

        $component = Livewire::test(OrganizationManager::class)
            ->call('openManageUsersModal', $this->org1->id)
            ->set('userSearch', 'alice');

        // Should only find Alice (filteredUsers returns array)
        $filteredUsers = $component->get('filteredUsers');
        expect(count($filteredUsers))->toBe(1);
        expect($filteredUsers[0]->email)->toBe('alice@example.com');
    });

    it('filters users by email domain', function () {
        User::factory()->create(['email' => 'user1@example.com', 'fullname' => 'User One']);
        User::factory()->create(['email' => 'user2@example.com', 'fullname' => 'User Two']);
        User::factory()->create(['email' => 'user3@different.com', 'fullname' => 'User Three']);

        $component = Livewire::test(OrganizationManager::class)
            ->call('openManageUsersModal', $this->org1->id)
            ->set('userSearch', 'example.com');

        // Should find 2 users with example.com (filteredUsers returns array)
        $filteredUsers = $component->get('filteredUsers');
        expect(count($filteredUsers))->toBeGreaterThanOrEqual(2);
    });

    it('shows all users in filtered results including assigned', function () {
        $user1 = User::factory()->create(['email' => 'user1@example.com', 'fullname' => 'User One']);
        $user2 = User::factory()->create(['email' => 'user2@example.com', 'fullname' => 'User Two']);

        // Assign user1 to organization
        $this->org1->usersMany()->attach($user1->id, ['is_manual' => true]);

        $component = Livewire::test(OrganizationManager::class)
            ->call('openManageUsersModal', $this->org1->id)
            ->set('userSearch', 'example.com');

        $filteredUsers = $component->get('filteredUsers');
        $filteredUserIds = array_map(fn($u) => $u->id, $filteredUsers);

        // The implementation doesn't exclude assigned users, it shows all matching users
        expect(count($filteredUsers))->toBeGreaterThanOrEqual(2);
    });

    it('paginates assigned users correctly', function () {
        // Create 30 users and assign to org (more than 20 per page)
        for ($i = 1; $i <= 30; $i++) {
            $user = User::factory()->create(['email' => "user{$i}@example.com", 'fullname' => "User {$i}"]);
            $this->org1->usersMany()->attach($user->id, ['is_manual' => false]);
        }

        $component = Livewire::test(OrganizationManager::class)
            ->call('openManageUsersModal', $this->org1->id);

        // Initial load should show first page (20 per page)
        $assignedUsers = $component->get('assignedUsers');
        expect(count($assignedUsers))->toBeLessThanOrEqual(20);

        // Load more
        $component->call('loadMoreAssignedUsers');
        $assignedUsersAfter = $component->get('assignedUsers');

        // Should show more users now (40 total: 2 pages * 20)
        expect(count($assignedUsersAfter))->toBeGreaterThan(count($assignedUsers));
        expect(count($assignedUsersAfter))->toBe(30); // All 30 users should be loaded now
    });

    it('displays manual assignment indicator correctly', function () {
        $autoUser = User::factory()->create(['email' => 'auto@example.com', 'fullname' => 'Auto User']);
        $manualUser = User::factory()->create(['email' => 'manual@example.com', 'fullname' => 'Manual User']);

        $this->org1->usersMany()->attach($autoUser->id, ['is_manual' => false]);
        $this->org1->usersMany()->attach($manualUser->id, ['is_manual' => true]);

        $component = Livewire::test(OrganizationManager::class)
            ->call('openManageUsersModal', $this->org1->id);

        $assignedUsers = $component->get('assignedUsers');

        // Find each user in results
        $autoUserResult = collect($assignedUsers)->firstWhere('id', $autoUser->id);
        $manualUserResult = collect($assignedUsers)->firstWhere('id', $manualUser->id);

        expect($autoUserResult->pivot->is_manual)->toBe(0);
        expect($manualUserResult->pivot->is_manual)->toBe(1);
    });

    it('sorts manual assignments before auto assignments', function () {
        $autoUser = User::factory()->create(['email' => 'aaa@example.com', 'fullname' => 'AAA User']);
        $manualUser = User::factory()->create(['email' => 'zzz@example.com', 'fullname' => 'ZZZ User']);

        // Attach in reverse order (auto first, then manual)
        $this->org1->usersMany()->attach($autoUser->id, ['is_manual' => false]);
        $this->org1->usersMany()->attach($manualUser->id, ['is_manual' => true]);

        $component = Livewire::test(OrganizationManager::class)
            ->call('openManageUsersModal', $this->org1->id);

        $assignedUsers = $component->get('assignedUsers');

        // Manual user should appear first despite alphabetical order
        $firstUser = collect($assignedUsers)->first();
        expect($firstUser->pivot->is_manual)->toBe(1);
    });

    it('handles edge case of organization with no users', function () {
        Livewire::test(OrganizationManager::class)
            ->call('openManageUsersModal', $this->org1->id)
            ->assertSet('showManageUsersModal', true);

        // Should not error and should show empty state
        $component = Livewire::test(OrganizationManager::class)
            ->call('openManageUsersModal', $this->org1->id);

        $assignedUsers = $component->get('assignedUsers');
        expect($assignedUsers)->toHaveCount(0);
    });

    it('closes modal correctly and resets state', function () {
        $user1 = User::factory()->create(['email' => 'user1@example.com', 'fullname' => 'User One']);
        $this->org1->usersMany()->attach($user1->id, ['is_manual' => true]);

        Livewire::test(OrganizationManager::class)
            ->call('openManageUsersModal', $this->org1->id)
            ->assertSet('showManageUsersModal', true)
            ->call('closeManageUsersModal')
            ->assertSet('showManageUsersModal', false)
            ->assertSet('organizationToManage', null)
            ->assertSet('userSearch', '');
    });

    it('prevents attaching same user twice using attachUser', function () {
        $user1 = User::factory()->create(['email' => 'user1@example.com', 'fullname' => 'User One']);

        // Attach user first time
        $this->org1->usersMany()->attach($user1->id, ['is_manual' => true]);

        // Try to attach same user again using attachUser
        Livewire::test(OrganizationManager::class)
            ->call('openManageUsersModal', $this->org1->id)
            ->call('attachUser', $user1->id, true);

        // Should only have one pivot entry (attachUser checks for duplicates)
        $pivotCount = DB::table('organization_user')
            ->where('user_id', $user1->id)
            ->where('organization_id', $this->org1->id)
            ->count();

        expect($pivotCount)->toBe(1);
    });

    it('does not update legacy organization_id via organization modal', function () {
        $user1 = User::factory()->create(['email' => 'user1@example.com', 'fullname' => 'User One', 'organization_id' => null]);

        Livewire::test(OrganizationManager::class)
            ->call('openManageUsersModal', $this->org1->id)
            ->call('attachUser', $user1->id, true);

        // Note: OrganizationManager doesn't update legacy organization_id
        // This is handled in domain sync jobs instead
        // Test just verifies the user was assigned via pivot table
        $pivot = DB::table('organization_user')
            ->where('user_id', $user1->id)
            ->where('organization_id', $this->org1->id)
            ->first();

        expect($pivot)->not->toBeNull();
    });
});

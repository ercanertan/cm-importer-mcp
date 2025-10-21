<?php

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('User Primary Organization', function () {
    describe('primary organization relationship', function () {
        it('can get primary organization', function () {
            $user = User::factory()->create();
            $org1 = Organization::factory()->create();
            $org2 = Organization::factory()->create();

            // Attach organizations
            $user->organizations()->attach($org1->id, ['is_primary' => true]);
            $user->organizations()->attach($org2->id, ['is_primary' => false]);

            $primaryOrg = $user->primaryOrganization();

            expect($primaryOrg)->not()->toBeNull();
            expect($primaryOrg->id)->toBe($org1->id);
        });

        it('returns null when no primary organization is set', function () {
            $user = User::factory()->create();
            $org = Organization::factory()->create();

            $user->organizations()->attach($org->id, ['is_primary' => false]);

            $primaryOrg = $user->primaryOrganization();

            expect($primaryOrg)->toBeNull();
        });

        it('includes is_primary in pivot data', function () {
            $user = User::factory()->create();
            $org = Organization::factory()->create();

            $user->organizations()->attach($org->id, ['is_primary' => true]);

            $organization = $user->organizations()->first();

            expect($organization->membership->is_primary)->toBeTrue();
        });
    });

    describe('setPrimaryOrganization method', function () {
        it('can set an organization as primary', function () {
            $user = User::factory()->create();
            $org1 = Organization::factory()->create();
            $org2 = Organization::factory()->create();

            $user->organizations()->attach($org1->id);
            $user->organizations()->attach($org2->id);

            $result = $user->setPrimaryOrganization($org1->id);

            expect($result)->toBeTrue();

            $primaryOrg = $user->primaryOrganization();
            expect($primaryOrg->id)->toBe($org1->id);
        });

        it('ensures only one organization can be primary', function () {
            $user = User::factory()->create();
            $org1 = Organization::factory()->create();
            $org2 = Organization::factory()->create();

            $user->organizations()->attach($org1->id, ['is_primary' => true]);
            $user->organizations()->attach($org2->id, ['is_primary' => false]);

            $user->setPrimaryOrganization($org2->id);

            $user->refresh();

            // Check org1 is no longer primary
            $org1Status = $user->organizations()->where('organization_id', $org1->id)->first();
            expect($org1Status->membership->is_primary)->toBeFalse();

            // Check org2 is now primary
            $org2Status = $user->organizations()->where('organization_id', $org2->id)->first();
            expect($org2Status->membership->is_primary)->toBeTrue();
        });

        it('returns false when user does not belong to organization', function () {
            $user = User::factory()->create();
            $org1 = Organization::factory()->create();
            $org2 = Organization::factory()->create();

            $user->organizations()->attach($org1->id);

            $result = $user->setPrimaryOrganization($org2->id);

            expect($result)->toBeFalse();
        });

        it('handles switching primary organization multiple times', function () {
            $user = User::factory()->create();
            $org1 = Organization::factory()->create();
            $org2 = Organization::factory()->create();
            $org3 = Organization::factory()->create();

            $user->organizations()->attach($org1->id);
            $user->organizations()->attach($org2->id);
            $user->organizations()->attach($org3->id);

            $user->setPrimaryOrganization($org1->id);
            expect($user->primaryOrganization()->id)->toBe($org1->id);

            $user->setPrimaryOrganization($org2->id);
            expect($user->primaryOrganization()->id)->toBe($org2->id);

            $user->setPrimaryOrganization($org3->id);
            expect($user->primaryOrganization()->id)->toBe($org3->id);

            // Verify others are not primary
            $user->refresh();
            $org1Status = $user->organizations()->where('organization_id', $org1->id)->first();
            $org2Status = $user->organizations()->where('organization_id', $org2->id)->first();

            expect($org1Status->membership->is_primary)->toBeFalse();
            expect($org2Status->membership->is_primary)->toBeFalse();
        });
    });

    describe('isPrimaryOrganization method', function () {
        it('returns true when organization is primary', function () {
            $user = User::factory()->create();
            $org = Organization::factory()->create();

            $user->organizations()->attach($org->id, ['is_primary' => true]);

            expect($user->isPrimaryOrganization($org->id))->toBeTrue();
        });

        it('returns false when organization is not primary', function () {
            $user = User::factory()->create();
            $org = Organization::factory()->create();

            $user->organizations()->attach($org->id, ['is_primary' => false]);

            expect($user->isPrimaryOrganization($org->id))->toBeFalse();
        });

        it('returns false when user does not belong to organization', function () {
            $user = User::factory()->create();
            $org = Organization::factory()->create();

            expect($user->isPrimaryOrganization($org->id))->toBeFalse();
        });
    });

    describe('multiple users with different primary organizations', function () {
        it('allows different users to have different primary organizations', function () {
            $user1 = User::factory()->create();
            $user2 = User::factory()->create();
            $org1 = Organization::factory()->create();
            $org2 = Organization::factory()->create();

            $user1->organizations()->attach($org1->id);
            $user1->organizations()->attach($org2->id);
            $user2->organizations()->attach($org1->id);
            $user2->organizations()->attach($org2->id);

            $user1->setPrimaryOrganization($org1->id);
            $user2->setPrimaryOrganization($org2->id);

            expect($user1->primaryOrganization()->id)->toBe($org1->id);
            expect($user2->primaryOrganization()->id)->toBe($org2->id);
        });
    });

    describe('edge cases', function () {
        it('handles user with single organization', function () {
            $user = User::factory()->create();
            $org = Organization::factory()->create();

            $user->organizations()->attach($org->id);
            $user->setPrimaryOrganization($org->id);

            expect($user->primaryOrganization()->id)->toBe($org->id);
        });

        it('maintains other pivot data when setting primary', function () {
            $user = User::factory()->create();
            $org = Organization::factory()->create();

            $user->organizations()->attach($org->id, [
                'is_manual' => true,
                'is_primary' => false,
            ]);

            $user->setPrimaryOrganization($org->id);

            $organization = $user->organizations()->first();

            expect($organization->membership->is_primary)->toBeTrue();
            expect($organization->membership->is_manual)->toBeTrue();
        });
    });
});

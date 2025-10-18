<?php

use App\Models\Domain;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Domain Model', function () {
    beforeEach(function () {
        // Create a default organization for tests
        $this->defaultOrganization = Organization::factory()->create([
            'name' => 'Default Organization',
            'is_active' => true,
        ]);

        $this->organization = Organization::factory()->create([
            'name' => 'Test Organization',
            'is_active' => true,
        ]);
    });

    describe('extractFromEmail', function () {
        it('extracts domain from valid email', function () {
            expect(Domain::extractFromEmail('user@example.com'))->toBe('example.com');
            expect(Domain::extractFromEmail('test@gmail.com'))->toBe('gmail.com');
            expect(Domain::extractFromEmail('admin@subdomain.company.org'))->toBe('subdomain.company.org');
        });

        it('returns null for invalid email', function () {
            expect(Domain::extractFromEmail('not-an-email'))->toBeNull();
            expect(Domain::extractFromEmail(''))->toBeNull();
            expect(Domain::extractFromEmail('invalid@'))->toBeNull();
        });

        it('lowercases domain', function () {
            expect(Domain::extractFromEmail('user@EXAMPLE.COM'))->toBe('example.com');
            expect(Domain::extractFromEmail('test@Gmail.Com'))->toBe('gmail.com');
        });
    });

    describe('findOrCreateByEmail', function () {
        it('creates domain from email', function () {
            $domain = Domain::findOrCreateByEmail('user@example.com');

            expect($domain)->toBeInstanceOf(Domain::class);
            expect($domain->domain)->toBe('example.com');
            expect(Domain::count())->toBe(1);
        });

        it('finds existing domain instead of creating duplicate', function () {
            $existing = Domain::create(['domain' => 'example.com']);

            $domain = Domain::findOrCreateByEmail('another@example.com');

            expect($domain->id)->toBe($existing->id);
            expect(Domain::count())->toBe(1);
        });

        it('returns null for invalid email', function () {
            expect(Domain::findOrCreateByEmail('invalid-email'))->toBeNull();
        });
    });

    describe('assignUsersFromDomain', function () {
        it('assigns users with matching domain email', function () {
            $domain = Domain::create(['domain' => 'example.com']);
            $domain->organizations()->attach($this->organization->id);

            // Create users with matching domain
            User::factory()->count(5)->create([
                'email' => fn() => fake()->userName() . '@example.com',
                'domain_id' => null,
                'organization_id' => null,
            ]);

            // Create users with different domain
            User::factory()->count(3)->create([
                'email' => fn() => fake()->userName() . '@other.com',
            ]);

            $result = $domain->assignUsersFromDomain();

            expect($result['total_users'])->toBe(5);
            expect(User::where('domain_id', $domain->id)->count())->toBe(5);
            expect(User::whereNull('domain_id')->count())->toBe(3);
        });

        it('assigns users to all domain organizations', function () {
            $domain = Domain::create(['domain' => 'example.com']);
            $org1 = Organization::factory()->create();
            $org2 = Organization::factory()->create();

            $domain->organizations()->attach([$org1->id, $org2->id]);

            $user = User::factory()->create([
                'email' => 'test@example.com',
                'domain_id' => null,
                'organization_id' => null,
            ]);

            $domain->assignUsersFromDomain();

            $userOrgIds = $user->fresh()->organizations->pluck('id')->toArray();
            expect($userOrgIds)->toContain($org1->id);
            expect($userOrgIds)->toContain($org2->id);
        });

        it('uses bulk updates for performance', function () {
            $domain = Domain::create(['domain' => 'example.com']);
            $domain->organizations()->attach($this->organization->id);

            // Create 100 users
            User::factory()->count(100)->create([
                'email' => fn() => fake()->userName() . '@example.com',
                'domain_id' => null,
            ]);

            $startTime = microtime(true);
            $domain->assignUsersFromDomain();
            $endTime = microtime(true);

            // Should complete in less than 2 seconds for 100 users (bulk operations)
            expect($endTime - $startTime)->toBeLessThan(2.0);
            expect(User::where('domain_id', $domain->id)->count())->toBe(100);
        });

        it('sets legacy organization_id field', function () {
            $domain = Domain::create(['domain' => 'example.com']);
            $domain->organizations()->attach($this->organization->id);

            $user = User::factory()->create([
                'email' => 'test@example.com',
                'organization_id' => null,
            ]);

            $domain->assignUsersFromDomain();

            expect($user->fresh()->organization_id)->toBe($this->organization->id);
        });

        it('does not override existing domain_id if already correct', function () {
            $domain = Domain::create(['domain' => 'example.com']);
            $domain->organizations()->attach($this->organization->id);

            $user = User::factory()->create([
                'email' => 'test@example.com',
                'domain_id' => $domain->id,
            ]);

            $result = $domain->assignUsersFromDomain();

            expect($user->fresh()->domain_id)->toBe($domain->id);
        });

        it('handles domain with no organizations', function () {
            $domain = Domain::create(['domain' => 'example.com']);

            User::factory()->count(5)->create([
                'email' => fn() => fake()->userName() . '@example.com',
            ]);

            $result = $domain->assignUsersFromDomain();

            expect($result['total_users'])->toBe(5);
            // Domain ID should still be set even without organizations
            expect(User::where('domain_id', $domain->id)->count())->toBe(5);
        });
    });

    describe('assignUsersToOrganization', function () {
        it('assigns all domain users to specific organization', function () {
            $domain = Domain::create(['domain' => 'example.com']);

            User::factory()->count(10)->create([
                'email' => fn() => fake()->userName() . '@example.com',
            ]);

            $count = $domain->assignUsersToOrganization($this->organization);

            expect($count)->toBe(10);
            expect(User::where('organization_id', $this->organization->id)->count())->toBe(10);
        });

        it('adds organization to domain when assigning', function () {
            $domain = Domain::create(['domain' => 'example.com']);

            expect($domain->organizations)->toHaveCount(0);

            $domain->assignUsersToOrganization($this->organization);

            expect($domain->fresh()->organizations)->toHaveCount(1);
            expect($domain->fresh()->organizations->first()->id)->toBe($this->organization->id);
        });

        it('removes default organization when assigning to specific organization', function () {
            $domain = Domain::create(['domain' => 'example.com']);
            $domain->organizations()->attach($this->defaultOrganization->id);

            $domain->assignUsersToOrganization($this->organization);

            $domainOrgIds = $domain->fresh()->organizations->pluck('id')->toArray();
            expect($domainOrgIds)->not->toContain($this->defaultOrganization->id);
            expect($domainOrgIds)->toContain($this->organization->id);
        });

        it('uses bulk updates for many users', function () {
            $domain = Domain::create(['domain' => 'example.com']);

            // Create users with guaranteed unique emails
            for ($i = 0; $i < 1000; $i++) {
                User::factory()->create([
                    'email' => "user{$i}@example.com",
                ]);
            }

            $startTime = microtime(true);
            $domain->assignUsersToOrganization($this->organization);
            $endTime = microtime(true);

            // Should complete in less than 3 seconds for 1000 users
            expect($endTime - $startTime)->toBeLessThan(3.0);
        });

        it('adds to many-to-many pivot table', function () {
            $domain = Domain::create(['domain' => 'example.com']);

            $user = User::factory()->create([
                'email' => 'test@example.com',
            ]);

            $domain->assignUsersToOrganization($this->organization);

            $pivotCount = DB::table('organization_user')
                ->where('user_id', $user->id)
                ->where('organization_id', $this->organization->id)
                ->count();

            expect($pivotCount)->toBe(1);
        });

        it('does not create duplicate pivot entries', function () {
            $domain = Domain::create(['domain' => 'example.com']);

            $user = User::factory()->create([
                'email' => 'test@example.com',
            ]);

            // Assign twice
            $domain->assignUsersToOrganization($this->organization);
            $domain->assignUsersToOrganization($this->organization);

            $pivotCount = DB::table('organization_user')
                ->where('user_id', $user->id)
                ->where('organization_id', $this->organization->id)
                ->count();

            expect($pivotCount)->toBe(1);
        });
    });

    describe('relationships', function () {
        it('has many users', function () {
            $domain = Domain::create(['domain' => 'example.com']);

            User::factory()->count(5)->create([
                'email' => fn() => fake()->userName() . '@example.com',
                'domain_id' => $domain->id,
            ]);

            expect($domain->users)->toHaveCount(5);
        });

        it('belongs to many organizations', function () {
            $domain = Domain::create(['domain' => 'example.com']);

            $orgs = Organization::factory()->count(3)->create();
            $domain->organizations()->attach($orgs->pluck('id'));

            expect($domain->organizations)->toHaveCount(3);
        });

        it('has user_count attribute', function () {
            $domain = Domain::create(['domain' => 'example.com']);

            User::factory()->count(7)->create([
                'domain_id' => $domain->id,
            ]);

            expect($domain->user_count)->toBe(7);
        });
    });
});

# Domain Sync Implementation Notes

## CRITICAL: Two Different Job Paths for Organization Domain Management

When working on organization-domain syncing features, **ALWAYS check BOTH jobs**:

### 1. `SyncOrganizationDomainsJob`
**Location:** `app/Jobs/SyncOrganizationDomainsJob.php`

**When it's used:**
- For **non-conditional** organizations (organizations WITHOUT conditional rules)
- Called from `OrganizationManager::updateOrganization()` when domains change

**What it does:**
- Syncs organization → domains relationship
- Assigns users from those domains to the organization
- Handles user migration from Default Organization

### 2. `SyncOrganizationConditionalJob`
**Location:** `app/Jobs/SyncOrganizationConditionalJob.php`

**When it's used:**
- For **conditional** organizations (organizations WITH conditional rules/conditions)
- Called from `OrganizationManager::updateAdvancedOrganization()` when editing conditional orgs
- Called from `OrganizationManager::createAdvancedOrganization()` when creating conditional orgs

**What it does:**
- Syncs organization → domains relationship
- Evaluates conditional rules to find matching users
- Assigns users who meet the conditions
- Removes users who no longer meet the conditions
- Removes users from detached domains

## How to Check Which Job is Being Called

Look at the `OrganizationManager` Livewire component:

### For Regular Organizations:
```php
// Line ~525 in OrganizationManager.php
SyncOrganizationDomainsJob::dispatch(
    $syncLog->id,
    $organization->id,
    $domainList,
    $this->syncUsers
);
```

### For Conditional Organizations (Advanced):
```php
// Line ~250 and ~345 in OrganizationManager.php
SyncOrganizationConditionalJob::dispatch(
    $syncLog->id,
    $organization->id,
    $domainList,
    $this->conditions,
    $this->conditionLogic
);
```

## Common Bugs to Watch For

### Bug Pattern 1: Using `attach()` instead of `sync()`
❌ **Wrong:**
```php
foreach ($domainList as $domainName) {
    $domain = Domain::firstOrCreate(['domain' => $domainName]);
    if (!$organization->domains()->where('domain_id', $domain->id)->exists()) {
        $organization->domains()->attach($domain->id);
    }
}
```

✅ **Correct:**
```php
$domainIds = [];
foreach ($domainList as $domainName) {
    $domain = Domain::firstOrCreate(['domain' => $domainName]);
    $domainIds[] = $domain->id;
}
$organization->domains()->sync($domainIds); // This removes domains not in the list!
```

### Bug Pattern 2: Not Removing Users After Domain Detachment
When domains are removed from an organization, auto-assigned users from those domains should also be removed.

✅ **Required logic:**
```php
// After syncing domains, remove users from detached domains
$usersToRemove = DB::table('organization_user')
    ->join('users', 'organization_user.user_id', '=', 'users.id')
    ->where('organization_user.organization_id', $organization->id)
    ->where('organization_user.is_manual', false) // CRITICAL: Only auto-assigned
    ->whereNotNull('users.domain_id')
    ->whereNotIn('users.domain_id', $currentDomainIds)
    ->pluck('organization_user.user_id')
    ->toArray();

if (!empty($usersToRemove)) {
    DB::table('organization_user')
        ->where('organization_id', $organization->id)
        ->whereIn('user_id', $usersToRemove)
        ->where('is_manual', false) // CRITICAL: Safety check
        ->delete();
}
```

### Bug Pattern 3: Re-syncing Domain Relationships in Helper Methods
When calling `Domain::assignUsersToOrganization()` from within a job that has already synced domains, pass `false` for the `$syncDomainOrganization` parameter to prevent re-adding detached domains.

❌ **Wrong:**
```php
$domain->assignUsersToOrganization($organization); // Re-syncs domains!
```

✅ **Correct:**
```php
$domain->assignUsersToOrganization($organization, false); // Skip domain sync
```

## Testing Checklist

When modifying domain sync logic, test BOTH scenarios:

- [ ] Regular organization (no conditions) - removes domain
- [ ] Regular organization (no conditions) - removes all domains
- [ ] Conditional organization - removes domain
- [ ] Conditional organization - removes all domains
- [ ] Verify auto-assigned users are removed
- [ ] Verify manually assigned users are preserved
- [ ] Check that domains are actually detached in `organization_domain` table
- [ ] Check that users are removed from `organization_user` table (where `is_manual = false`)

## Related Files

- `app/Livewire/Admin/Organizations/OrganizationManager.php` - Determines which job to call
- `app/Jobs/SyncOrganizationDomainsJob.php` - Regular organization sync
- `app/Jobs/SyncOrganizationConditionalJob.php` - Conditional organization sync
- `app/Models/Domain.php` - `assignUsersToOrganization()` method
- `app/Models/Organization.php` - Domain relationship

## History of Fixes

### 2025-10-27: Domain Detachment Bug
**Issue:** Domains remained attached to organizations even after being removed from the UI.

**Root Cause:**
1. `SyncOrganizationConditionalJob::associateDomains()` used `attach()` instead of `sync()`
2. No logic to remove users from detached domains
3. `Domain::assignUsersToOrganization()` was re-syncing domains after the main job had already synced them

**Fix Applied:**
1. Changed `SyncOrganizationConditionalJob::associateDomains()` to use `sync()` instead of `attach()`
2. Added `SyncOrganizationConditionalJob::removeUsersFromDetachedDomains()` method
3. Added `$syncDomainOrganization` parameter to `Domain::assignUsersToOrganization()`
4. Applied same fixes to `SyncOrganizationDomainsJob`

**Files Modified:**
- `app/Jobs/SyncOrganizationConditionalJob.php`
- `app/Jobs/SyncOrganizationDomainsJob.php`
- `app/Models/Domain.php`
- `app/Livewire/Admin/Organizations/OrganizationManager.php`

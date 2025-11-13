# Multi-Role Dashboard Architecture

**Project:** Laravel 12 Multi-Tenant B2B CDP with Campaign Monitor Integration
**Framework:** Livewire 4 Beta + Flux Pro
**Last Updated:** 2025-11-13

---

## 📊 Architecture Overview

This document defines the folder structure, routing strategy, and architectural patterns for implementing multiple user roles and dashboards in the CDP platform.

### Application Layers

```
┌─────────────────────────────────────────────────────────────────┐
│                        APPLICATION LAYERS                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐         │
│  │ Super Admin  │  │  Org Admin   │  │     User     │         │
│  │  Dashboard   │  │  Dashboard   │  │  Dashboard   │         │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘         │
│         │                  │                  │                  │
│         └──────────────────┴──────────────────┘                 │
│                            │                                     │
│         ┌──────────────────▼──────────────────┐                │
│         │   Route Middleware Layer            │                │
│         │   ├─ web, auth, verified            │                │
│         │   ├─ role:super_admin               │                │
│         │   ├─ role:org_admin                 │                │
│         │   └─ role:user                      │                │
│         └──────────────────┬──────────────────┘                │
│                            │                                     │
│         ┌──────────────────▼──────────────────┐                │
│         │   Livewire 4 Components Layer       │                │
│         │   ├─ SuperAdmin/*                   │                │
│         │   ├─ OrgAdmin/*                     │                │
│         │   └─ User/*                         │                │
│         └──────────────────┬──────────────────┘                │
│                            │                                     │
│         ┌──────────────────▼──────────────────┐                │
│         │   Shared Components Layer           │                │
│         │   ├─ Traits (Authorization)         │                │
│         │   ├─ Concerns (Common Logic)        │                │
│         │   └─ Forms (Reusable Forms)         │                │
│         └──────────────────┬──────────────────┘                │
│                            │                                     │
│         ┌──────────────────▼──────────────────┐                │
│         │   Model & Business Logic Layer      │                │
│         │   ├─ Models                         │                │
│         │   ├─ Services                       │                │
│         │   ├─ Actions                        │                │
│         │   └─ Policies                       │                │
│         └─────────────────────────────────────┘                │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## 🎭 User Roles & Responsibilities

### **1. Super Admin**
- **Scope:** Platform-wide administration
- **Access:**
  - CDP tier and product catalog management
  - Segment builder and campaign management
  - Organization CRUD operations
  - Global user management
  - System configuration and sync monitoring

### **2. Organization Admin**
- **Scope:** Organization-level administration
- **Access:**
  - Team member management within organization
  - Product subscription management for organization
  - Organization settings and configuration
  - Activity reports and engagement metrics
  - Cannot access other organizations

### **3. User (End User)**
- **Scope:** Personal account management
- **Access:**
  - Personal profile and preferences
  - Product subscription opt-in/opt-out
  - GDPR consent management
  - View personal email activity history
  - Cannot access admin features

---

## 📁 Complete Folder Structure

```
app/
├── Livewire/
│   ├── SuperAdmin/                    # Platform-wide administration
│   │   ├── Dashboard.php              # Main super admin dashboard
│   │   ├── Cdp/
│   │   │   ├── TierManager.php        # Tier management (CRUD)
│   │   │   ├── ProductManager.php     # Product catalog management
│   │   │   ├── SegmentBuilder.php     # Segment creation & builder
│   │   │   ├── CampaignManager.php    # Campaign management
│   │   │   ├── TemplateManager.php    # Email template management
│   │   │   └── SyncMonitor.php        # CM sync monitoring dashboard
│   │   ├── Organizations/
│   │   │   ├── OrganizationManager.php      # Organization CRUD
│   │   │   ├── OrganizationDetails.php      # View/edit org details
│   │   │   └── OrganizationTierAssignment.php # Assign tiers to orgs
│   │   ├── Domains/
│   │   │   └── DomainManager.php      # Domain management
│   │   ├── Users/
│   │   │   ├── UserManager.php        # Global user management
│   │   │   └── UserRoleAssignment.php # Assign roles to users
│   │   └── Settings/
│   │       ├── CustomFieldsManager.php  # CM custom fields config
│   │       └── SyncLogs.php           # View sync logs
│   │
│   ├── OrgAdmin/                      # Organization-level administration
│   │   ├── Dashboard.php              # Org admin dashboard
│   │   ├── Team/
│   │   │   ├── TeamMembers.php        # Manage org users
│   │   │   └── InviteUser.php         # Invite new users to org
│   │   ├── Products/
│   │   │   ├── ProductSubscriptions.php  # Manage org product access
│   │   │   └── BulkSubscribe.php      # Bulk subscribe users
│   │   ├── Reports/
│   │   │   ├── ActivityReport.php     # Organization activity metrics
│   │   │   └── EngagementMetrics.php  # Email engagement analytics
│   │   └── Settings/
│   │       └── OrganizationSettings.php # Org-specific settings
│   │
│   ├── User/                          # End-user facing components
│   │   ├── Dashboard.php              # User dashboard
│   │   ├── Profile/
│   │   │   ├── EditProfile.php        # Edit user profile
│   │   │   └── ManageConsent.php      # GDPR consent management
│   │   ├── Subscriptions/
│   │   │   ├── ManageSubscriptions.php      # Product opt-in/opt-out
│   │   │   └── SubscriptionPreferences.php  # Email preferences
│   │   └── Activity/
│   │       └── ActivityHistory.php    # User's email activity log
│   │
│   ├── Shared/                        # Shared across all roles
│   │   ├── Forms/
│   │   │   ├── TierForm.php           # Reusable tier form component
│   │   │   ├── ProductForm.php        # Reusable product form
│   │   │   └── SegmentFilterForm.php  # Segment filter builder form
│   │   ├── Tables/
│   │   │   ├── UsersTable.php         # Reusable users data table
│   │   │   └── SubscriptionsTable.php # Reusable subscriptions table
│   │   └── Modals/
│   │       ├── ConfirmationModal.php  # Generic confirmation modal
│   │       └── BulkActionModal.php    # Bulk action confirmation
│   │
│   └── Concerns/                      # Livewire traits/concerns
│       ├── WithRoleAuthorization.php  # Role checking trait
│       ├── WithBulkActions.php        # Bulk operations trait
│       ├── WithDataTable.php          # Data table functionality
│       └── WithFluxNotifications.php  # Flux toast notifications
│
├── Http/
│   ├── Middleware/
│   │   ├── EnsureSuperAdmin.php       # Super admin authorization gate
│   │   ├── EnsureOrgAdmin.php         # Org admin authorization gate
│   │   └── EnsureOrgMembership.php    # Verify org membership
│   │
│   └── Controllers/
│       └── DashboardController.php    # Smart redirect based on role
│
├── Services/                          # Business logic layer
│   ├── Cdp/
│   │   ├── TierService.php            # Tier business logic
│   │   ├── ProductService.php         # Product business logic
│   │   ├── SegmentService.php         # Segment building logic
│   │   └── CampaignService.php        # Campaign orchestration
│   ├── CampaignMonitor/
│   │   ├── SyncService.php            # CM sync orchestration
│   │   └── ApiService.php             # CM API wrapper
│   └── Organization/
│       └── OrganizationService.php    # Organization business logic
│
├── Actions/                           # Single-purpose action classes
│   ├── Cdp/
│   │   ├── CreateTier.php             # Create tier action
│   │   ├── AssignProductToTier.php    # Assign product to tier
│   │   └── SyncUserToCampaignMonitor.php # Sync user to CM
│   └── Organization/
│       ├── CreateOrganization.php     # Create organization
│       └── AssignUserToOrganization.php # Assign user to org
│
└── Policies/                          # Authorization policies
    ├── TierPolicy.php                 # Tier authorization
    ├── ProductPolicy.php              # Product authorization
    ├── OrganizationPolicy.php         # Organization authorization
    └── UserPolicy.php                 # User authorization

resources/
├── views/
│   ├── livewire/
│   │   ├── super-admin/               # Super admin Blade views
│   │   │   ├── dashboard.blade.php
│   │   │   ├── cdp/
│   │   │   │   ├── tier-manager.blade.php
│   │   │   │   ├── product-manager.blade.php
│   │   │   │   ├── segment-builder.blade.php
│   │   │   │   ├── campaign-manager.blade.php
│   │   │   │   ├── template-manager.blade.php
│   │   │   │   └── sync-monitor.blade.php
│   │   │   ├── organizations/
│   │   │   │   ├── organization-manager.blade.php
│   │   │   │   ├── organization-details.blade.php
│   │   │   │   └── organization-tier-assignment.blade.php
│   │   │   ├── domains/
│   │   │   │   └── domain-manager.blade.php
│   │   │   ├── users/
│   │   │   │   ├── user-manager.blade.php
│   │   │   │   └── user-role-assignment.blade.php
│   │   │   └── settings/
│   │   │       ├── custom-fields-manager.blade.php
│   │   │       └── sync-logs.blade.php
│   │   │
│   │   ├── org-admin/                 # Org admin Blade views
│   │   │   ├── dashboard.blade.php
│   │   │   ├── team/
│   │   │   │   ├── team-members.blade.php
│   │   │   │   └── invite-user.blade.php
│   │   │   ├── products/
│   │   │   │   ├── product-subscriptions.blade.php
│   │   │   │   └── bulk-subscribe.blade.php
│   │   │   ├── reports/
│   │   │   │   ├── activity-report.blade.php
│   │   │   │   └── engagement-metrics.blade.php
│   │   │   └── settings/
│   │   │       └── organization-settings.blade.php
│   │   │
│   │   ├── user/                      # User Blade views
│   │   │   ├── dashboard.blade.php
│   │   │   ├── profile/
│   │   │   │   ├── edit-profile.blade.php
│   │   │   │   └── manage-consent.blade.php
│   │   │   ├── subscriptions/
│   │   │   │   ├── manage-subscriptions.blade.php
│   │   │   │   └── subscription-preferences.blade.php
│   │   │   └── activity/
│   │   │       └── activity-history.blade.php
│   │   │
│   │   └── shared/                    # Shared Blade components
│   │       ├── forms/
│   │       │   ├── tier-form.blade.php
│   │       │   ├── product-form.blade.php
│   │       │   └── segment-filter-form.blade.php
│   │       ├── tables/
│   │       │   ├── users-table.blade.php
│   │       │   └── subscriptions-table.blade.php
│   │       └── modals/
│   │           ├── confirmation-modal.blade.php
│   │           └── bulk-action-modal.blade.php
│   │
│   └── components/
│       └── layouts/
│           ├── super-admin.blade.php      # Super admin layout
│           ├── org-admin.blade.php        # Org admin layout
│           ├── user.blade.php             # User layout
│           └── partials/
│               ├── super-admin-sidebar.blade.php
│               ├── org-admin-sidebar.blade.php
│               └── user-sidebar.blade.php

routes/
├── web.php                    # Public routes + smart dashboard redirect
├── super-admin.php            # Super admin routes (prefix: /super-admin)
├── org-admin.php              # Org admin routes (prefix: /org)
└── user.php                   # User dashboard routes (prefix: /my)

database/
└── migrations/
    ├── 2025_11_13_200000_add_role_to_users_table.php
    ├── 2025_11_13_200100_create_organizations_table.php
    └── 2025_11_13_200200_create_organization_user_table.php
```

---

## 🛣️ Routing Strategy

### Route File Organization

Routes are organized by role to maintain clear separation and easier maintenance.

#### **routes/web.php** - Public & Shared Routes
```php
<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

// Smart dashboard redirect based on user role
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
});

require __DIR__.'/auth.php';
require __DIR__.'/super-admin.php';
require __DIR__.'/org-admin.php';
require __DIR__.'/user.php';
```

#### **routes/super-admin.php** - Super Admin Routes
```php
<?php

use App\Livewire\SuperAdmin;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:super_admin'])
    ->prefix('super-admin')
    ->name('super-admin.')
    ->group(function () {
        // Dashboard
        Route::get('/dashboard', SuperAdmin\Dashboard::class)->name('dashboard');

        // CDP Management
        Route::prefix('cdp')->name('cdp.')->group(function () {
            Route::get('/tiers', SuperAdmin\Cdp\TierManager::class)->name('tiers.index');
            Route::get('/products', SuperAdmin\Cdp\ProductManager::class)->name('products.index');
            Route::get('/segments', SuperAdmin\Cdp\SegmentBuilder::class)->name('segments.index');
            Route::get('/campaigns', SuperAdmin\Cdp\CampaignManager::class)->name('campaigns.index');
            Route::get('/templates', SuperAdmin\Cdp\TemplateManager::class)->name('templates.index');
            Route::get('/sync-monitor', SuperAdmin\Cdp\SyncMonitor::class)->name('sync-monitor');
        });

        // Organization Management
        Route::prefix('organizations')->name('organizations.')->group(function () {
            Route::get('/', SuperAdmin\Organizations\OrganizationManager::class)->name('index');
            Route::get('/{organization}', SuperAdmin\Organizations\OrganizationDetails::class)->name('show');
            Route::get('/{organization}/tier', SuperAdmin\Organizations\OrganizationTierAssignment::class)->name('tier');
        });

        // Domain Management
        Route::prefix('domains')->name('domains.')->group(function () {
            Route::get('/', SuperAdmin\Domains\DomainManager::class)->name('index');
        });

        // User Management
        Route::prefix('users')->name('users.')->group(function () {
            Route::get('/', SuperAdmin\Users\UserManager::class)->name('index');
            Route::get('/{user}/roles', SuperAdmin\Users\UserRoleAssignment::class)->name('roles');
        });

        // Settings
        Route::prefix('settings')->name('settings.')->group(function () {
            Route::get('/custom-fields', SuperAdmin\Settings\CustomFieldsManager::class)->name('custom-fields');
            Route::get('/sync-logs', SuperAdmin\Settings\SyncLogs::class)->name('sync-logs');
        });
    });
```

#### **routes/org-admin.php** - Organization Admin Routes
```php
<?php

use App\Livewire\OrgAdmin;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:org_admin'])
    ->prefix('org')
    ->name('org.')
    ->group(function () {
        // Dashboard
        Route::get('/dashboard', OrgAdmin\Dashboard::class)->name('dashboard');

        // Team Management
        Route::prefix('team')->name('team.')->group(function () {
            Route::get('/members', OrgAdmin\Team\TeamMembers::class)->name('members');
            Route::get('/invite', OrgAdmin\Team\InviteUser::class)->name('invite');
        });

        // Product Management
        Route::prefix('products')->name('products.')->group(function () {
            Route::get('/subscriptions', OrgAdmin\Products\ProductSubscriptions::class)->name('subscriptions');
            Route::get('/bulk-subscribe', OrgAdmin\Products\BulkSubscribe::class)->name('bulk-subscribe');
        });

        // Reports
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/activity', OrgAdmin\Reports\ActivityReport::class)->name('activity');
            Route::get('/engagement', OrgAdmin\Reports\EngagementMetrics::class)->name('engagement');
        });

        // Settings
        Route::prefix('settings')->name('settings.')->group(function () {
            Route::get('/', OrgAdmin\Settings\OrganizationSettings::class)->name('index');
        });
    });
```

#### **routes/user.php** - User Dashboard Routes
```php
<?php

use App\Livewire\User;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])
    ->prefix('my')
    ->name('user.')
    ->group(function () {
        // Dashboard
        Route::get('/dashboard', User\Dashboard::class)->name('dashboard');

        // Profile
        Route::prefix('profile')->name('profile.')->group(function () {
            Route::get('/edit', User\Profile\EditProfile::class)->name('edit');
            Route::get('/consent', User\Profile\ManageConsent::class)->name('consent');
        });

        // Subscriptions
        Route::prefix('subscriptions')->name('subscriptions.')->group(function () {
            Route::get('/', User\Subscriptions\ManageSubscriptions::class)->name('index');
            Route::get('/preferences', User\Subscriptions\SubscriptionPreferences::class)->name('preferences');
        });

        // Activity
        Route::prefix('activity')->name('activity.')->group(function () {
            Route::get('/history', User\Activity\ActivityHistory::class)->name('history');
        });
    });
```

---

## 🔑 Key Design Principles

### **1. Role-Based Separation of Concerns**

Each role has clearly defined responsibilities:

| Role | Namespace | Route Prefix | Layout | Sidebar |
|------|-----------|--------------|--------|---------|
| Super Admin | `App\Livewire\SuperAdmin` | `/super-admin` | `layouts.super-admin` | Platform-wide navigation |
| Org Admin | `App\Livewire\OrgAdmin` | `/org` | `layouts.org-admin` | Organization-scoped navigation |
| User | `App\Livewire\User` | `/my` | `layouts.user` | Personal navigation |

### **2. Livewire 4 Beta Features**

Leverage Livewire 4's new capabilities:

```php
<?php

namespace App\Livewire\SuperAdmin\Cdp;

use App\Livewire\Concerns\WithRoleAuthorization;
use App\Livewire\Concerns\WithDataTable;
use App\Models\Tier;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\Component;

#[Layout('layouts.super-admin')]
#[Title('Tier Management')]
class TierManager extends Component
{
    use WithRoleAuthorization, WithDataTable;

    public string $search = '';
    public bool $showCreateModal = false;

    #[Computed]
    public function tiers()
    {
        return Tier::query()
            ->with(['products', 'organizations'])
            ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->latest()
            ->paginate(15);
    }

    public function createTier()
    {
        $this->authorize('create', Tier::class);
        $this->showCreateModal = true;
    }

    public function render()
    {
        return view('livewire.super-admin.cdp.tier-manager');
    }
}
```

**Key Livewire 4 Features Used:**
- `#[Layout]` attribute for layout assignment
- `#[Title]` attribute for page title
- `#[Computed]` attribute for computed properties with automatic caching
- Traits for reusable component logic
- Type hints for better IDE support

### **3. Flux Pro Component Patterns**

Consistent use of Flux Pro components across all dashboards:

```blade
{{-- resources/views/livewire/super-admin/cdp/tier-manager.blade.php --}}
<div>
    <flux:header>
        <flux:heading size="xl">Tier Management</flux:heading>

        <flux:subheading>
            Manage subscription tiers and pricing for the CDP platform
        </flux:subheading>

        <flux:button wire:click="createTier" variant="primary" icon="plus">
            Create Tier
        </flux:button>
    </flux:header>

    <flux:separator />

    <flux:input wire:model.live="search" placeholder="Search tiers..." icon="search" />

    <flux:table>
        <flux:columns>
            <flux:column>Tier Name</flux:column>
            <flux:column>Price</flux:column>
            <flux:column>Products</flux:column>
            <flux:column>Organizations</flux:column>
            <flux:column>Status</flux:column>
            <flux:column>Actions</flux:column>
        </flux:columns>

        <flux:rows>
            @foreach($this->tiers as $tier)
                <flux:row wire:key="tier-{{ $tier->id }}">
                    <flux:cell>{{ $tier->name }}</flux:cell>
                    <flux:cell>${{ number_format($tier->price, 2) }}</flux:cell>
                    <flux:cell>{{ $tier->products_count }}</flux:cell>
                    <flux:cell>{{ $tier->organizations_count }}</flux:cell>
                    <flux:cell>
                        <flux:badge :color="$tier->is_active ? 'green' : 'gray'">
                            {{ $tier->is_active ? 'Active' : 'Inactive' }}
                        </flux:badge>
                    </flux:cell>
                    <flux:cell>
                        <flux:dropdown>
                            <flux:dropdown.item wire:click="editTier({{ $tier->id }})">
                                Edit
                            </flux:dropdown.item>
                            <flux:dropdown.item wire:click="deleteTier({{ $tier->id }})">
                                Delete
                            </flux:dropdown.item>
                        </flux:dropdown>
                    </flux:cell>
                </flux:row>
            @endforeach
        </flux:rows>
    </flux:table>

    {{ $this->tiers->links() }}
</div>
```

### **4. Shared Components for DRY Principle**

Reusable components eliminate code duplication:

```php
<?php

namespace App\Livewire\Shared\Forms;

use App\Models\Tier;
use Livewire\Component;

class TierForm extends Component
{
    public ?Tier $tier = null;
    public string $mode = 'create'; // 'create' or 'edit'

    public string $name = '';
    public string $slug = '';
    public float $price = 0.00;
    public string $description = '';
    public bool $is_active = true;

    public function mount(?Tier $tier = null, string $mode = 'create')
    {
        $this->tier = $tier;
        $this->mode = $mode;

        if ($tier) {
            $this->fill($tier->only(['name', 'slug', 'price', 'description', 'is_active']));
        }
    }

    public function save()
    {
        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:tiers,slug,' . $this->tier?->id,
            'price' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if ($this->mode === 'create') {
            $this->authorize('create', Tier::class);
            Tier::create($validated);
        } else {
            $this->authorize('update', $this->tier);
            $this->tier->update($validated);
        }

        $this->dispatch('tier-saved');
        $this->reset();
    }

    public function render()
    {
        return view('livewire.shared.forms.tier-form');
    }
}
```

### **5. Middleware-Based Authorization**

Route-level security using custom middleware:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user()) {
            return redirect()->route('login');
        }

        if (!$request->user()->isSuperAdmin()) {
            abort(403, 'Super Admin access required.');
        }

        return $next($request);
    }
}
```

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureOrgAdmin
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user()) {
            return redirect()->route('login');
        }

        if (!$request->user()->isOrgAdmin() && !$request->user()->isSuperAdmin()) {
            abort(403, 'Organization Admin access required.');
        }

        return $next($request);
    }
}
```

### **6. Smart Dashboard Redirect**

Automatically redirect users to appropriate dashboard based on role:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        // Super Admin gets platform dashboard
        if ($user->isSuperAdmin()) {
            return redirect()->route('super-admin.dashboard');
        }

        // Org Admin gets organization dashboard
        if ($user->isOrgAdmin()) {
            return redirect()->route('org.dashboard');
        }

        // Regular users get personal dashboard
        return redirect()->route('user.dashboard');
    }
}
```

---

## 📦 Database Schema Additions

### **Users Table - Add Role Column**

```php
// database/migrations/2025_11_13_200000_add_role_to_users_table.php

Schema::table('users', function (Blueprint $table) {
    if (!Schema::hasColumn('users', 'role')) {
        $table->enum('role', ['user', 'org_admin', 'super_admin'])
              ->default('user')
              ->after('email');
    }
});
```

### **Organizations Table**

```php
// database/migrations/2025_11_13_200100_create_organizations_table.php

Schema::create('organizations', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('slug')->unique();
    $table->foreignId('tier_id')->nullable()->constrained()->nullOnDelete();
    $table->text('description')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    $table->softDeletes();
});
```

### **Organization-User Pivot Table**

```php
// database/migrations/2025_11_13_200200_create_organization_user_table.php

Schema::create('organization_user', function (Blueprint $table) {
    $table->id();
    $table->foreignId('organization_id')->constrained()->onDelete('cascade');
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->enum('role', ['member', 'admin'])->default('member');
    $table->timestamps();

    $table->unique(['organization_id', 'user_id']);
});
```

---

## ✅ Benefits of This Architecture

| Benefit | Description |
|---------|-------------|
| **Clear Separation** | Each role has its own namespace, routes, and views |
| **Scalable** | Easy to add new roles, dashboards, or features |
| **Maintainable** | Related components grouped together logically |
| **Reusable** | Shared components reduce code duplication |
| **Secure** | Middleware enforces role-based access at route level |
| **Testable** | Clear boundaries for unit and feature testing |
| **Flux Pro Ready** | Optimized for Flux component patterns and best practices |
| **Livewire 4** | Uses new attributes, computed properties, and modern syntax |
| **Type Safe** | Full type hints for better IDE support and fewer bugs |
| **Performance** | Computed properties with automatic caching |

---

## 🚀 Implementation Phases

### **Phase 1: Foundation** (Current)
- [x] Database schema (tiers, products, subscriptions, activity tracking)
- [x] Core models (Tier, Product, UserProductSubscription)
- [x] User model enhancements
- [ ] Role system implementation
- [ ] Organization multi-tenancy
- [ ] Middleware setup

### **Phase 2: Super Admin Dashboard**
- [ ] Super Admin layout and sidebar
- [ ] Tier Manager component
- [ ] Product Manager component
- [ ] Organization Manager component
- [ ] User Manager component

### **Phase 3: Org Admin Dashboard**
- [ ] Org Admin layout and sidebar
- [ ] Team Members component
- [ ] Product Subscriptions component
- [ ] Reports and analytics

### **Phase 4: User Dashboard**
- [ ] User layout and sidebar
- [ ] Profile management
- [ ] Subscription preferences
- [ ] Activity history

### **Phase 5: Shared Components**
- [ ] Reusable forms
- [ ] Data tables with sorting/filtering
- [ ] Modal components
- [ ] Notification system

---

## 📚 Reference Implementation Examples

### Example: Livewire 4 Component with Flux Pro

```php
<?php

namespace App\Livewire\SuperAdmin\Cdp;

use App\Livewire\Concerns\WithRoleAuthorization;
use App\Models\Product;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.super-admin')]
#[Title('Product Management - CDP')]
class ProductManager extends Component
{
    use WithPagination, WithRoleAuthorization;

    public string $search = '';
    public string $categoryFilter = '';
    public bool $showCreateModal = false;

    #[Computed]
    public function products()
    {
        return Product::query()
            ->with(['tiers'])
            ->when($this->search, fn($q) =>
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhere('description', 'like', "%{$this->search}%")
            )
            ->when($this->categoryFilter, fn($q) =>
                $q->where('category', $this->categoryFilter)
            )
            ->latest()
            ->paginate(20);
    }

    #[Computed]
    public function categories()
    {
        return Product::distinct()->pluck('category')->filter();
    }

    public function createProduct()
    {
        $this->authorize('create', Product::class);
        $this->showCreateModal = true;
    }

    public function render()
    {
        return view('livewire.super-admin.cdp.product-manager');
    }
}
```

### Example: Flux Pro Sidebar

```blade
{{-- resources/views/components/layouts/partials/super-admin-sidebar.blade.php --}}
<flux:sidebar sticky class="bg-zinc-50 dark:bg-zinc-900">
    <flux:sidebar.toggle />

    <flux:brand href="{{ route('super-admin.dashboard') }}" logo="/logo.svg" name="CDP Platform" />

    <flux:navlist variant="outline">
        <flux:navlist.item icon="home" href="{{ route('super-admin.dashboard') }}" :current="request()->routeIs('super-admin.dashboard')">
            Dashboard
        </flux:navlist.item>

        <flux:navlist.group heading="CDP Management" expandable>
            <flux:navlist.item icon="layers" href="{{ route('super-admin.cdp.tiers.index') }}" :current="request()->routeIs('super-admin.cdp.tiers.*')">
                Tiers
            </flux:navlist.item>
            <flux:navlist.item icon="package" href="{{ route('super-admin.cdp.products.index') }}" :current="request()->routeIs('super-admin.cdp.products.*')">
                Products
            </flux:navlist.item>
            <flux:navlist.item icon="users" href="{{ route('super-admin.cdp.segments.index') }}" :current="request()->routeIs('super-admin.cdp.segments.*')">
                Segments
            </flux:navlist.item>
            <flux:navlist.item icon="mail" href="{{ route('super-admin.cdp.campaigns.index') }}" :current="request()->routeIs('super-admin.cdp.campaigns.*')">
                Campaigns
            </flux:navlist.item>
            <flux:navlist.item icon="calendar" href="{{ route('super-admin.cdp.templates.index') }}" :current="request()->routeIs('super-admin.cdp.templates.*')">
                Templates
            </flux:navlist.item>
            <flux:navlist.item icon="zap" href="{{ route('super-admin.cdp.sync-monitor') }}" :current="request()->routeIs('super-admin.cdp.sync-monitor')">
                Sync Monitor
            </flux:navlist.item>
        </flux:navlist.group>

        <flux:navlist.group heading="Platform Management" expandable>
            <flux:navlist.item icon="building" href="{{ route('super-admin.organizations.index') }}" :current="request()->routeIs('super-admin.organizations.*')">
                Organizations
            </flux:navlist.item>
            <flux:navlist.item icon="globe" href="{{ route('super-admin.domains.index') }}" :current="request()->routeIs('super-admin.domains.*')">
                Domains
            </flux:navlist.item>
            <flux:navlist.item icon="users" href="{{ route('super-admin.users.index') }}" :current="request()->routeIs('super-admin.users.*')">
                Users
            </flux:navlist.item>
        </flux:navlist.group>

        <flux:navlist.group heading="Settings" expandable>
            <flux:navlist.item icon="sliders" href="{{ route('super-admin.settings.custom-fields') }}" :current="request()->routeIs('super-admin.settings.custom-fields')">
                Custom Fields
            </flux:navlist.item>
            <flux:navlist.item icon="database" href="{{ route('super-admin.settings.sync-logs') }}" :current="request()->routeIs('super-admin.settings.sync-logs')">
                Sync Logs
            </flux:navlist.item>
        </flux:navlist.group>
    </flux:navlist>

    <flux:spacer />

    <flux:navlist variant="outline">
        <flux:navlist.item icon="cog" href="{{ route('profile.edit') }}">Settings</flux:navlist.item>
        <flux:navlist.item icon="arrow-right-on-rectangle" wire:click="logout">Logout</flux:navlist.item>
    </flux:navlist>
</flux:sidebar>
```

---

## 🔐 Security Considerations

1. **Route-Level Protection**: All role-specific routes protected by middleware
2. **Component Authorization**: Use `$this->authorize()` in component methods
3. **Policy-Based Access**: Laravel policies for fine-grained permissions
4. **CSRF Protection**: Automatic with Livewire forms
5. **SQL Injection Prevention**: Eloquent ORM prevents SQL injection
6. **XSS Protection**: Blade templates auto-escape output
7. **Organization Isolation**: Org admins can only access their organization's data

---

## 📖 Naming Conventions

| Type | Convention | Example |
|------|------------|---------|
| **Livewire Components** | PascalCase, singular noun | `TierManager`, `Dashboard` |
| **Blade Views** | kebab-case, matches component | `tier-manager.blade.php` |
| **Routes** | kebab-case, RESTful | `super-admin.cdp.tiers.index` |
| **Middleware** | PascalCase, starts with verb | `EnsureSuperAdmin` |
| **Policies** | PascalCase, ends with Policy | `TierPolicy` |
| **Services** | PascalCase, ends with Service | `TierService` |
| **Actions** | PascalCase, verb phrase | `CreateTier` |
| **Traits** | PascalCase, starts with With | `WithRoleAuthorization` |

---

**Document Version:** 1.0
**Author:** Claude AI
**Last Updated:** 2025-11-13

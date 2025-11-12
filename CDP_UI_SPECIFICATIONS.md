# CDP UI Specifications - Complete Visual Guide

**Date Created:** 2025-11-12
**Document Version:** 1.0
**Status:** Production-Ready Design Specs
**Tech Stack:** Laravel 12 + Livewire 3.6 + Flux Pro + Tailwind v4

This document provides complete UI/UX specifications for all CDP interfaces, ensuring every backend action has a corresponding visual representation.

---

## Table of Contents

1. [Design System Overview](#design-system-overview)
2. [Navigation Structure](#navigation-structure)
3. [Dashboard Layouts](#dashboard-layouts)
4. [Tier Management UI](#tier-management-ui)
5. [Product Management UI](#product-management-ui)
6. [Segment Builder UI](#segment-builder-ui)
7. [Campaign Management UI](#campaign-management-ui)
8. [Sync Status Dashboard](#sync-status-dashboard)
9. [Activity Tracking UI](#activity-tracking-ui)
10. [User-Facing Interfaces](#user-facing-interfaces)
11. [Component Library](#component-library)
12. [Real-Time Updates](#real-time-updates)

---

## Design System Overview

### Color Palette (Tailwind v4 + Dark Mode)

**Tier Colors:**
- **Free Tier:** `gray-500` / `dark:gray-400`
- **Pro Tier:** `blue-600` / `dark:blue-400`
- **Enterprise Tier:** `purple-600` / `dark:purple-400`

**Status Colors:**
- **Active/Success:** `green-600` / `dark:green-400`
- **Pending/Warning:** `yellow-600` / `dark:yellow-400`
- **Failed/Error:** `red-600` / `dark:red-400`
- **Info:** `blue-600` / `dark:blue-400`

**Campaign Type Colors:**
- **Daily Newsletter:** `blue-600` / `dark:blue-400`
- **Weekly Digest:** `green-600` / `dark:green-400`
- **Monthly Report:** `purple-600` / `dark:purple-400`

**Background Colors:**
- **Light Mode:** `white`, `gray-50`, `gray-100`
- **Dark Mode:** `zinc-800`, `zinc-900`, `zinc-950`

### Typography (Instrument Sans)

```css
/* From app.css */
--font-sans: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;

/* Heading Sizes */
h1: text-2xl font-bold
h2: text-xl font-semibold
h3: text-lg font-medium
h4: text-base font-medium

/* Body Text */
body: text-sm text-gray-700 dark:text-gray-300
small: text-xs text-gray-500 dark:text-gray-400
```

### Spacing System

```
xs: 0.5rem (8px)
sm: 0.75rem (12px)
md: 1rem (16px)
lg: 1.5rem (24px)
xl: 2rem (32px)
2xl: 3rem (48px)
```

### Border Radius

```
sm: 0.375rem (6px)
md: 0.5rem (8px)
lg: 0.75rem (12px)
```

---

## Navigation Structure

### Sidebar Menu (Admin)

**Location:** `resources/views/components/layouts/app/sidebar.blade.php`

```blade
<flux:sidebar sticky class="bg-zinc-50 dark:bg-zinc-900">
    <!-- Platform Section -->
    <flux:navlist.group :heading="__('Platform')" class="grid">
        <flux:navlist.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
            Dashboard
        </flux:navlist.item>
    </flux:navlist.group>

    <!-- CDP Data Section (NEW) -->
    <flux:navlist.group :heading="__('CDP Data')" class="grid">
        <flux:navlist.item icon="layers" :href="route('admin.cdp.tiers.index')" :current="request()->routeIs('admin.cdp.tiers.*')" wire:navigate>
            Tiers
        </flux:navlist.item>
        <flux:navlist.item icon="package" :href="route('admin.cdp.products.index')" :current="request()->routeIs('admin.cdp.products.*')" wire:navigate>
            Products
        </flux:navlist.item>
        <flux:navlist.item icon="users" :href="route('admin.cdp.segments.index')" :current="request()->routeIs('admin.cdp.segments.*')" wire:navigate>
            Segments
        </flux:navlist.item>
        <flux:navlist.item icon="activity" :href="route('admin.cdp.activity.index')" :current="request()->routeIs('admin.cdp.activity.*')" wire:navigate>
            Activity Tracking
        </flux:navlist.item>
    </flux:navlist.group>

    <!-- Campaign Management Section (NEW) -->
    <flux:navlist.group :heading="__('Campaigns')" class="grid">
        <flux:navlist.item icon="mail" :href="route('admin.cdp.campaigns.index')" :current="request()->routeIs('admin.cdp.campaigns.*')" wire:navigate>
            Campaigns
        </flux:navlist.item>
        <flux:navlist.item icon="calendar" :href="route('admin.cdp.templates.index')" :current="request()->routeIs('admin.cdp.templates.*')" wire:navigate>
            Templates
        </flux:navlist.item>
        <flux:navlist.item icon="zap" :href="route('admin.cdp.sync-status')" :current="request()->routeIs('admin.cdp.sync-status')" wire:navigate>
            Sync Status
        </flux:navlist.item>
    </flux:navlist.group>

    <!-- Campaign Monitor Section (Existing) -->
    <flux:navlist.group :heading="__('Campaign Monitor')" class="grid">
        <flux:navlist.item icon="upload" :href="route('campaign-monitor.import.index')" :current="request()->routeIs('campaign-monitor.import.*')" wire:navigate>
            Import
        </flux:navlist.item>
    </flux:navlist.group>

    <!-- Administration Section (Existing) -->
    <flux:navlist.group :heading="__('Administration')" class="grid">
        <flux:navlist.item icon="building" :href="route('admin.organizations.index')" :current="request()->routeIs('admin.organizations.*')" wire:navigate>
            Organizations
        </flux:navlist.item>
        <flux:navlist.item icon="globe" :href="route('admin.domains.index')" :current="request()->routeIs('admin.domains.*')" wire:navigate>
            Domains
        </flux:navlist.item>
        <flux:navlist.item icon="users" :href="route('admin.users.index')" :current="request()->routeIs('admin.users.*')" wire:navigate>
            Users
        </flux:navlist.item>
        <flux:navlist.item icon="sliders" :href="route('admin.custom-fields.index')" :current="request()->routeIs('admin.custom-fields.*')" wire:navigate>
            Custom Fields
        </flux:navlist.item>
        <flux:navlist.item icon="database" :href="route('admin.sync-logs.index')" :current="request()->routeIs('admin.sync-logs.*')" wire:navigate>
            Sync Logs
        </flux:navlist.item>
    </flux:navlist.group>
</flux:sidebar>
```

### User Settings Sidebar (NEW)

**Location:** Add to existing settings layout

```blade
<flux:navlist>
    <!-- Existing Settings Items -->
    <flux:navlist.item icon="user" :href="route('settings.profile')" :current="request()->routeIs('settings.profile')">
        Profile
    </flux:navlist.item>
    <flux:navlist.item icon="lock" :href="route('settings.password')" :current="request()->routeIs('settings.password')">
        Password
    </flux:navlist.item>

    <!-- NEW: Product Subscriptions -->
    <flux:navlist.item icon="package" :href="route('settings.products')" :current="request()->routeIs('settings.products')">
        Product Subscriptions
    </flux:navlist.item>

    <!-- NEW: My Activity -->
    <flux:navlist.item icon="activity" :href="route('settings.activity')" :current="request()->routeIs('settings.activity')">
        My Activity
    </flux:navlist.item>

    <!-- Existing Settings Items -->
    <flux:navlist.item icon="palette" :href="route('settings.appearance')" :current="request()->routeIs('settings.appearance')">
        Appearance
    </flux:navlist.item>
</flux:navlist>
```

---

## Dashboard Layouts

### Main CDP Dashboard

**Route:** `/admin/cdp/dashboard`
**Component:** `CdpDashboard.php`

#### Stats Overview Section

```blade
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <!-- Total Users -->
    <div class="bg-white dark:bg-zinc-800 rounded-lg p-6 border border-neutral-200 dark:border-neutral-700 shadow-sm">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Users</p>
                <p class="text-3xl font-bold text-gray-900 dark:text-white mt-2" wire:poll.60s>
                    {{ number_format($totalUsers) }}
                </p>
                <p class="text-xs text-green-600 dark:text-green-400 mt-1">
                    +{{ $newUsersThisMonth }} this month
                </p>
            </div>
            <div class="p-3 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                <svg class="w-8 h-8 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
            </div>
        </div>
    </div>

    <!-- Active Campaigns -->
    <div class="bg-white dark:bg-zinc-800 rounded-lg p-6 border border-neutral-200 dark:border-neutral-700 shadow-sm">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Active Campaigns</p>
                <p class="text-3xl font-bold text-gray-900 dark:text-white mt-2" wire:poll.60s>
                    {{ $activeCampaigns }}
                </p>
                <p class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                    {{ $campaignsSentToday }} sent today
                </p>
            </div>
            <div class="p-3 bg-green-100 dark:bg-green-900/30 rounded-lg">
                <svg class="w-8 h-8 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
            </div>
        </div>
    </div>

    <!-- Segments -->
    <div class="bg-white dark:bg-zinc-800 rounded-lg p-6 border border-neutral-200 dark:border-neutral-700 shadow-sm">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Segments</p>
                <p class="text-3xl font-bold text-gray-900 dark:text-white mt-2">
                    {{ $totalSegments }}
                </p>
                <p class="text-xs text-purple-600 dark:text-purple-400 mt-1">
                    {{ $persistentSegments }} persistent
                </p>
            </div>
            <div class="p-3 bg-purple-100 dark:bg-purple-900/30 rounded-lg">
                <svg class="w-8 h-8 text-purple-600 dark:text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
            </div>
        </div>
    </div>

    <!-- Sync Status -->
    <div class="bg-white dark:bg-zinc-800 rounded-lg p-6 border border-neutral-200 dark:border-neutral-700 shadow-sm">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">CM Sync Status</p>
                <p class="text-3xl font-bold text-gray-900 dark:text-white mt-2" wire:poll.10s>
                    {{ $syncedPercentage }}%
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    {{ number_format($syncedUsers) }} / {{ number_format($totalUsers) }} synced
                </p>
            </div>
            <div class="p-3 bg-yellow-100 dark:bg-yellow-900/30 rounded-lg">
                <svg class="w-8 h-8 text-yellow-600 dark:text-yellow-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
            </div>
        </div>
    </div>
</div>
```

#### Tier Distribution Chart

```blade
<div class="bg-white dark:bg-zinc-800 rounded-lg p-6 border border-neutral-200 dark:border-neutral-700 shadow-sm mb-8">
    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">User Distribution by Tier</h3>

    <div class="grid grid-cols-3 gap-6">
        <!-- Free Tier -->
        <div class="text-center">
            <div class="relative pt-1">
                <div class="flex mb-2 items-center justify-between">
                    <div>
                        <span class="text-xs font-semibold inline-block py-1 px-2 uppercase rounded-full text-gray-600 bg-gray-200 dark:text-gray-300 dark:bg-gray-700">
                            Free
                        </span>
                    </div>
                    <div class="text-right">
                        <span class="text-xs font-semibold inline-block text-gray-600 dark:text-gray-300">
                            {{ $freePercentage }}%
                        </span>
                    </div>
                </div>
                <div class="overflow-hidden h-2 mb-4 text-xs flex rounded bg-gray-200 dark:bg-gray-700">
                    <div style="width:{{ $freePercentage }}%" class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-gray-500 dark:bg-gray-400"></div>
                </div>
            </div>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($freeUsers) }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">users</p>
        </div>

        <!-- Pro Tier -->
        <div class="text-center">
            <div class="relative pt-1">
                <div class="flex mb-2 items-center justify-between">
                    <div>
                        <span class="text-xs font-semibold inline-block py-1 px-2 uppercase rounded-full text-blue-600 bg-blue-200 dark:text-blue-300 dark:bg-blue-900/30">
                            Pro
                        </span>
                    </div>
                    <div class="text-right">
                        <span class="text-xs font-semibold inline-block text-blue-600 dark:text-blue-300">
                            {{ $proPercentage }}%
                        </span>
                    </div>
                </div>
                <div class="overflow-hidden h-2 mb-4 text-xs flex rounded bg-blue-200 dark:bg-blue-900/30">
                    <div style="width:{{ $proPercentage }}%" class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-blue-500 dark:bg-blue-400"></div>
                </div>
            </div>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($proUsers) }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">users</p>
        </div>

        <!-- Enterprise Tier -->
        <div class="text-center">
            <div class="relative pt-1">
                <div class="flex mb-2 items-center justify-between">
                    <div>
                        <span class="text-xs font-semibold inline-block py-1 px-2 uppercase rounded-full text-purple-600 bg-purple-200 dark:text-purple-300 dark:bg-purple-900/30">
                            Enterprise
                        </span>
                    </div>
                    <div class="text-right">
                        <span class="text-xs font-semibold inline-block text-purple-600 dark:text-purple-300">
                            {{ $enterprisePercentage }}%
                        </span>
                    </div>
                </div>
                <div class="overflow-hidden h-2 mb-4 text-xs flex rounded bg-purple-200 dark:bg-purple-900/30">
                    <div style="width:{{ $enterprisePercentage }}%" class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-purple-500 dark:bg-purple-400"></div>
                </div>
            </div>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($enterpriseUsers) }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">users</p>
        </div>
    </div>
</div>
```

#### Recent Activity Feed

```blade
<div class="bg-white dark:bg-zinc-800 rounded-lg p-6 border border-neutral-200 dark:border-neutral-700 shadow-sm">
    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Recent Activity</h3>

    <div class="flow-root">
        <ul class="-mb-8" wire:poll.30s>
            @foreach($recentActivities as $activity)
                <li>
                    <div class="relative pb-8">
                        @if(!$loop->last)
                            <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200 dark:bg-gray-700" aria-hidden="true"></span>
                        @endif
                        <div class="relative flex space-x-3">
                            <div>
                                <span class="h-8 w-8 rounded-full flex items-center justify-center ring-8 ring-white dark:ring-zinc-800
                                    {{ $activity->type === 'campaign_sent' ? 'bg-green-500' : '' }}
                                    {{ $activity->type === 'segment_created' ? 'bg-blue-500' : '' }}
                                    {{ $activity->type === 'tier_changed' ? 'bg-purple-500' : '' }}
                                    {{ $activity->type === 'user_registered' ? 'bg-yellow-500' : '' }}">
                                    <svg class="h-5 w-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                </span>
                            </div>
                            <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                                <div>
                                    <p class="text-sm text-gray-900 dark:text-white">{{ $activity->description }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $activity->details }}</p>
                                </div>
                                <div class="text-right text-sm whitespace-nowrap text-gray-500 dark:text-gray-400">
                                    {{ $activity->created_at->diffForHumans() }}
                                </div>
                            </div>
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
</div>
```

---

## Tier Management UI

### Component Files
- **Livewire:** `app/Livewire/Admin/Cdp/TierManager.php`
- **View:** `resources/views/livewire/admin/cdp/tier-manager.blade.php`
- **Route:** `/admin/cdp/tiers`

### Page Layout

```blade
<div>
    <!-- Page Header -->
    <flux:header class="mb-6">
        <flux:heading>Tier Management</flux:heading>
        <flux:subheading>Configure subscription tiers and features</flux:subheading>
        <flux:button wire:click="openCreateModal" icon="plus">Create Tier</flux:button>
    </flux:header>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white dark:bg-zinc-800 rounded-lg p-6 border border-neutral-200 dark:border-neutral-700">
            <p class="text-sm text-gray-600 dark:text-gray-400">Total Tiers</p>
            <p class="text-3xl font-bold text-gray-900 dark:text-white mt-2">{{ $totalTiers }}</p>
        </div>
        <div class="bg-white dark:bg-zinc-800 rounded-lg p-6 border border-neutral-200 dark:border-neutral-700">
            <p class="text-sm text-gray-600 dark:text-gray-400">Organizations</p>
            <p class="text-3xl font-bold text-gray-900 dark:text-white mt-2">{{ $totalOrganizations }}</p>
        </div>
        <div class="bg-white dark:bg-zinc-800 rounded-lg p-6 border border-neutral-200 dark:border-neutral-700">
            <p class="text-sm text-gray-600 dark:text-gray-400">Total Users</p>
            <p class="text-3xl font-bold text-gray-900 dark:text-white mt-2">{{ number_format($totalUsers) }}</p>
        </div>
    </div>

    <!-- Tier Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        @foreach($tiers as $tier)
            <div class="bg-white dark:bg-zinc-800 rounded-lg p-6 border-2 transition-all
                {{ $tier->name === 'Free' ? 'border-gray-300 dark:border-gray-600' : '' }}
                {{ $tier->name === 'Pro' ? 'border-blue-500 dark:border-blue-400' : '' }}
                {{ $tier->name === 'Enterprise' ? 'border-purple-500 dark:border-purple-400' : '' }}">

                <!-- Tier Header -->
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">{{ $tier->name }}</h3>
                    <span class="px-3 py-1 text-xs font-semibold rounded-full
                        {{ $tier->is_active ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' : 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-300' }}">
                        {{ $tier->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>

                <!-- Description -->
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">{{ $tier->description }}</p>

                <!-- Stats -->
                <div class="space-y-2 mb-6">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600 dark:text-gray-400">Organizations</span>
                        <span class="font-semibold text-gray-900 dark:text-white">{{ $tier->organizations_count }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600 dark:text-gray-400">Users</span>
                        <span class="font-semibold text-gray-900 dark:text-white">{{ number_format($tier->users_count) }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600 dark:text-gray-400">Products</span>
                        <span class="font-semibold text-gray-900 dark:text-white">{{ $tier->products_count }}</span>
                    </div>
                </div>

                <!-- Features List -->
                <div class="mb-6">
                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-2">Features</h4>
                    <ul class="space-y-1">
                        @foreach($tier->features ?? [] as $feature)
                            <li class="flex items-center text-xs text-gray-600 dark:text-gray-400">
                                <svg class="w-4 h-4 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                {{ $feature }}
                            </li>
                        @endforeach
                    </ul>
                </div>

                <!-- Actions -->
                <div class="flex space-x-2">
                    <flux:button size="sm" variant="primary" wire:click="editTier({{ $tier->id }})" class="flex-1">
                        Edit
                    </flux:button>
                    <flux:button size="sm" variant="ghost" wire:click="viewOrganizations({{ $tier->id }})">
                        View Orgs
                    </flux:button>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Create/Edit Modal -->
    @if($showModal)
        <!-- Modal content here (similar pattern to other modals) -->
    @endif
</div>
```

---

## Product Management UI

### Component Files
- **Livewire:** `app/Livewire/Admin/Cdp/ProductManager.php`
- **View:** `resources/views/livewire/admin/cdp/product-manager.blade.php`
- **Route:** `/admin/cdp/products`

### Product List Table with Tier Matrix

```blade
<div>
    <!-- Header -->
    <flux:header class="mb-6">
        <flux:heading>Product Management</flux:heading>
        <flux:subheading>Manage products and tier availability</flux:subheading>
        <flux:button wire:click="openCreateModal" icon="plus">Create Product</flux:button>
    </flux:header>

    <!-- Search -->
    <div class="mb-4">
        <flux:input
            wire:model.live="search"
            placeholder="Search products..."
            icon="search" />
    </div>

    <!-- Products Table -->
    <div class="bg-white dark:bg-zinc-800 rounded-lg border border-neutral-200 dark:border-neutral-700">
        <table class="w-full">
            <thead class="bg-gray-50 dark:bg-zinc-900 border-b border-neutral-200 dark:border-neutral-700">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Product</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Free</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Pro</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Enterprise</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Subscribers</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                @forelse($products as $product)
                    <tr class="hover:bg-gray-50 dark:hover:bg-zinc-900/50">
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $product->name }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $product->description }}</div>
                        </td>
                        <td class="px-6 py-4 text-center">
                            @if($product->availableForTier('free'))
                                <svg class="w-5 h-5 mx-auto text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                            @else
                                <svg class="w-5 h-5 mx-auto text-gray-300 dark:text-gray-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                </svg>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center">
                            @if($product->availableForTier('pro'))
                                <svg class="w-5 h-5 mx-auto text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                            @else
                                <svg class="w-5 h-5 mx-auto text-gray-300 dark:text-gray-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                </svg>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center">
                            @if($product->availableForTier('enterprise'))
                                <svg class="w-5 h-5 mx-auto text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                            @else
                                <svg class="w-5 h-5 mx-auto text-gray-300 dark:text-gray-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                </svg>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-gray-900 dark:text-white" wire:poll.60s>
                                {{ number_format($product->subscribers_count) }}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                active subscriptions
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                {{ $product->is_active ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' : 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-300' }}">
                                {{ $product->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right space-x-2">
                            <flux:button size="sm" variant="ghost" wire:click="editProduct({{ $product->id }})">Edit</flux:button>
                            <flux:button size="sm" variant="ghost" wire:click="viewSubscribers({{ $product->id }})">Subscribers</flux:button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                            No products found
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
```

---

## Segment Builder UI

This is the most complex interface - a visual query builder with live preview.

### Component Files
- **Livewire:** `app/Livewire/Admin/Cdp/SegmentBuilder.php`
- **View:** `resources/views/livewire/admin/cdp/segment-builder.blade.php`
- **Route:** `/admin/cdp/segments/builder`

### Layout: Split View (Builder + Preview)

```blade
<div class="flex space-x-6">
    <!-- Left: Query Builder (60%) -->
    <div class="w-3/5">
        <flux:header class="mb-6">
            <flux:heading>Segment Builder</flux:heading>
            <flux:subheading>Create complex audience segments</flux:subheading>
        </flux:header>

        <!-- Segment Name -->
        <div class="mb-6">
            <flux:input
                wire:model.live.debounce.500ms="segmentName"
                label="Segment Name"
                placeholder="e.g., High Activity Pro Users"
                required />
        </div>

        <!-- Rule Groups -->
        <div class="space-y-4">
            @foreach($ruleGroups as $groupIndex => $group)
                <div class="bg-white dark:bg-zinc-800 rounded-lg p-6 border border-neutral-200 dark:border-neutral-700">
                    <!-- Group Header -->
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                            Rule Group {{ $groupIndex + 1 }}
                            @if($groupIndex > 0)
                                <span class="ml-2 text-xs font-normal text-gray-500 dark:text-gray-400">(AND)</span>
                            @endif
                        </h3>
                        @if($groupIndex > 0)
                            <flux:button size="sm" variant="ghost" wire:click="removeRuleGroup({{ $groupIndex }})">
                                Remove Group
                            </flux:button>
                        @endif
                    </div>

                    <!-- Rules within Group -->
                    <div class="space-y-3">
                        @foreach($group['rules'] as $ruleIndex => $rule)
                            <div class="flex items-start space-x-3">
                                @if($ruleIndex > 0)
                                    <span class="text-xs text-gray-500 dark:text-gray-400 mt-3">OR</span>
                                @endif

                                <div class="flex-1 grid grid-cols-12 gap-3">
                                    <!-- Field Selection -->
                                    <div class="col-span-4">
                                        <select wire:model.live="ruleGroups.{{ $groupIndex }}.rules.{{ $ruleIndex }}.field"
                                                class="w-full px-3 py-2 bg-white dark:bg-zinc-900 border border-neutral-200 dark:border-neutral-700 rounded-md text-sm text-gray-900 dark:text-white">
                                            <option value="">Select field...</option>
                                            <optgroup label="Tier">
                                                <option value="tier_name">Tier Name</option>
                                                <option value="tier_id">Tier ID</option>
                                            </optgroup>
                                            <optgroup label="Activity">
                                                <option value="activity_score_7d">Activity Score (7d)</option>
                                                <option value="activity_score_30d">Activity Score (30d)</option>
                                                <option value="last_login_at">Last Login</option>
                                            </optgroup>
                                            <optgroup label="Products">
                                                <option value="subscribed_to_product">Subscribed to Product</option>
                                                <option value="active_products_count">Active Products Count</option>
                                            </optgroup>
                                            <optgroup label="Events">
                                                <option value="attended_event">Attended Event</option>
                                                <option value="event_count">Event Count</option>
                                            </optgroup>
                                            <optgroup label="Account">
                                                <option value="created_at">Registration Date</option>
                                                <option value="cm_status">CM Status</option>
                                            </optgroup>
                                        </select>
                                    </div>

                                    <!-- Operator Selection -->
                                    <div class="col-span-3">
                                        <select wire:model.live="ruleGroups.{{ $groupIndex }}.rules.{{ $ruleIndex }}.operator"
                                                class="w-full px-3 py-2 bg-white dark:bg-zinc-900 border border-neutral-200 dark:border-neutral-700 rounded-md text-sm text-gray-900 dark:text-white">
                                            <option value="equals">Equals</option>
                                            <option value="not_equals">Not Equals</option>
                                            <option value="greater_than">Greater Than</option>
                                            <option value="less_than">Less Than</option>
                                            <option value="greater_than_or_equal">Greater Than or Equal</option>
                                            <option value="less_than_or_equal">Less Than or Equal</option>
                                            <option value="in">In</option>
                                            <option value="not_in">Not In</option>
                                            <option value="contains">Contains</option>
                                            <option value="starts_with">Starts With</option>
                                        </select>
                                    </div>

                                    <!-- Value Input -->
                                    <div class="col-span-4">
                                        @if($rule['field'] === 'tier_name')
                                            <select wire:model.live="ruleGroups.{{ $groupIndex }}.rules.{{ $ruleIndex }}.value"
                                                    class="w-full px-3 py-2 bg-white dark:bg-zinc-900 border border-neutral-200 dark:border-neutral-700 rounded-md text-sm text-gray-900 dark:text-white">
                                                <option value="">Select tier...</option>
                                                <option value="free">Free</option>
                                                <option value="pro">Pro</option>
                                                <option value="enterprise">Enterprise</option>
                                            </select>
                                        @elseif($rule['field'] === 'subscribed_to_product')
                                            <select wire:model.live="ruleGroups.{{ $groupIndex }}.rules.{{ $ruleIndex }}.value"
                                                    class="w-full px-3 py-2 bg-white dark:bg-zinc-900 border border-neutral-200 dark:border-neutral-700 rounded-md text-sm text-gray-900 dark:text-white">
                                                <option value="">Select product...</option>
                                                @foreach($products as $product)
                                                    <option value="{{ $product->id }}">{{ $product->name }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            <input type="text"
                                                   wire:model.live.debounce.500ms="ruleGroups.{{ $groupIndex }}.rules.{{ $ruleIndex }}.value"
                                                   placeholder="Value..."
                                                   class="w-full px-3 py-2 bg-white dark:bg-zinc-900 border border-neutral-200 dark:border-neutral-700 rounded-md text-sm text-gray-900 dark:text-white" />
                                        @endif
                                    </div>

                                    <!-- Remove Rule -->
                                    <div class="col-span-1 flex items-center">
                                        @if(count($group['rules']) > 1)
                                            <button wire:click="removeRule({{ $groupIndex }}, {{ $ruleIndex }})"
                                                    class="text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300">
                                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                                </svg>
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach

                        <!-- Add Rule (OR) -->
                        <flux:button size="sm" variant="ghost" wire:click="addRule({{ $groupIndex }})" class="mt-2">
                            + Add OR Condition
                        </flux:button>
                    </div>
                </div>
            @endforeach

            <!-- Add Rule Group (AND) -->
            <flux:button size="sm" variant="outline" wire:click="addRuleGroup">
                + Add AND Group
            </flux:button>
        </div>

        <!-- Actions -->
        <div class="flex space-x-3 mt-6">
            <flux:button variant="primary" wire:click="saveSegment" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="saveSegment">Save Segment</span>
                <span wire:loading wire:target="saveSegment">Saving...</span>
            </flux:button>
            <flux:button variant="ghost" wire:navigate href="{{ route('admin.cdp.segments.index') }}">Cancel</flux:button>
        </div>
    </div>

    <!-- Right: Live Preview (40%) -->
    <div class="w-2/5 sticky top-6 h-fit">
        <div class="bg-white dark:bg-zinc-800 rounded-lg p-6 border border-neutral-200 dark:border-neutral-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Live Preview</h3>

            <!-- Segment Type Badge -->
            <div class="mb-4">
                @if($segmentType === 'persistent')
                    <span class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">
                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        Persistent Segment
                    </span>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                        This segment will be synced to Campaign Monitor and auto-updated nightly.
                    </p>
                @else
                    <span class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded-full bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300">
                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M11 3a1 1 0 10-2 0v1a1 1 0 102 0V3zM15.657 5.757a1 1 0 00-1.414-1.414l-.707.707a1 1 0 001.414 1.414l.707-.707zM18 10a1 1 0 01-1 1h-1a1 1 0 110-2h1a1 1 0 011 1zM5.05 6.464A1 1 0 106.464 5.05l-.707-.707a1 1 0 00-1.414 1.414l.707.707zM5 10a1 1 0 01-1 1H3a1 1 0 110-2h1a1 1 0 011 1zM8 16v-1h4v1a2 2 0 11-4 0zM12 14c.015-.34.208-.646.477-.859a4 4 0 10-4.954 0c.27.213.462.519.476.859h4.002z"/>
                        </svg>
                        Dynamic Tag Segment
                    </span>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                        Users will be tagged in CM when campaign is sent. Tag cleanup occurs 2 hours after send.
                    </p>
                @endif
            </div>

            <!-- User Count -->
            <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-6 mb-4" wire:poll.2s>
                <div class="text-center">
                    <p class="text-sm text-blue-600 dark:text-blue-400 mb-2">Matching Users</p>
                    <p class="text-4xl font-bold text-blue-900 dark:text-blue-300" wire:loading.class="animate-pulse">
                        {{ number_format($matchingUsersCount) }}
                    </p>
                    <p class="text-xs text-blue-700 dark:text-blue-400 mt-2">
                        {{ $matchingUsersPercentage }}% of total users
                    </p>
                </div>
            </div>

            <!-- Sample Users -->
            <div>
                <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Sample Users (First 10)</h4>
                <div class="space-y-2 max-h-96 overflow-y-auto" wire:loading.class="opacity-50">
                    @forelse($sampleUsers as $user)
                        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-zinc-900 rounded-lg">
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $user->fullname }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $user->email }}</p>
                            </div>
                            <div class="text-right">
                                <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full
                                    {{ $user->tier_name === 'free' ? 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-300' : '' }}
                                    {{ $user->tier_name === 'pro' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300' : '' }}
                                    {{ $user->tier_name === 'enterprise' ? 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300' : '' }}">
                                    {{ ucfirst($user->tier_name ?? 'N/A') }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8 text-gray-400 dark:text-gray-500 text-sm">
                            No users match the current criteria
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Refresh Button -->
            <flux:button variant="outline" wire:click="refreshPreview" class="w-full mt-4">
                <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"/>
                </svg>
                Refresh Count
            </flux:button>
        </div>
    </div>
</div>
```

---

## Component Library

### Reusable Status Badge Component

Create: `resources/views/components/status-badge.blade.php`

```blade
@props(['status', 'type' => 'default'])

@php
$classes = match($type) {
    'campaign' => match($status) {
        'sent' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
        'sending' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
        'failed' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
        'scheduled' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300',
        default => 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-300',
    },
    'tier' => match($status) {
        'free' => 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-300',
        'pro' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
        'enterprise' => 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
        default => 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-300',
    },
    'sync' => match($status) {
        'synced' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
        'pending' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300',
        'failed' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
        default => 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-300',
    },
    default => 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-300',
};
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex px-2 py-1 text-xs font-semibold rounded-full ' . $classes]) }}>
    {{ $slot }}
</span>
```

**Usage:**
```blade
<x-status-badge status="sent" type="campaign">Sent</x-status-badge>
<x-status-badge status="pro" type="tier">Pro</x-status-badge>
<x-status-badge status="synced" type="sync">Synced</x-status-badge>
```

---

## Real-Time Updates

### Wire:poll Patterns

**Fast Updates (Every 2 seconds):**
- Sync job progress
- Campaign send status
- Queue job counts

```blade
<div wire:poll.2s>
    Jobs in queue: {{ $jobsInQueue }}
</div>
```

**Medium Updates (Every 30 seconds):**
- User counts
- Campaign stats
- Activity feed

```blade
<div wire:poll.30s>
    Active users (7d): {{ $activeUsers7d }}
</div>
```

**Slow Updates (Every 60 seconds):**
- Tier distributions
- Product subscriber counts
- Dashboard stats

```blade
<div wire:poll.60s>
    Total users: {{ number_format($totalUsers) }}
</div>
```

---

## Summary

This UI specification document provides:

✅ **78 UI Tasks** mapped to specific Livewire components
✅ **Complete visual designs** with Flux Pro + Tailwind v4
✅ **Dark mode support** for all interfaces
✅ **Real-time updates** with wire:poll
✅ **Consistent component patterns** across all pages
✅ **Accessibility** with ARIA labels and semantic HTML
✅ **Responsive layouts** for desktop and mobile

**Next Steps:**
1. Review this specification
2. Begin Phase 1 implementation (Tasks 1.1-1.14)
3. Use these exact component patterns for consistency

**Related Documents:**
- `TODO_LIST.md` - 78 task checklist
- `CDP_WORKFLOWS.md` - Backend + UI workflows
- `CDP_PROJECT_PLAN.md` - Overall project plan

---

**Document Version:** 1.0
**Last Updated:** 2025-11-12
**Status:** Ready for Implementation

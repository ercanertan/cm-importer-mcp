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
                                                <option value="not_subscribed_to_product">NOT Subscribed to Product</option>
                                                <option value="opted_out_of_product">Opted Out of Product</option>
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
                                        @elseif(in_array($rule['field'], ['subscribed_to_product', 'not_subscribed_to_product', 'opted_out_of_product']))
                                            @if($rule['operator'] === 'in')
                                                <!-- Multi-Product Checkbox Selection (OR logic) -->
                                                <div class="space-y-2 max-h-48 overflow-y-auto p-2 bg-gray-50 dark:bg-zinc-900/50 rounded border border-neutral-200 dark:border-neutral-700">
                                                    @foreach($products as $product)
                                                        <label class="flex items-center cursor-pointer hover:bg-gray-100 dark:hover:bg-zinc-800 p-1 rounded">
                                                            <input type="checkbox"
                                                                   wire:model.live="ruleGroups.{{ $groupIndex }}.rules.{{ $ruleIndex }}.value"
                                                                   value="{{ $product->id }}"
                                                                   class="w-4 h-4 text-blue-600 bg-white dark:bg-zinc-800 border-gray-300 dark:border-neutral-600 rounded focus:ring-blue-500">
                                                            <span class="ml-2 text-sm text-gray-900 dark:text-white">{{ $product->name }}</span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            @else
                                                <!-- Single Product Dropdown -->
                                                <select wire:model.live="ruleGroups.{{ $groupIndex }}.rules.{{ $ruleIndex }}.value"
                                                        class="w-full px-3 py-2 bg-white dark:bg-zinc-900 border border-neutral-200 dark:border-neutral-700 rounded-md text-sm text-gray-900 dark:text-white">
                                                    <option value="">Select product...</option>
                                                    @foreach($products as $product)
                                                        <option value="{{ $product->id }}">{{ $product->name }}</option>
                                                    @endforeach
                                                </select>
                                            @endif
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

            <!-- Sample Users with Product Subscriptions -->
            <div>
                <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Sample Users (First 10)</h4>
                <div class="space-y-2 max-h-96 overflow-y-auto" wire:loading.class="opacity-50">
                    @forelse($sampleUsers as $user)
                        <div class="p-3 bg-gray-50 dark:bg-zinc-900 rounded-lg">
                            <!-- User Info -->
                            <div class="flex items-center justify-between mb-2">
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

                            <!-- Product Subscriptions (if product filtering is active) -->
                            @if($hasProductFilters && $user->productSubscriptions->isNotEmpty())
                                <div class="flex flex-wrap gap-1 mt-2 pt-2 border-t border-gray-200 dark:border-zinc-700">
                                    @foreach($user->productSubscriptions->where('is_active', true) as $subscription)
                                        <span class="inline-flex items-center px-2 py-0.5 text-xs rounded bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
                                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                            </svg>
                                            {{ $subscription->product->name }}
                                        </span>
                                    @endforeach
                                    @foreach($user->productSubscriptions->where('is_active', false)->whereNotNull('unsubscribed_at') as $subscription)
                                        <span class="inline-flex items-center px-2 py-0.5 text-xs rounded bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300">
                                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                            </svg>
                                            {{ $subscription->product->name }} (opted out)
                                        </span>
                                    @endforeach
                                </div>
                            @endif
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

## Re-Sync Manager (Admin Tool)

### Component Overview

**Component:** ReSyncManager (Livewire)
**Route:** `/admin/cdp/re-sync`
**File:** `app/Livewire/Admin/Cdp/ReSyncManager.php`
**View:** `resources/views/livewire/admin/cdp/re-sync-manager.blade.php`

**Purpose:** Admin tool for manual data synchronization, backfill operations, and data quality corrections. Critical for data recovery, field mapping updates, and troubleshooting sync issues.

---

### Layout: Four-Panel Dashboard

```blade
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header -->
    <flux:header class="mb-8">
        <flux:heading>Re-Sync & Backfill Manager</flux:heading>
        <flux:subheading>
            Manual data synchronization tools for Campaign Monitor and historical data backfill operations.
            Use with caution - these operations can generate significant API usage.
        </flux:subheading>
    </flux:header>

    <!-- Warning Banner -->
    <div class="mb-6 p-4 bg-yellow-50 dark:bg-yellow-900/20 border-l-4 border-yellow-400 rounded-r-lg">
        <div class="flex items-center">
            <svg class="w-5 h-5 text-yellow-400 mr-3" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <div>
                <p class="text-sm font-medium text-yellow-800 dark:text-yellow-200">
                    Admin-Only Operations
                </p>
                <p class="text-xs text-yellow-700 dark:text-yellow-300 mt-1">
                    These operations can consume significant API quota and processing time. Always review estimates before proceeding.
                </p>
            </div>
        </div>
    </div>

    <!-- Four Operation Panels -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- Panel 1: Re-Sync All Users -->
        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-neutral-200 dark:border-neutral-700 p-6">
            <div class="flex items-center mb-4">
                <div class="p-3 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                    <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Re-Sync All Users</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Full sync of all users to Campaign Monitor</p>
                </div>
            </div>

            <div class="space-y-3 mb-4">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600 dark:text-gray-400">Total Users:</span>
                    <span class="font-medium text-gray-900 dark:text-white" wire:poll.60s>
                        {{ number_format($totalUsersCount) }}
                    </span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600 dark:text-gray-400">Estimated API Calls:</span>
                    <span class="font-medium text-gray-900 dark:text-white">
                        ~{{ ceil($totalUsersCount / 1000) }} calls
                    </span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600 dark:text-gray-400">Estimated Time:</span>
                    <span class="font-medium text-gray-900 dark:text-white">
                        ~{{ ceil($totalUsersCount / 1000 * 2) }} minutes
                    </span>
                </div>
            </div>

            @if($syncAllProgress)
                <!-- Progress Bar -->
                <div class="mb-4" wire:poll.2s>
                    <div class="flex justify-between text-xs text-gray-600 dark:text-gray-400 mb-1">
                        <span>Syncing...</span>
                        <span>{{ $syncAllProgress }}%</span>
                    </div>
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                        <div class="bg-blue-600 h-2 rounded-full transition-all duration-300"
                             style="width: {{ $syncAllProgress }}%"></div>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                        Processed: {{ number_format($syncedUsersCount) }} / {{ number_format($totalUsersCount) }}
                    </p>
                </div>
            @endif

            <flux:button
                variant="primary"
                class="w-full"
                wire:click="confirmSyncAll"
                :disabled="$syncAllProgress > 0">
                {{ $syncAllProgress ? 'Syncing...' : 'Start Full Sync' }}
            </flux:button>
        </div>

        <!-- Panel 2: Re-Sync Segment -->
        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-neutral-200 dark:border-neutral-700 p-6">
            <div class="flex items-center mb-4">
                <div class="p-3 bg-green-100 dark:bg-green-900/30 rounded-lg">
                    <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                        <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Re-Sync Segment</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Sync users matching a specific segment</p>
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Select Segment
                </label>
                <select
                    wire:model.live="selectedSegmentId"
                    class="w-full px-3 py-2 bg-white dark:bg-zinc-900 border border-neutral-200 dark:border-neutral-700 rounded-md text-sm text-gray-900 dark:text-white">
                    <option value="">-- Choose a segment --</option>
                    @foreach($segments as $segment)
                        <option value="{{ $segment->id }}">
                            {{ $segment->name }} ({{ number_format($segment->users_count) }} users)
                        </option>
                    @endforeach
                </select>
            </div>

            @if($selectedSegmentId)
                <div class="space-y-3 mb-4 p-3 bg-gray-50 dark:bg-zinc-900/50 rounded-lg">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600 dark:text-gray-400">Matching Users:</span>
                        <span class="font-medium text-gray-900 dark:text-white">
                            {{ number_format($selectedSegmentUsersCount) }}
                        </span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600 dark:text-gray-400">Estimated Time:</span>
                        <span class="font-medium text-gray-900 dark:text-white">
                            ~{{ ceil($selectedSegmentUsersCount / 1000 * 2) }} minutes
                        </span>
                    </div>
                </div>

                @if($syncSegmentProgress)
                    <div class="mb-4" wire:poll.2s>
                        <div class="flex justify-between text-xs text-gray-600 dark:text-gray-400 mb-1">
                            <span>Syncing segment...</span>
                            <span>{{ $syncSegmentProgress }}%</span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                            <div class="bg-green-600 h-2 rounded-full transition-all duration-300"
                                 style="width: {{ $syncSegmentProgress }}%"></div>
                        </div>
                    </div>
                @endif

                <flux:button
                    variant="primary"
                    class="w-full bg-green-600 hover:bg-green-700"
                    wire:click="confirmSyncSegment"
                    :disabled="$syncSegmentProgress > 0">
                    {{ $syncSegmentProgress ? 'Syncing...' : 'Sync Segment' }}
                </flux:button>
            @endif
        </div>

        <!-- Panel 3: Re-Sync Single User -->
        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-neutral-200 dark:border-neutral-700 p-6">
            <div class="flex items-center mb-4">
                <div class="p-3 bg-purple-100 dark:bg-purple-900/30 rounded-lg">
                    <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Re-Sync Single User</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Quick sync for a specific user</p>
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    User Email
                </label>
                <input
                    type="email"
                    wire:model.live.debounce.500ms="userEmail"
                    placeholder="user@example.com"
                    class="w-full px-3 py-2 bg-white dark:bg-zinc-900 border border-neutral-200 dark:border-neutral-700 rounded-md text-sm text-gray-900 dark:text-white"
                />
            </div>

            @if($foundUser)
                <div class="mb-4 p-3 bg-purple-50 dark:bg-purple-900/20 rounded-lg border border-purple-200 dark:border-purple-700">
                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $foundUser->fullname }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $foundUser->email }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        Tier: <span class="font-medium">{{ ucfirst($foundUser->tier_name ?? 'N/A') }}</span>
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Last synced: {{ $foundUser->cm_synced_at ? $foundUser->cm_synced_at->diffForHumans() : 'Never' }}
                    </p>
                </div>

                @if($singleUserSyncing)
                    <div class="mb-4">
                        <div class="flex items-center justify-center py-2">
                            <svg class="animate-spin h-5 w-5 text-purple-600" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Syncing user...</span>
                        </div>
                    </div>
                @endif

                <flux:button
                    variant="primary"
                    class="w-full bg-purple-600 hover:bg-purple-700"
                    wire:click="syncSingleUser"
                    :disabled="$singleUserSyncing">
                    {{ $singleUserSyncing ? 'Syncing...' : 'Sync This User' }}
                </flux:button>
            @elseif($userEmail && strlen($userEmail) > 3)
                <div class="mb-4 p-3 bg-gray-50 dark:bg-zinc-900/50 rounded-lg">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $searchingUser ? 'Searching...' : 'No user found with this email' }}
                    </p>
                </div>
            @endif
        </div>

        <!-- Panel 4: Re-Calculate Activity Scores -->
        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-neutral-200 dark:border-neutral-700 p-6">
            <div class="flex items-center mb-4">
                <div class="p-3 bg-orange-100 dark:bg-orange-900/30 rounded-lg">
                    <svg class="w-6 h-6 text-orange-600 dark:text-orange-400" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"/>
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Re-Calculate Scores</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Recalculate activity scores for all users</p>
                </div>
            </div>

            <div class="space-y-3 mb-4">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600 dark:text-gray-400">Users to Process:</span>
                    <span class="font-medium text-gray-900 dark:text-white">
                        {{ number_format($totalUsersCount) }}
                    </span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600 dark:text-gray-400">Lookback Period:</span>
                    <span class="font-medium text-gray-900 dark:text-white">90 days</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600 dark:text-gray-400">Estimated Time:</span>
                    <span class="font-medium text-gray-900 dark:text-white">
                        ~{{ ceil($totalUsersCount / 1000 * 1) }} minutes
                    </span>
                </div>
            </div>

            @if($recalcProgress)
                <div class="mb-4" wire:poll.2s>
                    <div class="flex justify-between text-xs text-gray-600 dark:text-gray-400 mb-1">
                        <span>Recalculating...</span>
                        <span>{{ $recalcProgress }}%</span>
                    </div>
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                        <div class="bg-orange-600 h-2 rounded-full transition-all duration-300"
                             style="width: {{ $recalcProgress }}%"></div>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                        Processed: {{ number_format($recalcProcessed) }} / {{ number_format($totalUsersCount) }}
                    </p>
                </div>
            @endif

            <flux:button
                variant="primary"
                class="w-full bg-orange-600 hover:bg-orange-700"
                wire:click="confirmRecalcScores"
                :disabled="$recalcProgress > 0">
                {{ $recalcProgress ? 'Recalculating...' : 'Start Recalculation' }}
            </flux:button>
        </div>

    </div>

    <!-- Recent Operations Log -->
    <div class="mt-8 bg-white dark:bg-zinc-800 rounded-lg border border-neutral-200 dark:border-neutral-700">
        <div class="px-6 py-4 border-b border-neutral-200 dark:border-neutral-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Recent Operations</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Last 10 re-sync operations</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 dark:bg-zinc-900">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Operation
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Target
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Users Processed
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Started
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Duration
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Status
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700" wire:poll.10s>
                    @forelse($recentOperations as $operation)
                        <tr class="hover:bg-gray-50 dark:hover:bg-zinc-900">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                {{ $operation->operation_type }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $operation->target }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ number_format($operation->users_processed) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $operation->started_at->format('Y-m-d H:i') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $operation->duration }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                    {{ $operation->status === 'completed' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' : '' }}
                                    {{ $operation->status === 'running' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300' : '' }}
                                    {{ $operation->status === 'failed' ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300' : '' }}">
                                    {{ ucfirst($operation->status) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                No re-sync operations yet
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Confirmation Modal (Example for Sync All) -->
    @if($showConfirmModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" wire:click="cancelOperation">
            <div class="flex items-center justify-center min-h-screen px-4">
                <!-- Backdrop -->
                <div class="fixed inset-0 bg-black opacity-50"></div>

                <!-- Modal -->
                <div class="relative bg-white dark:bg-zinc-800 rounded-lg max-w-lg w-full p-6" wire:click.stop>
                    <div class="flex items-center mb-4">
                        <div class="p-3 bg-yellow-100 dark:bg-yellow-900/30 rounded-full">
                            <svg class="w-6 h-6 text-yellow-600 dark:text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <h3 class="ml-4 text-lg font-semibold text-gray-900 dark:text-white">
                            Confirm {{ $confirmOperation }}
                        </h3>
                    </div>

                    <div class="mb-6">
                        <p class="text-sm text-gray-700 dark:text-gray-300 mb-4">
                            {{ $confirmMessage }}
                        </p>

                        <div class="bg-gray-50 dark:bg-zinc-900/50 rounded-lg p-4 space-y-2">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600 dark:text-gray-400">Users to process:</span>
                                <span class="font-medium text-gray-900 dark:text-white">
                                    {{ number_format($confirmUsersCount) }}
                                </span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600 dark:text-gray-400">Estimated API calls:</span>
                                <span class="font-medium text-gray-900 dark:text-white">
                                    ~{{ $confirmApiCalls }}
                                </span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600 dark:text-gray-400">Estimated duration:</span>
                                <span class="font-medium text-gray-900 dark:text-white">
                                    ~{{ $confirmDuration }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="flex space-x-3">
                        <flux:button
                            variant="ghost"
                            class="flex-1"
                            wire:click="cancelOperation">
                            Cancel
                        </flux:button>
                        <flux:button
                            variant="danger"
                            class="flex-1"
                            wire:click="executeOperation">
                            Yes, Proceed
                        </flux:button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
```

### Component Properties

```php
// app/Livewire/Admin/Cdp/ReSyncManager.php

class ReSyncManager extends Component
{
    // Re-sync all users
    public $syncAllProgress = 0;
    public $syncedUsersCount = 0;
    public $totalUsersCount = 0;

    // Re-sync segment
    public $selectedSegmentId = null;
    public $selectedSegmentUsersCount = 0;
    public $syncSegmentProgress = 0;
    public $segments = [];

    // Re-sync single user
    public $userEmail = '';
    public $foundUser = null;
    public $searchingUser = false;
    public $singleUserSyncing = false;

    // Re-calculate scores
    public $recalcProgress = 0;
    public $recalcProcessed = 0;

    // Confirmation modal
    public $showConfirmModal = false;
    public $confirmOperation = '';
    public $confirmMessage = '';
    public $confirmUsersCount = 0;
    public $confirmApiCalls = 0;
    public $confirmDuration = '';

    // Recent operations log
    public $recentOperations = [];

    public function mount()
    {
        $this->totalUsersCount = User::count();
        $this->segments = Segment::withCount('users')->get();
        $this->loadRecentOperations();
    }

    public function confirmSyncAll()
    {
        $this->confirmOperation = 'Sync All Users';
        $this->confirmMessage = 'This will sync all ' . number_format($this->totalUsersCount) . ' users to Campaign Monitor. This operation cannot be cancelled once started.';
        $this->confirmUsersCount = $this->totalUsersCount;
        $this->confirmApiCalls = ceil($this->totalUsersCount / 1000);
        $this->confirmDuration = ceil($this->totalUsersCount / 1000 * 2) . ' minutes';
        $this->showConfirmModal = true;
    }

    public function executeOperation()
    {
        match($this->confirmOperation) {
            'Sync All Users' => $this->executeSyncAll(),
            'Sync Segment' => $this->executeSyncSegment(),
            'Recalculate Scores' => $this->executeRecalcScores(),
            default => null,
        };

        $this->showConfirmModal = false;
    }

    protected function executeSyncAll()
    {
        // Dispatch job to queue
        ReSyncAllUsersJob::dispatch();

        // Track progress (updated by job)
        session()->flash('message', 'Full sync started. Progress will update automatically.');
    }

    // ... other methods
}
```

### Key Features

1. **Four Sync Operations**: All users, specific segment, single user, recalculate scores
2. **Real-time Progress**: `wire:poll.2s` for live progress updates
3. **Confirmation Modals**: Safety checks before destructive operations
4. **Estimate Previews**: API calls, duration, user counts
5. **Operation Logging**: Table showing recent sync history
6. **Dark Mode Support**: Full compatibility
7. **Responsive Layout**: 2-column grid on desktop, stacked on mobile

---

## Summary

This UI specification document provides:

✅ **87 UI Tasks** mapped to specific Livewire components (updated with backfill features)
✅ **Complete visual designs** with Flux Pro + Tailwind v4
✅ **Dark mode support** for all interfaces
✅ **Real-time updates** with wire:poll
✅ **Consistent component patterns** across all pages
✅ **Accessibility** with ARIA labels and semantic HTML
✅ **Responsive layouts** for desktop and mobile
✅ **Admin tools** for data synchronization and backfill operations

**Components Documented:**
- 32 Admin UI components (tier, product, segment, campaign, sync management)
- 2 User-facing components (product subscriptions, activity dashboard)
- 1 Re-sync manager tool (manual sync, backfill, data corrections)

**Next Steps:**
1. Review this specification
2. Begin Phase 1 implementation (Tasks 1.1-1.14)
3. Use these exact component patterns for consistency

---

## Campaign State Monitoring & Error Recovery UI

### Component: CampaignDetail (Enhanced with Error States)

**File:** `app/Livewire/Admin/Cdp/CampaignDetail.php`
**View:** `resources/views/livewire/admin/cdp/campaign-detail.blade.php`
**Route:** `/admin/cdp/campaigns/{id}`
**Purpose:** Enhanced campaign detail view with comprehensive error state handling and manual recovery controls

#### Component Properties

```php
class CampaignDetail extends Component
{
    use WithPagination;

    public Campaign $campaign;
    public $showCancelCleanupModal = false;
    public $showRescheduleCleanupModal = false;
    public $showRetryModal = false;
    public $showMarkAsSentModal = false;

    // Real-time updates
    protected $listeners = ['refreshCampaign' => '$refresh'];

    public function mount(int $id)
    {
        $this->campaign = Campaign::with([
            'segment',
            'metrics',
            'template'
        ])->findOrFail($id);
    }

    // === STATE MANAGEMENT METHODS ===

    public function cancelCleanup()
    {
        if ($this->campaign->cleanup_job_id) {
            try {
                Queue::deleteJob($this->campaign->cleanup_job_id);

                $this->campaign->update([
                    'cleanup_job_id' => null,
                    'campaign_tag_status' => 'sent'
                ]);

                session()->flash('success', 'Cleanup job cancelled successfully.');
                $this->showCancelCleanupModal = false;
            } catch (Throwable $e) {
                session()->flash('error', 'Failed to cancel cleanup job: ' . $e->getMessage());
            }
        }
    }

    public function rescheduleCleanup()
    {
        // Cancel old cleanup if exists
        if ($this->campaign->cleanup_job_id) {
            try {
                Queue::deleteJob($this->campaign->cleanup_job_id);
            } catch (Throwable $e) {
                Log::warning("Could not cancel old cleanup job: " . $e->getMessage());
            }
        }

        // Dispatch new cleanup for 2 hours from NOW
        $cleanupJob = CleanupCampaignTagJob::dispatch($this->campaign->id)
            ->delay(now()->addHours(2))
            ->onQueue('cm-cleanup');

        $this->campaign->update([
            'cleanup_job_id' => $cleanupJob->id,
            'campaign_tag_status' => 'cleanup_scheduled'
        ]);

        session()->flash('success', 'Cleanup rescheduled for 2 hours from now.');
        $this->showRescheduleCleanupModal = false;
    }

    public function retrySend()
    {
        // Dispatch new send job
        TagAndSendCampaignJob::dispatch(
            $this->campaign->id,
            $this->campaign->segment->getUsers()->pluck('id')->toArray(),
            $this->campaign->campaign_tag ?? DynamicTagService::generateTag($this->campaign)
        )->onQueue('cm-campaigns');

        session()->flash('success', 'Campaign send job dispatched. Refresh page to see status.');
        $this->showRetryModal = false;
    }

    public function runCleanupNow()
    {
        // Dispatch cleanup job immediately (no delay)
        CleanupCampaignTagJob::dispatch($this->campaign->id)
            ->onQueue('cm-cleanup');

        session()->flash('success', 'Cleanup job dispatched immediately.');
    }

    public function markAsSent()
    {
        $this->campaign->update([
            'status' => 'sent',
            'campaign_tag_status' => 'sent',
            'sent_at' => now()
        ]);

        session()->flash('success', 'Campaign marked as sent.');
        $this->showMarkAsSentModal = false;
    }

    // === COMPUTED PROPERTIES ===

    public function getCleanupWarningProperty()
    {
        if ($this->campaign->campaign_tag_status !== 'cleanup_scheduled') {
            return false;
        }

        if (!$this->campaign->campaign_tag_created_at) {
            return false;
        }

        $hoursSinceSend = now()->diffInHours($this->campaign->campaign_tag_created_at);
        return $hoursSinceSend < 2;
    }

    public function getTimeUntilCleanupProperty()
    {
        if (!$this->campaign->campaign_tag_created_at) {
            return null;
        }

        $cleanupTime = Carbon::parse($this->campaign->campaign_tag_created_at)->addHours(2);
        return $cleanupTime->diffForHumans();
    }

    public function getTimeSinceSendProperty()
    {
        if (!$this->campaign->sent_at) {
            return null;
        }

        return Carbon::parse($this->campaign->sent_at)->diffForHumans();
    }

    public function render()
    {
        return view('livewire.admin.cdp.campaign-detail', [
            'cleanupWarning' => $this->getCleanupWarningProperty(),
            'timeUntilCleanup' => $this->getTimeUntilCleanupProperty(),
            'timeSinceSend' => $this->getTimeSinceSendProperty(),
        ]);
    }
}
```

#### Blade Template (Enhanced)

```blade
<div wire:poll.5s class="space-y-6">
    {{-- Flash Messages --}}
    @if (session()->has('success'))
        <flux:alert type="success" dismissible>
            {{ session('success') }}
        </flux:alert>
    @endif

    @if (session()->has('error'))
        <flux:alert type="error" dismissible>
            {{ session('error') }}
        </flux:alert>
    @endif

    {{-- Campaign Header with State Badge --}}
    <div class="flex items-start justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                {{ $campaign->name }}
            </h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Campaign #{{ $campaign->id }}
            </p>
        </div>

        {{-- State Badge (Dynamic) --}}
        <div>
            @switch($campaign->campaign_tag_status)
                @case('pending')
                    <span class="inline-flex items-center px-3 py-1 text-sm font-medium rounded-md
                                 bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300">
                        <svg class="w-4 h-4 mr-1.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"/>
                        </svg>
                        Pending
                    </span>
                    @break

                @case('tagging')
                    <span class="inline-flex items-center px-3 py-1 text-sm font-medium rounded-md
                                 bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                        <svg class="w-4 h-4 mr-1.5 animate-spin" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10 3a1 1 0 011 1v5a1 1 0 11-2 0V4a1 1 0 011-1z"/>
                        </svg>
                        Tagging Users
                    </span>
                    @break

                @case('tagged')
                    <span class="inline-flex items-center px-3 py-1 text-sm font-medium rounded-md
                                 bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                        <svg class="w-4 h-4 mr-1.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M17.707 9.293a1 1 0 010 1.414l-7 7a1 1 0 01-1.414 0l-7-7A.997.997 0 012 10V5a3 3 0 013-3h5c.256 0 .512.098.707.293l7 7zM5 6a1 1 0 100-2 1 1 0 000 2z"/>
                        </svg>
                        Tagged
                    </span>
                    @break

                @case('sending')
                    <span class="inline-flex items-center px-3 py-1 text-sm font-medium rounded-md
                                 bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300">
                        <svg class="w-4 h-4 mr-1.5 animate-pulse" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z"/>
                        </svg>
                        Sending
                    </span>
                    @break

                @case('sent')
                    <span class="inline-flex items-center px-3 py-1 text-sm font-medium rounded-md
                                 bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">
                        <svg class="w-4 h-4 mr-1.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
                        </svg>
                        Sent
                    </span>
                    @break

                @case('cleanup_scheduled')
                    @if($cleanupWarning)
                        <span class="inline-flex items-center px-3 py-1 text-sm font-medium rounded-md
                                     bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300">
                            <svg class="w-4 h-4 mr-1.5 animate-pulse" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"/>
                            </svg>
                            Cleanup Scheduled ⚠️
                        </span>
                    @else
                        <span class="inline-flex items-center px-3 py-1 text-sm font-medium rounded-md
                                     bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300">
                            <svg class="w-4 h-4 mr-1.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"/>
                            </svg>
                            Cleanup Scheduled
                        </span>
                    @endif
                    @break

                @case('cleaned')
                    <span class="inline-flex items-center px-3 py-1 text-sm font-medium rounded-md
                                 bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                        <svg class="w-4 h-4 mr-1.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"/>
                        </svg>
                        Cleaned
                    </span>
                    @break

                @case('failed')
                    <span class="inline-flex items-center px-3 py-1 text-sm font-medium rounded-md
                                 bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300">
                        <svg class="w-4 h-4 mr-1.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"/>
                        </svg>
                        Failed
                    </span>
                    @break
            @endswitch
        </div>
    </div>

    {{-- ERROR STATE: Campaign Failed --}}
    @if($campaign->campaign_tag_status === 'failed')
        <div class="rounded-lg bg-red-50 border border-red-200 p-6 dark:bg-red-900/20 dark:border-red-800">
            <div class="flex items-start">
                <svg class="w-6 h-6 text-red-600 dark:text-red-400 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"/>
                </svg>

                <div class="ml-4 flex-1">
                    <h3 class="text-sm font-semibold text-red-800 dark:text-red-300">
                        Campaign Send Failed
                    </h3>

                    <div class="mt-2 text-sm text-red-700 dark:text-red-400">
                        <p>The campaign failed to send after multiple attempts.</p>

                        {{-- Timeline of state transitions --}}
                        <div class="mt-3 space-y-1 font-mono text-xs">
                            <div>{{ $campaign->created_at->format('H:i') }} — Created</div>
                            <div>{{ $campaign->updated_at->format('H:i') }} — Failed at state: <strong>{{ $campaign->campaign_tag_status }}</strong></div>
                        </div>

                        @if($campaign->last_error)
                            <div class="mt-3 p-3 bg-red-100 dark:bg-red-900/40 rounded text-xs">
                                <strong>Error:</strong> {{ $campaign->last_error }}
                            </div>
                        @endif
                    </div>

                    <div class="mt-4 flex gap-3">
                        <flux:button wire:click="$set('showRetryModal', true)" size="sm" variant="filled">
                            Retry Send
                        </flux:button>

                        <flux:button wire:click="$set('showMarkAsSentModal', true)" size="sm" variant="ghost">
                            Mark as Sent
                        </flux:button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- WARNING: Cleanup Scheduled Too Early --}}
    @if($campaign->campaign_tag_status === 'cleanup_scheduled' && $cleanupWarning)
        <div class="rounded-lg bg-orange-50 border border-orange-200 p-6 dark:bg-orange-900/20 dark:border-orange-800">
            <div class="flex items-start">
                <svg class="w-6 h-6 text-orange-600 dark:text-orange-400 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"/>
                </svg>

                <div class="ml-4 flex-1">
                    <h3 class="text-sm font-semibold text-orange-800 dark:text-orange-300">
                        Warning: Cleanup Scheduled Less Than 2 Hours After Send
                    </h3>

                    <div class="mt-2 text-sm text-orange-700 dark:text-orange-400">
                        <p>
                            Sent: {{ $timeSinceSend }}<br>
                            Cleanup: {{ $timeUntilCleanup }}
                        </p>
                        <p class="mt-2">
                            Campaign metrics may not be fully collected yet. Consider rescheduling cleanup.
                        </p>
                    </div>

                    <div class="mt-4 flex gap-3">
                        <flux:button wire:click="$set('showRescheduleCleanupModal', true)" size="sm" variant="filled">
                            Reschedule Cleanup
                        </flux:button>

                        <flux:button wire:click="$set('showCancelCleanupModal', true)" size="sm" variant="ghost">
                            Cancel Cleanup
                        </flux:button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Manual Recovery Controls (for cleanup_scheduled state) --}}
    @if($campaign->campaign_tag_status === 'cleanup_scheduled' && !$cleanupWarning)
        <div class="rounded-lg bg-purple-50 border border-purple-200 p-4 dark:bg-purple-900/20 dark:border-purple-800">
            <div class="flex items-center justify-between">
                <div class="flex items-center text-sm text-purple-800 dark:text-purple-300">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"/>
                    </svg>
                    <span>Cleanup scheduled: <strong>{{ $timeUntilCleanup }}</strong></span>
                </div>

                <div class="flex gap-2">
                    <flux:button wire:click="$set('showRescheduleCleanupModal', true)" size="sm" variant="ghost">
                        Reschedule
                    </flux:button>

                    <flux:button wire:click="$set('showCancelCleanupModal', true)" size="sm" variant="ghost">
                        Cancel
                    </flux:button>

                    <flux:button wire:click="runCleanupNow" size="sm" variant="ghost">
                        Run Now
                    </flux:button>
                </div>
            </div>
        </div>
    @endif

    {{-- State Timeline Visualization --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
            Campaign State Timeline
        </h2>

        <div class="relative">
            {{-- Timeline --}}
            <div class="absolute left-4 top-0 bottom-0 w-0.5 bg-gray-200 dark:bg-gray-700"></div>

            <div class="space-y-4">
                {{-- Each state in timeline --}}
                <div class="relative flex items-start pl-10">
                    <div class="absolute left-0 w-8 h-8 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                        <svg class="w-4 h-4 text-gray-600 dark:text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">Created</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $campaign->created_at->format('M d, Y H:i') }}</p>
                    </div>
                </div>

                @if($campaign->sent_at)
                    <div class="relative flex items-start pl-10">
                        <div class="absolute left-0 w-8 h-8 rounded-full bg-green-500 flex items-center justify-center">
                            <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">Sent</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $campaign->sent_at->format('M d, Y H:i') }}</p>
                        </div>
                    </div>
                @endif

                @if($campaign->cleanup_completed_at)
                    <div class="relative flex items-start pl-10">
                        <div class="absolute left-0 w-8 h-8 rounded-full bg-gray-400 flex items-center justify-center">
                            <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">Cleaned</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $campaign->cleanup_completed_at->format('M d, Y H:i') }}</p>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Remaining campaign details (metrics, recipients, etc.) --}}
    {{-- ... existing campaign detail sections ... --}}

    {{-- MODALS --}}

    {{-- Retry Send Modal --}}
    @if($showRetryModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" wire:click.self="$set('showRetryModal', false)">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                    Retry Campaign Send?
                </h3>

                <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                    This will re-dispatch the send job. The job will resume from the last successful step.
                </p>

                <div class="text-sm text-gray-700 dark:text-gray-300 mb-6">
                    <strong>Recipients:</strong> {{ number_format($campaign->recipient_count ?? 0) }} users
                </div>

                <div class="flex justify-end gap-3">
                    <flux:button wire:click="$set('showRetryModal', false)" variant="ghost">
                        Cancel
                    </flux:button>

                    <flux:button wire:click="retrySend" variant="filled">
                        Confirm & Retry
                    </flux:button>
                </div>
            </div>
        </div>
    @endif

    {{-- Reschedule Cleanup Modal --}}
    @if($showRescheduleCleanupModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" wire:click.self="$set('showRescheduleCleanupModal', false)">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                    Reschedule Cleanup?
                </h3>

                <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                    This will cancel the current cleanup job and schedule a new one for 2 hours from now.
                </p>

                <div class="flex justify-end gap-3">
                    <flux:button wire:click="$set('showRescheduleCleanupModal', false)" variant="ghost">
                        Cancel
                    </flux:button>

                    <flux:button wire:click="rescheduleCleanup" variant="filled">
                        Confirm & Reschedule
                    </flux:button>
                </div>
            </div>
        </div>
    @endif

    {{-- Cancel Cleanup Modal --}}
    @if($showCancelCleanupModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" wire:click.self="$set('showCancelCleanupModal', false)">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                    Cancel Cleanup?
                </h3>

                <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                    This will permanently cancel the cleanup job. Tags will remain and the segment will not be deleted.
                </p>

                <p class="text-sm text-orange-600 dark:text-orange-400 mb-6">
                    ⚠️ You will need to manually clean up this campaign later.
                </p>

                <div class="flex justify-end gap-3">
                    <flux:button wire:click="$set('showCancelCleanupModal', false)" variant="ghost">
                        Cancel
                    </flux:button>

                    <flux:button wire:click="cancelCleanup" variant="filled">
                        Confirm & Cancel Cleanup
                    </flux:button>
                </div>
            </div>
        </div>
    @endif
</div>
```

#### State Badge Component (Reusable)

For consistency across the application, extract the state badge into a reusable Blade component:

**File:** `resources/views/components/campaign-state-badge.blade.php`

```blade
@props(['state' => 'pending', 'warning' => false])

@php
$configs = [
    'pending' => [
        'bg' => 'bg-gray-100 dark:bg-gray-800',
        'text' => 'text-gray-800 dark:text-gray-300',
        'icon' => 'clock',
        'label' => 'Pending',
        'animate' => false
    ],
    'tagging' => [
        'bg' => 'bg-blue-100 dark:bg-blue-900/30',
        'text' => 'text-blue-800 dark:text-blue-300',
        'icon' => 'spinner',
        'label' => 'Tagging',
        'animate' => 'spin'
    ],
    'tagged' => [
        'bg' => 'bg-blue-100 dark:bg-blue-900/30',
        'text' => 'text-blue-800 dark:text-blue-300',
        'icon' => 'tag',
        'label' => 'Tagged',
        'animate' => false
    ],
    'sending' => [
        'bg' => 'bg-yellow-100 dark:bg-yellow-900/30',
        'text' => 'text-yellow-800 dark:text-yellow-300',
        'icon' => 'paper-plane',
        'label' => 'Sending',
        'animate' => 'pulse'
    ],
    'sent' => [
        'bg' => 'bg-green-100 dark:bg-green-900/30',
        'text' => 'text-green-800 dark:text-green-300',
        'icon' => 'check-circle',
        'label' => 'Sent',
        'animate' => false
    ],
    'cleanup_scheduled' => [
        'bg' => $warning ? 'bg-orange-100 dark:bg-orange-900/30' : 'bg-purple-100 dark:bg-purple-900/30',
        'text' => $warning ? 'text-orange-800 dark:text-orange-300' : 'text-purple-800 dark:text-purple-300',
        'icon' => $warning ? 'warning' : 'clock',
        'label' => 'Cleanup Scheduled' . ($warning ? ' ⚠️' : ''),
        'animate' => $warning ? 'pulse' : false
    ],
    'cleaned' => [
        'bg' => 'bg-gray-100 dark:bg-gray-800',
        'text' => 'text-gray-600 dark:text-gray-400',
        'icon' => 'check',
        'label' => 'Cleaned',
        'animate' => false
    ],
    'failed' => [
        'bg' => 'bg-red-100 dark:bg-red-900/30',
        'text' => 'text-red-800 dark:text-red-300',
        'icon' => 'x-circle',
        'label' => 'Failed',
        'animate' => false
    ],
];

$config = $configs[$state] ?? $configs['pending'];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center px-2 py-1 text-xs font-medium rounded-md {$config['bg']} {$config['text']}"]) }}>
    <svg class="w-3 h-3 mr-1 {{ $config['animate'] ? 'animate-' . $config['animate'] : '' }}"
         fill="currentColor" viewBox="0 0 20 20">
        {{-- SVG path based on icon type --}}
    </svg>
    {{ $config['label'] }}
</span>
```

**Usage:**
```blade
<x-campaign-state-badge :state="$campaign->campaign_tag_status" :warning="$cleanupWarning" />
```

---

**Related Documents:**
- `TODO_LIST.md` - 92 task checklist (updated with campaign metrics + backfill + error handling)
- `CDP_WORKFLOWS.md` - Backend + UI workflows (includes metrics sync + job failure handling)
- `CDP_WORKFLOW_DIAGRAMS.md` - Visual system architecture (includes failure recovery workflows)
- `CDP_PROJECT_PLAN.md` - Overall project plan

---

**Document Version:** 3.0 (Added Campaign Error State Monitoring & Recovery UI)
**Last Updated:** 2025-11-12
**Status:** Ready for Implementation

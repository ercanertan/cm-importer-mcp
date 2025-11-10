# Campaign Monitor 50 Custom Field Limit Solution

## The Challenge

Campaign Monitor imposes a **hard limit of 50 custom fields per list**. Our system requires:

1. **Unlimited product subscriptions** - Users can opt in/out of many products
2. **Comprehensive user tracking** - CDP (Customer Data Platform) capabilities
3. **Rich engagement metrics** - Email opens, clicks, event attendance, behavior tracking
4. **Organization data** - Org tier, name, metadata
5. **User demographics** - Name, location, preferences, etc.
6. **Audit trails** - Complete history of user actions

**Problem**: We would easily exceed 50 fields if we tried to sync everything to Campaign Monitor.

---

## The Solution: Hybrid Architecture

### Laravel = Source of Truth (Unlimited Storage)
### Campaign Monitor = Email Delivery Engine (Limited to 50 Fields)

---

## Field Allocation Strategy

### Tier 1: Essential Custom Fields (~15-20 fields in CM)

These are the **only** fields synced to Campaign Monitor as custom fields:

| Field Name | Type | Purpose | Example |
|------------|------|---------|---------|
| `user_id` | Text | Link back to Laravel | `12345` |
| `email` | Email | Standard field | `john@example.com` |
| `fullname` | Text | Standard field | `John Doe` |
| `organization_name` | Text | For segmentation | `Acme Corp` |
| `organization_tier` | Text | For org-level campaigns | `paid_premium` |
| `user_tier` | Text | For user-level campaigns | `paid_pro` |
| `engagement_score` | Number | Calculated score | `85` |
| `total_opens` | Number | Email opens count | `42` |
| `total_clicks` | Number | Email clicks count | `18` |
| `last_email_opened_at` | Date | Recent activity | `2025-11-09` |
| `last_email_clicked_at` | Date | Recent engagement | `2025-11-08` |
| `permission_to_track` | Text | Consent status | `granted` |
| `created_at` | Date | User registration | `2025-01-15` |

**Total: ~13 core fields** (leaving 37 fields for future needs)

---

### Tier 2: Tags (Unlimited in CM)

Use **tags** for everything that could scale:

#### Product Subscriptions
```
product:newsletter
product:webinar-series
product:premium-content
product:api-access
product:training-materials
```

#### User Segments
```
segment:high-engagement    # Engagement score > 80
segment:moderate-engagement # Engagement score 40-80
segment:low-engagement     # Engagement score < 40
segment:at-risk            # Not opened in 30 days
segment:champion           # Opens every email
```

#### Organization Segments
```
org:enterprise     # Large organizations
org:startup        # Small organizations
org:education      # Educational institutions
org:nonprofit      # Non-profit organizations
```

#### Behavioral Tags
```
behavior:event-attendee       # Attended any event
behavior:frequent-attendee    # Attended 5+ events
behavior:webinar-participant  # Joined webinars
behavior:never-opened         # Never opened an email
behavior:recently-active      # Active in last 7 days
```

#### Tier Tags
```
tier:free
tier:paid-pro
tier:paid-premium
tier:enterprise
```

**Benefits of Tags:**
- **Unlimited** - Add as many as needed
- **Easy segmentation** - CM's UI supports tag-based segments
- **Bulk operations** - Can tag/untag thousands of users at once
- **Dynamic** - Easy to add/remove without schema changes

---

### Tier 3: Laravel Database (Unlimited Storage)

Store **everything** in Laravel that doesn't need to be in CM:

#### Product Subscriptions Table
```php
Schema::create('product_subscriptions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained();
    $table->foreignId('product_id')->constrained();
    $table->boolean('subscribed')->default(true);
    $table->timestamp('subscribed_at')->nullable();
    $table->timestamp('unsubscribed_at')->nullable();
    $table->string('source'); // 'user', 'admin', 'import', 'api'
    $table->timestamps();
});
```

#### Email Engagements Table (Already Created)
```php
Schema::create('email_engagements', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained();
    $table->string('campaign_id');
    $table->string('email_id')->nullable();
    $table->enum('event_type', ['sent', 'opened', 'clicked', 'bounced', 'unsubscribed']);
    $table->timestamp('event_at');
    $table->json('metadata')->nullable(); // Click URL, bounce reason, etc.
    $table->timestamps();
});
```

#### Events & Event Attendance (Already Created)
```php
Schema::create('events', function (Blueprint $table) {
    $table->id();
    $table->foreignId('organization_id')->constrained();
    $table->string('name');
    $table->text('description')->nullable();
    $table->timestamp('starts_at');
    $table->timestamp('ends_at')->nullable();
    $table->timestamps();
});

Schema::create('event_attendances', function (Blueprint $table) {
    $table->id();
    $table->foreignId('event_id')->constrained();
    $table->foreignId('user_id')->constrained();
    $table->enum('status', ['registered', 'attended', 'no-show']);
    $table->timestamp('registered_at')->nullable();
    $table->timestamp('attended_at')->nullable();
    $table->timestamps();
});
```

#### User Audit Log
```php
Schema::create('user_audit_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained();
    $table->string('action'); // 'created', 'updated', 'subscribed', 'unsubscribed'
    $table->string('entity_type')->nullable(); // 'product', 'event', 'tier'
    $table->unsignedBigInteger('entity_id')->nullable();
    $table->json('old_values')->nullable();
    $table->json('new_values')->nullable();
    $table->foreignId('performed_by')->nullable()->constrained('users');
    $table->string('ip_address')->nullable();
    $table->timestamps();
});
```

---

## Sync Strategy

### What Gets Synced to CM?

1. **Custom Fields** (13 core fields)
   - Synced immediately via UserObserver (for individual updates)
   - Synced in batches via BulkSyncJob (for bulk operations)

2. **Tags** (unlimited)
   - Product subscriptions → Tags (`product:name`)
   - User segments → Tags (`segment:type`)
   - Behavioral flags → Tags (`behavior:action`)

### What Stays in Laravel Only?

1. **Detailed engagement data** (email_engagements table)
2. **Event attendance history** (events, event_attendances tables)
3. **Product subscription history** (product_subscriptions table)
4. **User audit logs** (user_audit_logs table)
5. **Any metadata/JSON fields**

---

## Example: Adding a New Product

**Bad Approach (Would hit 50-field limit):**
```php
// ❌ Don't do this - each product = 1 custom field
CM Custom Fields:
- product_newsletter (boolean)
- product_webinar (boolean)
- product_premium_content (boolean)
- product_api_access (boolean)
// ... 46 more fields left, but we might have 100+ products!
```

**Good Approach (Unlimited scalability):**
```php
// ✅ Use tags + Laravel database

// 1. Create product in Laravel
$product = Product::create(['name' => 'Premium Webinar Series']);

// 2. User subscribes
$user->products()->attach($product->id, [
    'subscribed_at' => now(),
    'source' => 'user',
]);

// 3. Sync to CM as a tag
CampaignTagService::tagUser($user, "product:premium-webinar-series");

// 4. Query in Laravel for analytics
$subscribers = Product::find($product->id)
    ->users()
    ->where('subscribed', true)
    ->count();

// 5. Segment in CM for email campaigns
// Create segment: "Has tag: product:premium-webinar-series"
```

---

## Example: User Engagement Workflow

### Scenario: Track user who opens email and attends event

```php
// 1. User opens email (webhook from CM)
EmailEngagement::create([
    'user_id' => $user->id,
    'campaign_id' => 'camp123',
    'event_type' => 'opened',
    'event_at' => now(),
]);

// 2. Update aggregated fields in users table
$user->update([
    'total_opens' => $user->total_opens + 1,
    'last_email_opened_at' => now(),
]);

// 3. Recalculate engagement score
$engagementScore = EngagementMetricsService::calculateEngagementScore($user);
$user->update(['engagement_score' => $engagementScore]);

// 4. Sync updated fields to CM (only the changed ones)
CmSyncService::syncUser($user, ['total_opens', 'last_email_opened_at', 'engagement_score']);

// 5. Update segment tags based on new score
if ($engagementScore > 80) {
    CampaignTagService::tagUser($user, 'segment:high-engagement');
    CampaignTagService::untagUser($user, 'segment:moderate-engagement');
}

// 6. User attends event
EventAttendance::create([
    'event_id' => $event->id,
    'user_id' => $user->id,
    'status' => 'attended',
    'attended_at' => now(),
]);

// 7. Tag user as event attendee
CampaignTagService::tagUser($user, 'behavior:event-attendee');

// 8. Check if frequent attendee (5+ events)
$attendanceCount = EventAttendance::where('user_id', $user->id)
    ->where('status', 'attended')
    ->count();

if ($attendanceCount >= 5) {
    CampaignTagService::tagUser($user, 'behavior:frequent-attendee');
}
```

**Result:**
- **Laravel**: Full detail of every open, every event attendance
- **CM Custom Fields**: Updated aggregated metrics (total_opens, engagement_score)
- **CM Tags**: Dynamic segments (high-engagement, event-attendee, frequent-attendee)

---

## Reporting & Analytics

### Use Laravel for Detailed Reports

```php
// Report: Product subscription trends over time
ProductSubscription::selectRaw('DATE(subscribed_at) as date, COUNT(*) as count')
    ->where('product_id', $productId)
    ->groupBy('date')
    ->get();

// Report: User engagement breakdown
User::selectRaw('
    CASE
        WHEN engagement_score > 80 THEN "high"
        WHEN engagement_score > 40 THEN "moderate"
        ELSE "low"
    END as segment,
    COUNT(*) as count
')->groupBy('segment')->get();

// Report: Event attendance by organization
Organization::withCount(['events', 'users'])
    ->with(['events' => function($q) {
        $q->withCount('attendances');
    }])
    ->get();
```

### Use CM for Email Campaigns

```php
// Send to high-engagement users who attended an event
Segment in CM:
- Has tag: segment:high-engagement
- AND Has tag: behavior:event-attendee

// Send to users in paid tier who haven't opened in 30 days
Segment in CM:
- Has tag: tier:paid-pro OR tier:paid-premium
- AND Has tag: segment:at-risk
```

---

## Benefits of This Approach

### 1. Scalability
- ✅ Add unlimited products (via tags)
- ✅ Add unlimited custom tracking (via Laravel tables)
- ✅ Never hit 50-field limit

### 2. Performance
- ✅ Sync only essential data to CM
- ✅ Reduce API calls (only 13 fields instead of 100+)
- ✅ Fast queries in Laravel for analytics

### 3. Flexibility
- ✅ Change data structure in Laravel without touching CM
- ✅ Add new products without CM schema changes
- ✅ Easy to add new tracking metrics

### 4. Cost Efficiency
- ✅ CM pricing based on subscribers, not fields
- ✅ Fewer API calls = lower costs
- ✅ No need for CM premium features

### 5. Data Ownership
- ✅ Laravel has complete data
- ✅ Easy to export/migrate
- ✅ Not locked into CM structure

---

## Migration Path for New Products

When adding a new product:

```php
// 1. Create product in Laravel
php artisan make:model Product -mf

// 2. Create subscription pivot table
php artisan make:migration create_product_user_table

// 3. Add product
$product = Product::create([
    'name' => 'New Product',
    'slug' => 'new-product',
    'tier_required' => 'paid_pro',
]);

// 4. Define tag name
$tagName = "product:" . $product->slug; // "product:new-product"

// 5. When users subscribe
$user->products()->attach($product->id);
CampaignTagService::tagUser($user, $tagName);

// 6. Create email campaign in CM
// Segment: Has tag "product:new-product"
```

**No CM custom field changes required!** 🎉

---

## Summary

| Requirement | Solution | Location |
|------------|----------|----------|
| Core user data (13 fields) | Custom Fields | Campaign Monitor |
| Product subscriptions | Tags | Campaign Monitor |
| User segments | Tags | Campaign Monitor |
| Behavioral flags | Tags | Campaign Monitor |
| Detailed engagement | Database | Laravel |
| Event attendance | Database | Laravel |
| Subscription history | Database | Laravel |
| Audit logs | Database | Laravel |
| Analytics & Reports | Queries | Laravel |
| Email campaigns | Segments | Campaign Monitor |

**Key Principle**: Campaign Monitor is for **email delivery**, Laravel is for **data management**.

---

*API Reference: https://www.campaignmonitor.com/api/v3-3/getting-started/*

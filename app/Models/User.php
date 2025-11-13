<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'fullname',
        'email',
        'password',
        'cm_subscriber_id',
        'cm_status',
        'cm_subscribed_at',
        'cm_unsubscribed_at',
        'permission_to_track',
        'activity_score_7d',
        'activity_score_30d',
        'last_login_at',
        'cm_synced_at',
        'account_status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'cm_subscribed_at' => 'datetime',
            'cm_unsubscribed_at' => 'datetime',
            'permission_to_track' => 'boolean',
            'activity_score_7d' => 'integer',
            'activity_score_30d' => 'integer',
            'last_login_at' => 'datetime',
            'cm_synced_at' => 'datetime',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->fullname)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    public function customFieldValues()
    {
        return $this->hasMany(CmCustomFieldValue::class);
    }

    public function getCustomFieldValue($fieldKey)
    {
        $field = CmCustomField::where('field_key', $fieldKey)->first();
        if (!$field) {
            return null;
        }

        $value = $this->customFieldValues()
            ->where('cm_custom_field_id', $field->id)
            ->first();

        return $value ? $value->value : null;
    }

    public function setCustomFieldValue($fieldKey, $value)
    {
        $field = CmCustomField::firstOrCreate(
            ['field_key' => $fieldKey],
            ['field_name' => $fieldKey, 'data_type' => 'text']
        );

        return $this->customFieldValues()->updateOrCreate(
            ['cm_custom_field_id' => $field->id],
            ['value' => $value]
        );
    }

    public function scopeActive($query)
    {
        return $query->where('cm_status', 'active');
    }

    public function scopeByCampaignMonitorId($query, $subscriberId)
    {
        return $query->where('cm_subscriber_id', $subscriberId);
    }

    /**
     * Product subscription relationships
     */
    public function productSubscriptions()
    {
        return $this->hasMany(UserProductSubscription::class);
    }

    public function activeProductSubscriptions()
    {
        return $this->productSubscriptions()->where('is_active', true);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'user_product_subscriptions')
                    ->withPivot(['is_active', 'subscribed_at', 'unsubscribed_at', 'source', 'notes'])
                    ->withTimestamps()
                    ->wherePivot('is_active', true);
    }

    public function allProducts()
    {
        return $this->belongsToMany(Product::class, 'user_product_subscriptions')
                    ->withPivot(['is_active', 'subscribed_at', 'unsubscribed_at', 'source', 'notes'])
                    ->withTimestamps();
    }

    /**
     * Check if user is subscribed to a product
     */
    public function isSubscribedTo($productId): bool
    {
        return $this->productSubscriptions()
                    ->where('product_id', $productId)
                    ->where('is_active', true)
                    ->exists();
    }

    /**
     * Subscribe user to a product
     */
    public function subscribeTo($productId, string $source = 'manual', ?string $notes = null)
    {
        return $this->productSubscriptions()->updateOrCreate(
            ['product_id' => $productId],
            [
                'is_active' => true,
                'subscribed_at' => now(),
                'unsubscribed_at' => null,
                'source' => $source,
                'notes' => $notes,
            ]
        );
    }

    /**
     * Unsubscribe user from a product
     */
    public function unsubscribeFrom($productId, ?string $notes = null)
    {
        return $this->productSubscriptions()
                    ->where('product_id', $productId)
                    ->update([
                        'is_active' => false,
                        'unsubscribed_at' => now(),
                        'notes' => $notes,
                    ]);
    }

    /**
     * Get count of active product subscriptions
     */
    public function getActiveProductsCountAttribute(): int
    {
        return $this->activeProductSubscriptions()->count();
    }

    /**
     * Activity tracking scopes
     */
    public function scopeHighActivity7d($query, $threshold = 70)
    {
        return $query->where('activity_score_7d', '>=', $threshold);
    }

    public function scopeHighActivity30d($query, $threshold = 70)
    {
        return $query->where('activity_score_30d', '>=', $threshold);
    }

    public function scopeRecentlyActive($query, $days = 7)
    {
        return $query->where('last_login_at', '>=', now()->subDays($days));
    }

    public function scopeNeedsCmSync($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('cm_synced_at')
              ->orWhere('cm_synced_at', '<', now()->subDay());
        });
    }

    /**
     * Update user's last login timestamp
     */
    public function updateLastLogin(): void
    {
        $this->update(['last_login_at' => now()]);
    }
}

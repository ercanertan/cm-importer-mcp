<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'fullname',
        'name', // Alias for fullname (backward compatibility)
        'email',
        'password',
        'organization_id',
        'domain_id',
        'cm_subscriber_id',
        'cm_status',
        'cm_subscribed_at',
        'cm_unsubscribed_at',
        'cm_status_changed_at',
        'permission_to_track',
        'tier',
        'total_emails_sent',
        'total_opens',
        'total_clicks',
        'total_bounces',
        'engagement_score',
        'last_email_opened_at',
        'last_email_clicked_at',
        'last_activity_at',
        'temp_campaign_tag',
        'cm_tags_need_sync',      // Mark for bulk tag sync
        'cm_tags_synced_at',      // Timestamp of last tag sync
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
            'cm_status_changed_at' => 'datetime',
            'cm_tags_synced_at' => 'datetime',
            'permission_to_track' => 'boolean',
            'cm_tags_need_sync' => 'boolean',
            'engagement_score' => 'decimal:2',
            'last_email_opened_at' => 'datetime',
            'last_email_clicked_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    /**
     * Accessor for 'name' - maps to 'fullname' for backward compatibility
     */
    public function getNameAttribute(): string
    {
        return $this->fullname;
    }

    /**
     * Mutator for 'name' - maps to 'fullname' for backward compatibility
     */
    public function setNameAttribute(string $value): void
    {
        $this->attributes['fullname'] = $value;
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

    /**
     * Get the organization that the user belongs to (legacy single organization)
     */
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get all organizations that the user belongs to (many-to-many)
     */
    public function organizations()
    {
        return $this->belongsToMany(Organization::class)
            ->withPivot('is_manual', 'is_primary')
            ->withTimestamps()
            ->using(\App\Models\OrganizationUser::class);
    }

    /**
     * Get the primary organization for this user
     */
    public function primaryOrganization()
    {
        return $this->belongsToMany(Organization::class)
            ->withPivot('is_manual', 'is_primary')
            ->wherePivot('is_primary', true)
            ->withTimestamps()
            ->using(\App\Models\OrganizationUser::class)
            ->first();
    }

    /**
     * Set an organization as the primary organization for this user
     * Ensures only one organization can be primary
     */
    public function setPrimaryOrganization(int $organizationId): bool
    {
        // Check if the user belongs to this organization
        if (!$this->organizations()->where('organization_id', $organizationId)->exists()) {
            return false;
        }

        // Use transaction to ensure atomicity
        \DB::transaction(function () use ($organizationId) {
            // Get all organization IDs for this user
            $allOrgIds = $this->organizations()->pluck('organization_id')->toArray();

            // Set all organizations to non-primary for this user
            foreach ($allOrgIds as $orgId) {
                $this->organizations()->updateExistingPivot($orgId, ['is_primary' => false]);
            }

            // Set the specified organization as primary
            $this->organizations()->updateExistingPivot($organizationId, ['is_primary' => true]);
        });

        return true;
    }

    /**
     * Check if a specific organization is the primary one for this user
     */
    public function isPrimaryOrganization(int $organizationId): bool
    {
        $org = $this->organizations()
            ->where('organization_id', $organizationId)
            ->first();

        return $org && $org->pivot->is_primary;
    }

    /**
     * Get the domain of the user
     */
    public function domain()
    {
        return $this->belongsTo(Domain::class);
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
            ['field_name' => $fieldKey, 'data_type' => 'Text']
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
     * Get all email engagements for this user
     */
    public function emailEngagements()
    {
        return $this->hasMany(EmailEngagement::class);
    }

    /**
     * Get all event attendances for this user
     */
    public function eventAttendances()
    {
        return $this->hasMany(EventAttendance::class);
    }

    /**
     * Get all events this user is attending (via attendances)
     */
    public function events()
    {
        return $this->belongsToMany(Event::class, 'event_attendances')
            ->withPivot(['status', 'registered_at', 'attended_at', 'attendance_confirmed'])
            ->withTimestamps();
    }

    /**
     * Get events this user is organizing
     */
    public function organizedEvents()
    {
        return $this->hasMany(Event::class, 'organizer_id');
    }
}

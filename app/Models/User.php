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
            'permission_to_track' => 'boolean',
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
            ->withPivot('is_manual')
            ->withTimestamps();
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
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Event extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'type',
        'slug',
        'starts_at',
        'ends_at',
        'timezone',
        'location_type',
        'location_name',
        'location_address',
        'location_url',
        'requires_registration',
        'max_attendees',
        'registration_opens_at',
        'registration_closes_at',
        'status',
        'organizer_id',
        'organizer_name',
        'organizer_email',
        'metadata',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'registration_opens_at' => 'datetime',
        'registration_closes_at' => 'datetime',
        'requires_registration' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Boot the model and set up event listeners
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-generate slug if not provided
        static::creating(function ($event) {
            if (empty($event->slug)) {
                $event->slug = Str::slug($event->name);
            }
        });
    }

    /**
     * Get the organizer of the event
     */
    public function organizer()
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    /**
     * Get all attendances for this event
     */
    public function attendances()
    {
        return $this->hasMany(EventAttendance::class);
    }

    /**
     * Get all users who attended this event
     */
    public function attendees()
    {
        return $this->belongsToMany(User::class, 'event_attendances')
            ->withPivot(['status', 'registered_at', 'attended_at', 'attendance_confirmed'])
            ->withTimestamps();
    }

    /**
     * Scope to filter published events
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    /**
     * Scope to filter upcoming events
     */
    public function scopeUpcoming($query)
    {
        return $query->where('starts_at', '>', now());
    }

    /**
     * Scope to filter past events
     */
    public function scopePast($query)
    {
        return $query->where('starts_at', '<', now());
    }

    /**
     * Scope to filter by event type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to filter events happening within a date range
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('starts_at', [$startDate, $endDate]);
    }

    /**
     * Scope to filter cancelled events
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Scope to filter completed events
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Check if the event is published
     */
    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /**
     * Check if the event is upcoming
     */
    public function isUpcoming(): bool
    {
        return $this->starts_at > now();
    }

    /**
     * Check if the event is past
     */
    public function isPast(): bool
    {
        return $this->starts_at < now();
    }

    /**
     * Check if the event is cancelled
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Check if registration is open
     */
    public function isRegistrationOpen(): bool
    {
        if (!$this->requires_registration) {
            return false;
        }

        $now = now();

        // Check if registration period is set
        if ($this->registration_opens_at && $now < $this->registration_opens_at) {
            return false;
        }

        if ($this->registration_closes_at && $now > $this->registration_closes_at) {
            return false;
        }

        // Check if event has reached max attendees
        if ($this->max_attendees && $this->attendances()->count() >= $this->max_attendees) {
            return false;
        }

        return true;
    }

    /**
     * Check if the event is full (max capacity reached)
     */
    public function isFull(): bool
    {
        if (!$this->max_attendees) {
            return false;
        }

        return $this->attendances()->count() >= $this->max_attendees;
    }

    /**
     * Get the number of available spots
     */
    public function availableSpots(): ?int
    {
        if (!$this->max_attendees) {
            return null; // Unlimited
        }

        return max(0, $this->max_attendees - $this->attendances()->count());
    }

    /**
     * Get attendee count
     */
    public function attendeeCount(): int
    {
        return $this->attendances()->where('attendance_confirmed', true)->count();
    }

    /**
     * Get registration count (includes those who registered but might not have attended)
     */
    public function registrationCount(): int
    {
        return $this->attendances()->whereIn('status', ['registered', 'attended'])->count();
    }
}

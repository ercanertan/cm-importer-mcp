<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventAttendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'user_id',
        'status',
        'registered_at',
        'attended_at',
        'cancelled_at',
        'attendance_confirmed',
        'registration_source',
        'metadata',
    ];

    protected $casts = [
        'registered_at' => 'datetime',
        'attended_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'attendance_confirmed' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Boot the model and set up event listeners
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-set registered_at timestamp when creating
        static::creating(function ($attendance) {
            if (empty($attendance->registered_at)) {
                $attendance->registered_at = now();
            }
        });

        // Auto-set attended_at when status changes to attended
        static::updating(function ($attendance) {
            if ($attendance->isDirty('status') && $attendance->status === 'attended') {
                if (empty($attendance->attended_at)) {
                    $attendance->attended_at = now();
                }
                $attendance->attendance_confirmed = true;
            }

            // Auto-set cancelled_at when status changes to cancelled
            if ($attendance->isDirty('status') && $attendance->status === 'cancelled') {
                if (empty($attendance->cancelled_at)) {
                    $attendance->cancelled_at = now();
                }
            }
        });
    }

    /**
     * Get the event this attendance belongs to
     */
    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get the user who is attending
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to filter by status
     */
    public function scopeOfStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter registered attendees
     */
    public function scopeRegistered($query)
    {
        return $query->where('status', 'registered');
    }

    /**
     * Scope to filter attended attendees
     */
    public function scopeAttended($query)
    {
        return $query->where('status', 'attended');
    }

    /**
     * Scope to filter cancelled registrations
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Scope to filter no-shows
     */
    public function scopeNoShow($query)
    {
        return $query->where('status', 'no_show');
    }

    /**
     * Scope to filter confirmed attendances
     */
    public function scopeConfirmed($query)
    {
        return $query->where('attendance_confirmed', true);
    }

    /**
     * Scope to filter by event
     */
    public function scopeForEvent($query, $eventId)
    {
        return $query->where('event_id', $eventId);
    }

    /**
     * Scope to filter by user
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to filter attendances within a date range
     */
    public function scopeRegisteredBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('registered_at', [$startDate, $endDate]);
    }

    /**
     * Check if the user attended
     */
    public function hasAttended(): bool
    {
        return $this->status === 'attended';
    }

    /**
     * Check if the registration is active (not cancelled)
     */
    public function isActive(): bool
    {
        return $this->status !== 'cancelled';
    }

    /**
     * Check if the registration was cancelled
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Check if marked as no-show
     */
    public function isNoShow(): bool
    {
        return $this->status === 'no_show';
    }

    /**
     * Mark as attended (check-in)
     */
    public function markAsAttended(): bool
    {
        return $this->update([
            'status' => 'attended',
            'attended_at' => now(),
            'attendance_confirmed' => true,
        ]);
    }

    /**
     * Cancel the registration
     */
    public function cancel(): bool
    {
        return $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);
    }

    /**
     * Mark as no-show
     */
    public function markAsNoShow(): bool
    {
        return $this->update([
            'status' => 'no_show',
        ]);
    }
}

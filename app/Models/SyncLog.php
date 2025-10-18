<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncLog extends Model
{
    use HasFactory;
    protected $fillable = [
        'type',
        'status',
        'user_id',
        'total_items',
        'processed_items',
        'successful_items',
        'failed_items',
        'started_at',
        'completed_at',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'total_items' => 'integer',
        'processed_items' => 'integer',
        'successful_items' => 'integer',
        'failed_items' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Get the user who initiated the sync
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mark sync as started
     */
    public function markAsStarted(): void
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    /**
     * Mark sync as completed
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark sync as failed
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Update progress
     */
    public function updateProgress(int $processed, int $successful, int $failed): void
    {
        $this->update([
            'processed_items' => $processed,
            'successful_items' => $successful,
            'failed_items' => $failed,
        ]);
    }

    /**
     * Get progress percentage
     */
    public function getProgressPercentageAttribute(): int
    {
        if ($this->total_items === 0) {
            return 0;
        }

        return (int) (($this->processed_items / $this->total_items) * 100);
    }

    /**
     * Check if sync is still running
     */
    public function isRunning(): bool
    {
        return $this->status === 'processing';
    }

    /**
     * Check if sync is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if sync has failed
     */
    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Get duration in seconds
     */
    public function getDurationAttribute(): ?int
    {
        if (!$this->started_at) {
            return null;
        }

        $endTime = $this->completed_at ?? now();
        return $this->started_at->diffInSeconds($endTime);
    }
}

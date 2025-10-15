<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CmImportLog extends Model
{
    protected $fillable = [
        'user_id',
        'filename',
        'file_hash',
        'storage_path',
        'file_type',
        'status',
        'total_rows',
        'total_chunks',
        'completed_chunks',
        'failed_chunks',
        'is_chunked',
        'processed_rows',
        'created_count',
        'updated_count',
        'failed_count',
        'memory_peak',
        'memory_current',
        'custom_fields_detected',
        'error_details',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'custom_fields_detected' => 'array',
        'error_details' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function markAsStarted()
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    public function markAsCompleted()
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed($errorDetails = null)
    {
        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
            'error_details' => $errorDetails,
        ]);
    }

    public function incrementProcessed()
    {
        $this->refresh();
        $this->processed_rows++;
        $this->save();
    }

    public function incrementCreated()
    {
        $this->created_count++;
        // Don't save here, will be saved with processed_rows
    }

    public function incrementUpdated()
    {
        $this->updated_count++;
        // Don't save here, will be saved with processed_rows
    }

    public function incrementFailed()
    {
        $this->failed_count++;
        // Don't save here, will be saved with processed_rows
    }

    public function getProgressPercentageAttribute()
    {
        if ($this->total_rows === 0) {
            return 0;
        }

        return round(($this->processed_rows / $this->total_rows) * 100, 2);
    }

    public function getDurationAttribute()
    {
        if (!$this->started_at) {
            return null;
        }

        $endTime = $this->completed_at ?? now();
        return $this->started_at->diffInSeconds($endTime);
    }

    /**
     * Get the user who initiated this import
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

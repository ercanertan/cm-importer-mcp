<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CmImportLog extends Model
{
    protected $fillable = [
        'filename',
        'file_hash',
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
        $this->increment('processed_rows');
    }

    public function incrementCreated()
    {
        $this->increment('created_count');
    }

    public function incrementUpdated()
    {
        $this->increment('updated_count');
    }

    public function incrementFailed()
    {
        $this->increment('failed_count');
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
}

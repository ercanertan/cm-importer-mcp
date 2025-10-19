<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CmCustomField extends Model
{
    use HasFactory;
    protected $fillable = [
        'field_key',
        'field_name',
        'data_type',
        'options',
        'is_active',
        'is_user_editable',
        'last_seen_at',
    ];

    protected $casts = [
        'options' => 'array',
        'is_active' => 'boolean',
        'is_user_editable' => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    public function customFieldValues()
    {
        return $this->hasMany(CmCustomFieldValue::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'cm_custom_field_values')
                    ->withPivot('value')
                    ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeUserEditable($query)
    {
        return $query->where('is_user_editable', true)->where('is_active', true);
    }

    public function scopeByFieldKey($query, $fieldKey)
    {
        return $query->where('field_key', $fieldKey);
    }

    public function detectDataType($value)
    {
        if (is_numeric($value)) {
            return 'number';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            return 'date';
        }

        if (strpos($value, ',') !== false || strpos($value, ';') !== false) {
            return 'multi_select';
        }

        return 'text';
    }

    public function updateLastSeen()
    {
        $this->update(['last_seen_at' => now()]);
    }
}

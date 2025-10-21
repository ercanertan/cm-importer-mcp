<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CmCustomFieldValue extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'cm_custom_field_id',
        'value',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function customField()
    {
        return $this->belongsTo(CmCustomField::class, 'cm_custom_field_id');
    }

    public function scopeByField($query, $fieldKey)
    {
        return $query->whereHas('customField', function ($q) use ($fieldKey) {
            $q->where('field_key', $fieldKey);
        });
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function getValueAttribute($value)
    {
        $field = $this->customField;

        // Decode JSON for multi-select fields with allow_multiple
        if ($field && $field->data_type === 'multi_select' && $field->allow_multiple) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }

        return $value;
    }

    public function setValueAttribute($value)
    {
        $field = $this->customField;

        // Encode to JSON for multi-select fields with allow_multiple
        if ($field && $field->data_type === 'multi_select' && $field->allow_multiple && is_array($value)) {
            $this->attributes['value'] = json_encode($value);
        } else {
            $this->attributes['value'] = $value;
        }
    }

    public function getFormattedValueAttribute()
    {
        $field = $this->customField;

        if (!$field) {
            return $this->value;
        }

        switch ($field->data_type) {
            case 'date':
                try {
                    $timestamp = strtotime($this->value);
                    // strtotime returns false for invalid dates, or -1/false for completely invalid strings
                    if ($timestamp === false || $timestamp === -1 || $timestamp < 0) {
                        return $this->value;
                    }
                    return date('Y-m-d', $timestamp);
                } catch (\Exception $e) {
                    return $this->value;
                }
            case 'number':
                return is_numeric($this->value) ? (float) $this->value : $this->value;
            case 'multi_select':
                // If allow_multiple, value is already an array from the accessor
                if ($field->allow_multiple) {
                    return $this->value;
                }
                // Otherwise, it's a single value
                return $this->value;
            default:
                return $this->value;
        }
    }
}

<?php

namespace App\Models;

use App\Enums\CustomFieldTypes;
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

        // Decode JSON for MultiSelectMany fields
        if ($field && $field->data_type === CustomFieldTypes::MultiSelectMany) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }

        return $value;
    }

    public function setValueAttribute($value)
    {
        $field = $this->customField;

        // Encode to JSON for MultiSelectMany fields
        if ($field && $field->data_type === CustomFieldTypes::MultiSelectMany && is_array($value)) {
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
            case CustomFieldTypes::Date:
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
            case CustomFieldTypes::Number:
                return is_numeric($this->value) ? (float) $this->value : $this->value;
            case CustomFieldTypes::MultiSelectMany:
                // Value is already an array from the accessor
                return $this->value;
            case CustomFieldTypes::MultiSelectOne:
                // Single value
                return $this->value;
            default:
                return $this->value;
        }
    }
}

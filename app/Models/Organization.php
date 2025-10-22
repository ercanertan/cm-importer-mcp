<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'is_active',
        'conditional_rules',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'conditional_rules' => 'array',
    ];

    /**
     * Get all users belonging to this organization (legacy single organization)
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get all users belonging to this organization (many-to-many)
     */
    public function usersMany(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_user')
            ->withPivot('is_manual', 'is_primary')
            ->withTimestamps()
            ->as('membership')
            ->using(\App\Models\OrganizationUser::class);
    }

    /**
     * Get all domains associated with this organization (many-to-many)
     */
    public function domains(): BelongsToMany
    {
        return $this->belongsToMany(Domain::class, 'organization_domain')
            ->withTimestamps();
    }

    /**
     * Get the number of users in this organization
     */
    public function getUserCountAttribute(): int
    {
        return $this->users()->count();
    }

    /**
     * Scope to get only active organizations
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Check if this organization has conditional rules
     */
    public function hasConditionalRules(): bool
    {
        return !empty($this->conditional_rules);
    }

    /**
     * Get the condition logic (AND/OR)
     */
    public function getConditionLogic(): ?string
    {
        return $this->conditional_rules['logic'] ?? null;
    }

    /**
     * Get the conditions array
     * Returns a plain array to prevent Livewire serialization errors
     * Supports both old format (conditions) and new format (items)
     */
    public function getConditions(): array
    {
        // New format with nested groups
        if (isset($this->conditional_rules['items'])) {
            return json_decode(json_encode($this->conditional_rules['items']), true);
        }

        // Old format - backward compatibility
        $conditions = $this->conditional_rules['conditions'] ?? [];
        return json_decode(json_encode($conditions), true);
    }

    /**
     * Check if organization uses new nested condition format
     */
    public function usesNestedConditions(): bool
    {
        return isset($this->conditional_rules['items']);
    }

    /**
     * Convert old format conditions to new nested format
     */
    public function convertToNestedFormat(): array
    {
        if ($this->usesNestedConditions()) {
            return $this->conditional_rules;
        }

        $oldConditions = $this->conditional_rules['conditions'] ?? [];
        $logic = $this->conditional_rules['logic'] ?? 'AND';

        // Convert each condition to new format with type
        $items = array_map(function ($condition) {
            return array_merge(['type' => 'condition'], $condition);
        }, $oldConditions);

        return [
            'logic' => $logic,
            'items' => $items,
        ];
    }

    /**
     * Get all conditions from nested structure (flattened)
     * Useful for validation and display
     */
    public function getAllConditionsFlat(): array
    {
        $items = $this->getConditions();
        return $this->flattenItems($items);
    }

    /**
     * Recursively flatten nested items to get all conditions
     */
    protected function flattenItems(array $items): array
    {
        $flattened = [];

        foreach ($items as $item) {
            if (($item['type'] ?? 'condition') === 'condition') {
                $flattened[] = $item;
            } elseif (($item['type'] ?? '') === 'group') {
                $flattened = array_merge(
                    $flattened,
                    $this->flattenItems($item['items'] ?? [])
                );
            }
        }

        return $flattened;
    }
}

<?php

namespace App\Services;

use App\Models\User;
use App\Models\Organization;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ConditionalRuleEvaluator
{
    /**
     * Check if a user meets an organization's conditional rules
     * Supports both old format (conditions) and new format (items with nested groups)
     *
     * @param User $user
     * @param array $conditionalRules Format: ['logic' => 'AND|OR', 'conditions' => [...]] or ['logic' => 'AND|OR', 'items' => [...]]
     * @return bool
     */
    public function userMeetsConditions(User $user, array $conditionalRules): bool
    {
        // New format with nested items
        if (isset($conditionalRules['items'])) {
            if (empty($conditionalRules['items'])) {
                return true;
            }

            $logic = $conditionalRules['logic'] ?? 'AND';
            $userCustomFields = $user->customFieldValues()
                ->pluck('value', 'cm_custom_field_id')
                ->toArray();

            return $this->evaluateItems($userCustomFields, $conditionalRules['items'], $logic);
        }

        // Old format - backward compatibility
        if (empty($conditionalRules) || empty($conditionalRules['conditions'])) {
            // No conditions means everyone matches (for backward compatibility)
            return true;
        }

        $conditions = $conditionalRules['conditions'];
        $logic = $conditionalRules['logic'] ?? 'AND';

        // Load user's custom field values
        $userCustomFields = $user->customFieldValues()
            ->pluck('value', 'cm_custom_field_id')
            ->toArray();

        if ($logic === 'AND') {
            // ALL conditions must match
            foreach ($conditions as $condition) {
                if (!$this->evaluateCondition($userCustomFields, $condition)) {
                    return false;
                }
            }
            return true;
        } else {
            // OR logic - ANY condition can match
            foreach ($conditions as $condition) {
                if ($this->evaluateCondition($userCustomFields, $condition)) {
                    return true;
                }
            }
            return false;
        }
    }

    /**
     * Evaluate an array of items (can be conditions or groups)
     * Supports nested groups recursively
     *
     * @param array $userCustomFields
     * @param array $items
     * @param string $logic
     * @return bool
     */
    protected function evaluateItems(array $userCustomFields, array $items, string $logic): bool
    {
        if (empty($items)) {
            return true;
        }

        if ($logic === 'AND') {
            foreach ($items as $item) {
                if (!$this->evaluateItem($userCustomFields, $item)) {
                    return false;
                }
            }
            return true;
        } else { // OR
            foreach ($items as $item) {
                if ($this->evaluateItem($userCustomFields, $item)) {
                    return true;
                }
            }
            return false;
        }
    }

    /**
     * Evaluate a single item (either a condition or a group)
     *
     * @param array $userCustomFields
     * @param array $item
     * @return bool
     */
    protected function evaluateItem(array $userCustomFields, array $item): bool
    {
        $type = $item['type'] ?? 'condition';

        if ($type === 'condition') {
            return $this->evaluateCondition($userCustomFields, $item);
        } elseif ($type === 'group') {
            $groupLogic = $item['logic'] ?? 'AND';
            $groupItems = $item['items'] ?? [];
            return $this->evaluateItems($userCustomFields, $groupItems, $groupLogic);
        }

        return false;
    }

    /**
     * Batch evaluate users against an organization's conditional rules
     * Returns array of user IDs that match the conditions
     *
     * @param Collection $users Collection of User models or user data arrays
     * @param Organization $organization
     * @return array Array of user IDs that meet the conditions
     */
    public function batchEvaluateUsers(Collection $users, Organization $organization): array
    {
        if (!$organization->hasConditionalRules()) {
            // No rules = all users match
            return $users->pluck('id')->toArray();
        }

        $conditionalRules = $organization->conditional_rules;
        $logic = $conditionalRules['logic'] ?? 'AND';

        // New format with nested items
        if (isset($conditionalRules['items'])) {
            if (empty($conditionalRules['items'])) {
                return $users->pluck('id')->toArray();
            }

            // Get all user IDs
            $userIds = $users->pluck('id')->toArray();

            if (empty($userIds)) {
                return [];
            }

            // Load all custom field values for these users in one query
            $customFieldValues = DB::table('cm_custom_field_values')
                ->whereIn('user_id', $userIds)
                ->select('user_id', 'cm_custom_field_id', 'value')
                ->get()
                ->groupBy('user_id')
                ->map(function ($values) {
                    return $values->pluck('value', 'cm_custom_field_id')->toArray();
                })
                ->toArray();

            // Evaluate each user using nested items
            $matchingUserIds = [];

            foreach ($userIds as $userId) {
                $userFields = $customFieldValues[$userId] ?? [];

                if ($this->evaluateItems($userFields, $conditionalRules['items'], $logic)) {
                    $matchingUserIds[] = $userId;
                }
            }

            return $matchingUserIds;
        }

        // Old format - backward compatibility
        $conditions = $conditionalRules['conditions'] ?? [];

        if (empty($conditions)) {
            return $users->pluck('id')->toArray();
        }

        // Get all user IDs
        $userIds = $users->pluck('id')->toArray();

        if (empty($userIds)) {
            return [];
        }

        // Load all custom field values for these users in one query
        $customFieldValues = DB::table('cm_custom_field_values')
            ->whereIn('user_id', $userIds)
            ->select('user_id', 'cm_custom_field_id', 'value')
            ->get()
            ->groupBy('user_id')
            ->map(function ($values) {
                return $values->pluck('value', 'cm_custom_field_id')->toArray();
            })
            ->toArray();

        // Evaluate each user
        $matchingUserIds = [];

        foreach ($userIds as $userId) {
            $userFields = $customFieldValues[$userId] ?? [];

            if ($this->evaluateBatchConditions($userFields, $conditions, $logic)) {
                $matchingUserIds[] = $userId;
            }
        }

        return $matchingUserIds;
    }

    /**
     * Evaluate conditions for batch processing (in-memory evaluation)
     *
     * @param array $userCustomFields Array of field_id => value
     * @param array $conditions
     * @param string $logic 'AND' or 'OR'
     * @return bool
     */
    protected function evaluateBatchConditions(array $userCustomFields, array $conditions, string $logic): bool
    {
        if ($logic === 'AND') {
            // ALL conditions must match
            foreach ($conditions as $condition) {
                if (!$this->evaluateCondition($userCustomFields, $condition)) {
                    return false;
                }
            }
            return true;
        } else {
            // OR logic - ANY condition can match
            foreach ($conditions as $condition) {
                if ($this->evaluateCondition($userCustomFields, $condition)) {
                    return true;
                }
            }
            return false;
        }
    }

    /**
     * Evaluate a single condition against user's custom fields
     *
     * @param array $userCustomFields Array of field_id => value
     * @param array $condition Format: ['field_id' => X, 'operator' => 'equals', 'value' => 'Y']
     * @return bool
     */
    protected function evaluateCondition(array $userCustomFields, array $condition): bool
    {
        $fieldId = $condition['field_id'] ?? null;
        $operator = $condition['operator'] ?? 'equals';
        $expectedValue = $condition['value'] ?? '';

        if (!$fieldId) {
            return false;
        }

        // Get the actual value from user's custom fields
        $actualValue = $userCustomFields[$fieldId] ?? null;

        // Evaluate based on operator
        switch ($operator) {
            case 'equals':
                return $actualValue === $expectedValue;

            case 'not_equals':
                return $actualValue !== $expectedValue;

            case 'contains':
                return $actualValue !== null && str_contains(strtolower($actualValue), strtolower($expectedValue));

            case 'not_contains':
                return $actualValue === null || !str_contains(strtolower($actualValue), strtolower($expectedValue));

            case 'starts_with':
                return $actualValue !== null && str_starts_with(strtolower($actualValue), strtolower($expectedValue));

            case 'ends_with':
                return $actualValue !== null && str_ends_with(strtolower($actualValue), strtolower($expectedValue));

            case 'is_empty':
                return empty($actualValue);

            case 'is_not_empty':
                return !empty($actualValue);

            default:
                return false;
        }
    }

    /**
     * Evaluate conditional rules for CSV import data (before user is created/loaded)
     * This is optimized for bulk import scenarios
     *
     * @param array $rowData Raw CSV row data with custom field values
     * @param array $fieldKeyToIdMap Map of field_key => field_id
     * @param array $conditionalRules
     * @return bool
     */
    public function evaluateRowData(array $rowData, array $fieldKeyToIdMap, array $conditionalRules): bool
    {
        if (empty($conditionalRules)) {
            return true;
        }

        // Convert row data (field_key => value) to (field_id => value)
        $userCustomFields = [];
        foreach ($rowData as $fieldKey => $value) {
            if (isset($fieldKeyToIdMap[$fieldKey])) {
                $userCustomFields[$fieldKeyToIdMap[$fieldKey]] = $value;
            }
        }

        // New format with nested items
        if (isset($conditionalRules['items'])) {
            if (empty($conditionalRules['items'])) {
                return true;
            }

            $logic = $conditionalRules['logic'] ?? 'AND';
            return $this->evaluateItems($userCustomFields, $conditionalRules['items'], $logic);
        }

        // Old format - backward compatibility
        if (empty($conditionalRules['conditions'])) {
            return true;
        }

        $conditions = $conditionalRules['conditions'];
        $logic = $conditionalRules['logic'] ?? 'AND';

        return $this->evaluateBatchConditions($userCustomFields, $conditions, $logic);
    }
}

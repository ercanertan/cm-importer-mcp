<?php

namespace App\Livewire\Admin\Users;

use App\Enums\CustomFieldTypes;
use App\Models\CmCustomField;
use App\Models\CmCustomFieldValue;
use App\Models\User;
use Livewire\Component;

class UserCustomFieldsEditor extends Component
{
    public $userId;
    public $user;
    public $customFields = [];
    public $fieldValues = [];

    public function mount($userId)
    {
        $this->userId = $userId;
        $this->user = User::findOrFail($userId);
        $this->loadCustomFields();
    }

    protected function loadCustomFields()
    {
        // Admin can see ALL active custom fields, not just user-editable ones
        $this->customFields = CmCustomField::active()
            ->orderBy('field_name')
            ->get()
            ->toArray();

        // Load existing values for the user
        foreach ($this->customFields as $field) {
            $existingValue = CmCustomFieldValue::where('user_id', $this->userId)
                ->where('cm_custom_field_id', $field['id'])
                ->first();

            if ($existingValue) {
                $this->fieldValues[$field['id']] = $existingValue->value;
            } else {
                $this->fieldValues[$field['id']] = '';
            }
        }
    }

    public function save()
    {
        // Validate based on data types
        $rules = [];
        foreach ($this->customFields as $field) {
            $fieldRules = [];

            if ($field['data_type'] === CustomFieldTypes::Number->value) {
                $fieldRules[] = 'nullable';
                $fieldRules[] = 'numeric';
            } elseif ($field['data_type'] === CustomFieldTypes::Date->value) {
                $fieldRules[] = 'nullable';
                $fieldRules[] = 'date';
            } elseif ($field['data_type'] === CustomFieldTypes::MultiSelectMany->value) {
                // Allow array for MultiSelectMany
                $fieldRules[] = 'nullable';
                $fieldRules[] = 'array';
            } else {
                $fieldRules[] = 'nullable';
                $fieldRules[] = 'string';
            }

            $rules["fieldValues.{$field['id']}"] = $fieldRules;
        }

        $this->validate($rules);

        try {
            foreach ($this->customFields as $field) {
                $value = $this->fieldValues[$field['id']] ?? null;

                // Check if value is empty (consider empty arrays as empty too)
                if (empty($value) || (is_array($value) && count($value) === 0)) {
                    // Delete if value is empty
                    CmCustomFieldValue::where('user_id', $this->userId)
                        ->where('cm_custom_field_id', $field['id'])
                        ->delete();
                } else {
                    // Update or create the value
                    CmCustomFieldValue::updateOrCreate(
                        [
                            'user_id' => $this->userId,
                            'cm_custom_field_id' => $field['id'],
                        ],
                        [
                            'value' => $value,
                        ]
                    );
                }
            }

            session()->flash('message', 'Custom fields updated successfully for ' . $this->user->name . '.');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to update user custom fields', [
                'error' => $e->getMessage(),
                'user_id' => $this->userId,
            ]);
            session()->flash('error', 'Failed to update custom fields: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.admin.users.user-custom-fields-editor')
            ->layout('components.layouts.app', ['title' => 'Edit User Custom Fields']);
    }
}

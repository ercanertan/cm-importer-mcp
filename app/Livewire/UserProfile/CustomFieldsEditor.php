<?php

namespace App\Livewire\UserProfile;

use App\Models\CmCustomField;
use App\Models\CmCustomFieldValue;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class CustomFieldsEditor extends Component
{
    public $customFields = [];
    public $fieldValues = [];

    public function mount()
    {
        $this->loadCustomFields();
    }

    protected function loadCustomFields()
    {
        // Get only user-editable and active custom fields
        $this->customFields = CmCustomField::userEditable()
            ->orderBy('field_name')
            ->get()
            ->toArray();

        // Load existing values for the authenticated user
        foreach ($this->customFields as $field) {
            $existingValue = CmCustomFieldValue::where('user_id', Auth::id())
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

            if ($field['data_type'] === 'number') {
                $fieldRules[] = 'nullable';
                $fieldRules[] = 'numeric';
            } elseif ($field['data_type'] === 'date') {
                $fieldRules[] = 'nullable';
                $fieldRules[] = 'date';
            } elseif ($field['data_type'] === 'multi_select' && $field['allow_multiple']) {
                // Allow array for multi-select with allow_multiple
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
                    CmCustomFieldValue::where('user_id', Auth::id())
                        ->where('cm_custom_field_id', $field['id'])
                        ->delete();
                } else {
                    // Update or create the value
                    CmCustomFieldValue::updateOrCreate(
                        [
                            'user_id' => Auth::id(),
                            'cm_custom_field_id' => $field['id'],
                        ],
                        [
                            'value' => $value,
                        ]
                    );
                }
            }

            session()->flash('message', 'Custom fields updated successfully.');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to update user custom fields', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);
            session()->flash('error', 'Failed to update custom fields: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.user-profile.custom-fields-editor');
    }
}

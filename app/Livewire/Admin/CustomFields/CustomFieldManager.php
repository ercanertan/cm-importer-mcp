<?php

namespace App\Livewire\Admin\CustomFields;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\CmCustomField;
use App\Enums\CustomFieldTypes;
use Illuminate\Support\Facades\Log;
use App\Services\CampaignMonitorService;
use App\Services\CustomFieldImportService;

class CustomFieldManager extends Component
{
    use WithPagination;

    public $search = '';
    public $showCreateModal = false;
    public $showEditModal = false;
    public $showDeleteModal = false;
    public $showJsonModal = false;
    public $showPreviewModal = false;

    #[\Livewire\Attributes\Locked]
    public $customFieldToEditId = null;

    #[\Livewire\Attributes\Locked]
    public $customFieldToDeleteId = null;

    // JSON Import properties
    public $jsonInput = '';
    public $parsedFields = [];
    public $previewData = [];
    public $selectedPreviewIndices = [];
    public $importErrors = [];

    // Campaign Monitor properties
    public $cmConnectionStatus = [];
    public $isFetchingFromCm = false;

    // Form fields
    public $field_key = '';
    public $field_name = '';
    public $data_type = '';
    public $options = '';
    public $is_active = true;
    public $is_user_editable = false;

    protected $queryString = ['search'];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function render()
    {
        $customFields = CmCustomField::query()
            ->when($this->search, function ($query) {
                $query->where('field_name', 'like', '%' . $this->search . '%')
                    ->orWhere('field_key', 'like', '%' . $this->search . '%');
            })
            ->orderBy('field_name')
            ->paginate(15);

        return view('livewire.admin.custom-fields.custom-field-manager', [
            'customFields' => $customFields
        ])->layout('components.layouts.app', ['title' => 'Custom Fields']);
    }

    public function openCreateModal()
    {
        $this->reset(['field_key', 'field_name', 'data_type', 'options', 'is_active', 'is_user_editable']);
        $this->is_active = true;
        $this->is_user_editable = false;
        $this->data_type = CustomFieldTypes::Text->value;
        $this->showCreateModal = true;
    }

    public function closeCreateModal()
    {
        $this->showCreateModal = false;
        $this->reset(['field_key', 'field_name', 'data_type', 'options', 'is_active', 'is_user_editable']);
        $this->resetValidation();
    }

    public function createCustomField()
    {
        $validTypes = implode(',', array_map(fn($type) => $type->value, CustomFieldTypes::cases()));

        $this->validate([
            'field_key' => 'required|string|max:255|unique:cm_custom_fields,field_key',
            'field_name' => 'required|string|max:255',
            'data_type' => "required|in:{$validTypes}",
            'options' => 'nullable|string',
            'is_active' => 'boolean',
            'is_user_editable' => 'boolean',
        ]);

        try {
            CmCustomField::create([
                'field_key' => $this->field_key,
                'field_name' => $this->field_name,
                'data_type' => $this->data_type,
                'options' => $this->parseOptions($this->options),
                'is_active' => $this->is_active,
                'is_user_editable' => $this->is_user_editable,
            ]);

            session()->flash('message', 'Custom field created successfully.');
            $this->closeCreateModal();
        } catch (\Exception $e) {
            Log::error('Failed to create custom field', [
                'error' => $e->getMessage(),
                'field_key' => $this->field_key
            ]);
            session()->flash('error', 'Failed to create custom field: ' . $e->getMessage());
        }
    }

    public function openEditModal($id)
    {
        $customField = CmCustomField::findOrFail($id);

        $this->field_key = $customField->field_key;
        $this->field_name = $customField->field_name;
        $this->data_type = $customField->data_type->value;
        $this->options = is_array($customField->options) ? implode(', ', $customField->options) : '';
        $this->is_active = $customField->is_active;
        $this->is_user_editable = $customField->is_user_editable;

        $this->customFieldToEditId = $customField->id;
        $this->showEditModal = true;
    }

    public function closeEditModal()
    {
        $this->showEditModal = false;
        $this->customFieldToEditId = null;
        $this->reset(['field_key', 'field_name', 'data_type', 'options', 'is_active', 'is_user_editable']);
        $this->resetValidation();
    }

    public function updateCustomField()
    {
        $validTypes = implode(',', array_map(fn($type) => $type->value, CustomFieldTypes::cases()));

        $this->validate([
            'field_key' => 'required|string|max:255|unique:cm_custom_fields,field_key,' . $this->customFieldToEditId,
            'field_name' => 'required|string|max:255',
            'data_type' => "required|in:{$validTypes}",
            'options' => 'nullable|string',
            'is_active' => 'boolean',
            'is_user_editable' => 'boolean',
        ]);

        try {
            $customField = CmCustomField::findOrFail($this->customFieldToEditId);

            $customField->update([
                'field_key' => $this->field_key,
                'field_name' => $this->field_name,
                'data_type' => $this->data_type,
                'options' => $this->parseOptions($this->options),
                'is_active' => $this->is_active,
                'is_user_editable' => $this->is_user_editable,
            ]);

            session()->flash('message', 'Custom field updated successfully.');
            $this->closeEditModal();
        } catch (\Exception $e) {
            Log::error('Failed to update custom field', [
                'error' => $e->getMessage(),
                'id' => $this->customFieldToEditId
            ]);
            session()->flash('error', 'Failed to update custom field: ' . $e->getMessage());
        }
    }

    public function confirmDelete($id)
    {
        $this->customFieldToDeleteId = $id;
        $this->showDeleteModal = true;
    }

    public function cancelDelete()
    {
        $this->showDeleteModal = false;
        $this->customFieldToDeleteId = null;
    }

    public function deleteCustomField()
    {
        if ($this->customFieldToDeleteId) {
            try {
                $customField = CmCustomField::findOrFail($this->customFieldToDeleteId);
                $fieldName = $customField->field_name;

                // Delete associated custom field values first
                $customField->customFieldValues()->delete();

                // Delete the custom field
                $customField->delete();

                session()->flash('message', "Custom field '{$fieldName}' deleted successfully.");
                $this->cancelDelete();
            } catch (\Exception $e) {
                Log::error('Failed to delete custom field', [
                    'error' => $e->getMessage(),
                    'id' => $this->customFieldToDeleteId
                ]);
                session()->flash('error', 'Failed to delete custom field: ' . $e->getMessage());
            }
        }
    }

    public function toggleActive($id)
    {
        $customField = CmCustomField::findOrFail($id);
        $customField->update(['is_active' => !$customField->is_active]);
        session()->flash('message', 'Field status updated.');
    }

    public function toggleUserEditable($id)
    {
        $customField = CmCustomField::findOrFail($id);
        $customField->update(['is_user_editable' => !$customField->is_user_editable]);
        session()->flash('message', 'User editable status updated.');
    }

    protected function parseOptions($optionsString)
    {
        if (empty($optionsString)) {
            return null;
        }

        // Split by comma and trim whitespace
        $options = array_map('trim', explode(',', $optionsString));
        return array_filter($options);
    }

    // JSON Import Methods
    public function openJsonModal()
    {
        $this->showJsonModal = true;
        $this->jsonInput = '';
        $this->importErrors = [];
        $this->resetValidation('jsonInput');
    }

    public function closeJsonModal()
    {
        $this->showJsonModal = false;
        $this->jsonInput = '';
        $this->importErrors = [];
        $this->resetValidation('jsonInput');
    }

    public function processJsonInput()
    {
        $this->validate([
            'jsonInput' => 'required|string|min:1',
        ]);

        $this->importErrors = [];

        try {
            $importService = new CustomFieldImportService();
            $this->parsedFields = $importService->parseJsonData($this->jsonInput);
            $this->previewData = $importService->generatePreview($this->parsedFields);

            // Auto-select all new and updated fields
            $this->selectedPreviewIndices = [
                ...array_column($this->previewData['new'], 'index'),
                ...array_column($this->previewData['updated'], 'index'),
            ];

            $this->showJsonModal = false;
            $this->showPreviewModal = true;

            $message = "Processed {$this->previewData['statistics']['total']} fields: ";
            $message .= "{$this->previewData['statistics']['new']} new, ";
            $message .= "{$this->previewData['statistics']['updated']} to update, ";
            $message .= "{$this->previewData['statistics']['duplicates']} duplicates, ";
            $message .= "{$this->previewData['statistics']['invalid']} invalid.";

            session()->flash('message', $message);

        } catch (\Exception $e) {
            $this->importErrors[] = $e->getMessage();
            session()->flash('error', $e->getMessage());
        }
    }

    public function closePreviewModal()
    {
        $this->showPreviewModal = false;
        $this->previewData = [];
        $this->selectedPreviewIndices = [];
        $this->importErrors = [];
    }

    public function applyJsonChanges()
    {
        if (empty($this->selectedPreviewIndices)) {
            session()->flash('error', 'Please select at least one field to import.');
            return;
        }

        try {
            $importService = new CustomFieldImportService();
            $results = $importService->applyChanges($this->previewData, $this->selectedPreviewIndices);

            $message = "Import completed: ";
            $message .= "{$results['created']} created, ";
            $message .= "{$results['updated']} updated, ";
            $message .= "{$results['skipped']} skipped.";

            if (!empty($results['errors'])) {
                $message .= " " . count($results['errors']) . " errors occurred.";
                Log::error('JSON import errors', $results['errors']);
            }

            session()->flash('message', $message);
            $this->closePreviewModal();

        } catch (\Exception $e) {
            session()->flash('error', 'Failed to apply changes: ' . $e->getMessage());
            Log::error('JSON import failed', [
                'error' => $e->getMessage(),
                'selected_indices' => $this->selectedPreviewIndices,
            ]);
        }
    }

    public function togglePreviewSelection($index)
    {
        if (in_array($index, $this->selectedPreviewIndices)) {
            $this->selectedPreviewIndices = array_diff($this->selectedPreviewIndices, [$index]);
        } else {
            $this->selectedPreviewIndices[] = $index;
        }
    }

    public function selectAllValidFields()
    {
        $this->selectedPreviewIndices = [
            ...array_column($this->previewData['new'], 'index'),
            ...array_column($this->previewData['updated'], 'index'),
        ];
    }

    public function clearSelection()
    {
        $this->selectedPreviewIndices = [];
    }

    public function getExampleJson()
    {
        $importService = new CustomFieldImportService();
        return $importService->getExampleFormat();
    }

    // Campaign Monitor Methods
    public function mount()
    {
        $this->checkCampaignMonitorConnection();
    }

    public function checkCampaignMonitorConnection()
    {
        try {
            $cmService = new CampaignMonitorService();
            $this->cmConnectionStatus = $cmService->getConnectionStatus();
        } catch (\Exception $e) {
            $this->cmConnectionStatus = [
                'configured' => false,
                'connected' => false,
                'error' => $e->getMessage(),
                'client_name' => null,
            ];
        }
    }

    public function fetchFromCampaignMonitor()
    {
        $this->isFetchingFromCm = true;

        try {
            $cmService = new CampaignMonitorService();
            $jsonData = $cmService->getCustomFieldsForImport();

            // Set the JSON input and process it
            $this->jsonInput = $jsonData;
            $this->processJsonInput();

            // Update connection status
            $this->checkCampaignMonitorConnection();

            session()->flash('message', 'Successfully fetched custom fields from Campaign Monitor!');

        } catch (\Exception $e) {
            session()->flash('error', 'Failed to fetch custom fields from Campaign Monitor: ' . $e->getMessage());
            Log::error('Campaign Monitor fetch failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        } finally {
            $this->isFetchingFromCm = false;
        }
    }

    public function openCampaignMonitorModal()
    {
        // Refresh connection status before opening modal
        $this->checkCampaignMonitorConnection();

        // If connected, automatically fetch fields
        if ($this->cmConnectionStatus['connected']) {
            $this->fetchFromCampaignMonitor();
        } else {
            // Show error about connection
            $error = $this->cmConnectionStatus['error'] ?? 'Unknown error';
            session()->flash('error', "Cannot connect to Campaign Monitor: {$error}");
        }
    }
}

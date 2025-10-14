<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CampaignMonitorImportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $maxUploadSize = config('campaign-monitor.max_upload_size', 10240);

        return [
            'csv_file' => [
                'required',
                'file',
                'mimes:csv,txt',
                "max:{$maxUploadSize}",
                function ($attribute, $value, $fail) {
                    if ($value && $value->isValid()) {
                        $handle = fopen($value->getPathname(), 'r');
                        if ($handle) {
                            $headers = fgetcsv($handle);
                            fclose($handle);

                            $emailColumnNames = ['email', 'Email Address', 'email address', 'Email', 'EMAIL', 'EMAIL ADDRESS'];
                            $hasEmailColumn = false;

                            if ($headers) {
                                foreach ($headers as $header) {
                                    if (in_array(trim($header), $emailColumnNames)) {
                                        $hasEmailColumn = true;
                                        break;
                                    }
                                }
                            }

                            if (!$hasEmailColumn) {
                                $fail('The CSV file must contain an email column (email, Email Address, etc.).');
                            }
                        } else {
                            $fail('Unable to read the CSV file.');
                        }
                    }
                }
            ]
        ];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $maxUploadSizeMB = config('campaign-monitor.max_upload_size', 10240) / 1024;

        return [
            'csv_file.required' => 'Please select a CSV file to upload.',
            'csv_file.file' => 'The uploaded file must be a valid file.',
            'csv_file.mimes' => 'The file must be a CSV file (.csv or .txt).',
            'csv_file.max' => "The file size must not exceed {$maxUploadSizeMB}MB.",
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'csv_file' => 'CSV file',
        ];
    }
}

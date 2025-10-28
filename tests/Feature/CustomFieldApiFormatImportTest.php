<?php

use App\Services\CustomFieldImportService;

beforeEach(function () {
    $this->service = app(CustomFieldImportService::class);
});

it('can parse api format json', function () {
    $apiJson = json_encode([
        'response' => [
            [
                'FieldName' => 'Job Title',
                'Key' => '[JobTitle]',
                'DataType' => 'Text',
                'FieldOptions' => [],
                'VisibleInPreferenceCenter' => true,
            ],
            [
                'FieldName' => 'Department',
                'Key' => '[Department]',
                'DataType' => 'MultiSelectOne',
                'FieldOptions' => ['Engineering', 'Sales', 'Marketing'],
                'VisibleInPreferenceCenter' => false,
            ],
        ],
        'http_status_code' => 200
    ]);

    $result = $this->service->parseJsonData($apiJson);

    expect($result)->toHaveCount(2);
    expect($result[0]['FieldName'])->toBe('Job Title');
    expect($result[0]['Key'])->toBe('[JobTitle]');
    expect($result[1]['FieldName'])->toBe('Department');
    expect($result[1]['Key'])->toBe('[Department]');
});

it('can parse manual format json', function () {
    $manualJson = json_encode([
        [
            'FieldName' => 'Job Title',
            'Key' => '[JobTitle]',
            'DataType' => 'Text',
            'FieldOptions' => [],
            'VisibleInPreferenceCenter' => true,
        ],
        [
            'FieldName' => 'Department',
            'Key' => '[Department]',
            'DataType' => 'MultiSelectOne',
            'FieldOptions' => ['Engineering', 'Sales', 'Marketing'],
            'VisibleInPreferenceCenter' => false,
        ],
    ]);

    $result = $this->service->parseJsonData($manualJson);

    expect($result)->toHaveCount(2);
    expect($result[0]['FieldName'])->toBe('Job Title');
    expect($result[0]['Key'])->toBe('[JobTitle]');
    expect($result[1]['FieldName'])->toBe('Department');
    expect($result[1]['Key'])->toBe('[Department]');
});

it('throws exception for api format with non 200 status', function () {
    $apiJson = json_encode([
        'response' => [
            [
                'FieldName' => 'Job Title',
                'Key' => '[JobTitle]',
                'DataType' => 'Text',
                'FieldOptions' => [],
                'VisibleInPreferenceCenter' => true,
            ],
        ],
        'http_status_code' => 400
    ]);

    $this->service->parseJsonData($apiJson);
})->throws(\InvalidArgumentException::class, 'API returned non-200 status code: 400');

it('throws exception for api format with empty response', function () {
    $apiJson = json_encode([
        'response' => [],
        'http_status_code' => 200
    ]);

    $this->service->parseJsonData($apiJson);
})->throws(\InvalidArgumentException::class, 'JSON must contain a non-empty array of field objects');

it('provides api example format', function () {
    $example = $this->service->getApiExampleFormat();

    $data = json_decode($example, true);

    expect($data)->toHaveKey('response');
    expect($data)->toHaveKey('http_status_code');
    expect($data['http_status_code'])->toBe(200);
    expect($data['response'])->toBeArray();
    expect($data['response'])->not->toBeEmpty();
});

it('can generate preview with api format', function () {
    $apiJson = json_encode([
        'response' => [
            [
                'FieldName' => 'New Field',
                'Key' => '[NewField]',
                'DataType' => 'Text',
                'FieldOptions' => [],
                'VisibleInPreferenceCenter' => true,
            ],
        ],
        'http_status_code' => 200
    ]);

    $fields = $this->service->parseJsonData($apiJson);
    $preview = $this->service->generatePreview($fields);

    expect($preview['new'])->toHaveCount(1);
    expect($preview['new'][0]['external']['field_name'])->toBe('New Field');
    expect($preview['new'][0]['external']['field_key'])->toBe('NewField');
});
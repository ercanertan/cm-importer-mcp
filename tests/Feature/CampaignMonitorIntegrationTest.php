<?php

use App\Services\CampaignMonitorService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('provides correct status when not configured', function () {
    config(['app.campign_monitor.cm_api_key' => null]);
    config(['app.campign_monitor.client_id' => null]);

    $service = new CampaignMonitorService();
    $status = $service->getConnectionStatus();

    expect($status['configured'])->toBeFalse();
    expect($status['connected'])->toBeFalse();
    expect($status['error'])->toContain('Missing configuration');
    expect($status['client_name'])->toBeNull();
});

it('provides configured status when credentials exist', function () {
    config(['app.campign_monitor.cm_api_key' => 'test-api-key']);
    config(['app.campign_monitor.list_id' => 'test-list-id']);

    $service = new CampaignMonitorService();
    $status = $service->getConnectionStatus();

    expect($status['configured'])->toBeTrue();
    // Service should not throw exception for missing configuration
    expect(is_null($status['error']) || !str_contains($status['error'], 'Missing configuration'))->toBeTrue();
});

it('handles missing api key gracefully', function () {
    config(['app.campign_monitor.cm_api_key' => null]);
    config(['app.campign_monitor.list_id' => 'some-list']);

    $service = new CampaignMonitorService();
    $status = $service->getConnectionStatus();

    expect($status['configured'])->toBeFalse();
    expect($status['error'])->toContain('Missing configuration');
});

it('provides proper error for partial configuration', function () {
    config(['app.campign_monitor.cm_api_key' => null]);
    config(['app.campign_monitor.list_id' => 'some-list-id']);

    $service = new CampaignMonitorService();
    $status = $service->getConnectionStatus();

    expect($status['configured'])->toBeFalse();
    expect($status['error'])->toContain('Missing configuration');
    expect($status['error'])->toContain('API Key');
});

it('provides proper error for missing list id', function () {
    config(['app.campign_monitor.cm_api_key' => 'test-api-key']);
    config(['app.campign_monitor.list_id' => null]);

    $service = new CampaignMonitorService();
    $status = $service->getConnectionStatus();

    expect($status['configured'])->toBeFalse();
    expect($status['error'])->toContain('Missing configuration');
    expect($status['error'])->toContain('List ID');
});

it('can format sample campaign monitor data', function () {
    config(['app.campign_monitor.cm_api_key' => 'test-key']);
    config(['app.campign_monitor.list_id' => 'test-list']);

    $service = new CampaignMonitorService();

    // Use reflection to test private method
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('formatCustomFields');
    $method->setAccessible(true);

    $sampleFields = [
        (object)[
            'FieldName' => 'Job Title',
            'Key' => '[JobTitle]',
            'DataType' => 'Text',
            'FieldOptions' => [],
            'VisibleInPreferenceCenter' => true,
        ],
    ];

    $formatted = $method->invoke($service, $sampleFields);

    expect($formatted)->toHaveCount(1);
    expect($formatted[0])->toMatchArray([
        'FieldName' => 'Job Title',
        'Key' => '[JobTitle]',
        'DataType' => 'Text',
        'FieldOptions' => [],
        'VisibleInPreferenceCenter' => true,
    ]);
});
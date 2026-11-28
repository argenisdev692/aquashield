<?php

declare(strict_types=1);

it('documents availability query parameters and typed calendar windows', function (): void {
    $document = json_decode(
        (string) file_get_contents(base_path('api.json')),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    $calendar = $document['paths']['/v1/availability/calendar']['get'];
    $parameters = array_column($calendar['parameters'], 'name');

    expect($parameters)->toContain('from', 'to', 'region')
        ->and($calendar['responses'])->toHaveKey('403')
        ->and($calendar['responses'])->toHaveKey('422');

    $window = $document['components']['schemas']['AvailabilityDayData']['properties']['windows']['items'];

    expect($window['$ref'])->toBe('#/components/schemas/AvailabilityWindowData');

    $schema = $document['components']['schemas']['AvailabilityWindowData'];

    expect($schema['type'])->toBe('object')
        ->and($schema['properties'])->toHaveKeys(['start', 'end']);
});

it('documents availability export media types and authorization failure', function (): void {
    $document = json_decode(
        (string) file_get_contents(base_path('api.json')),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    $export = $document['paths']['/v1/availability/export/{format}']['get'];
    $content = $export['responses'][200]['content'];

    expect($content)->toHaveKeys([
        'text/csv',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/pdf',
    ])
        ->and($export['responses'])->toHaveKey('403')
        ->and(array_column($export['parameters'], 'name'))->toContain('filter[source]', 'per_page');
});

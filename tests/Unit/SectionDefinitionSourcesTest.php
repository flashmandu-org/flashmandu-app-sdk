<?php

use Flashmandu\AppSdk\Storefront\SectionDefinition;
use Flashmandu\AppSdk\Storefront\SectionSchema;

it('defaults source to blueprint when unspecified', function (): void {
    $section = new SectionDefinition(
        type: 'example-app:hero',
        name: 'Hero',
        elementTree: [['type' => 'heading', 'text' => 'Hi']],
    );

    expect($section->source)->toBe(SectionDefinition::SOURCE_BLUEPRINT);
});

it('rejects an unrecognised source, naming the offending value', function (): void {
    expect(fn () => new SectionDefinition(type: 'example-app:hero', name: 'Hero', source: 'ftp'))
        ->toThrow(InvalidArgumentException::class, "section source 'ftp' is not one of: blueprint, static, endpoint");
});

it('requires element_tree for a blueprint-source section, naming the field', function (): void {
    expect(fn () => new SectionDefinition(type: 'example-app:hero', name: 'Hero', source: SectionDefinition::SOURCE_BLUEPRINT))
        ->toThrow(InvalidArgumentException::class, "section source 'blueprint' requires 'element_tree' (elementTree)");
});

it('lets a legacy view-rendered section satisfy the blueprint tier without an element_tree', function (): void {
    $section = new SectionDefinition(
        type: 'example-app:hero',
        name: 'Hero',
        view: 'example-app::sections.hero',
    );

    expect($section->source)->toBe(SectionDefinition::SOURCE_BLUEPRINT)
        ->and($section->elementTree)->toBeNull()
        ->and($section->view)->toBe('example-app::sections.hero');
});

it('accepts a blueprint-source section that declares an element_tree', function (): void {
    $section = new SectionDefinition(
        type: 'example-app:hero',
        name: 'Hero',
        source: SectionDefinition::SOURCE_BLUEPRINT,
        elementTree: [['type' => 'heading', 'text' => 'Hi']],
    );

    expect($section->elementTree)->toBe([['type' => 'heading', 'text' => 'Hi']]);
});

it('accepts a static-source section without element_tree or endpoint_path', function (): void {
    $section = new SectionDefinition(
        type: 'example-app:hero',
        name: 'Hero',
        source: SectionDefinition::SOURCE_STATIC,
    );

    expect($section->source)->toBe(SectionDefinition::SOURCE_STATIC)
        ->and($section->elementTree)->toBeNull();
});

it('requires endpoint_path for an endpoint-source section, naming the field', function (): void {
    expect(fn () => new SectionDefinition(type: 'example-app:events', name: 'Events', source: SectionDefinition::SOURCE_ENDPOINT))
        ->toThrow(InvalidArgumentException::class, "section source 'endpoint' requires 'endpoint_path' (endpointPath)");
});

it('rejects an empty-string endpoint_path for an endpoint-source section', function (): void {
    expect(fn () => new SectionDefinition(
        type: 'example-app:events',
        name: 'Events',
        source: SectionDefinition::SOURCE_ENDPOINT,
        endpointPath: '',
    ))->toThrow(InvalidArgumentException::class, "section source 'endpoint' requires 'endpoint_path' (endpointPath)");
});

it('accepts an endpoint-source section that declares endpoint_path', function (): void {
    $section = new SectionDefinition(
        type: 'example-app:events',
        name: 'Events',
        source: SectionDefinition::SOURCE_ENDPOINT,
        endpointPath: '/sections/events',
    );

    expect($section->source)->toBe(SectionDefinition::SOURCE_ENDPOINT)
        ->and($section->endpointPath)->toBe('/sections/events');
});

it('defaults settings and bindings to empty/null when absent', function (): void {
    $section = new SectionDefinition(
        type: 'example-app:hero',
        name: 'Hero',
        elementTree: [['type' => 'heading', 'text' => 'Hi']],
    );

    expect($section->settings)->toBe([])
        ->and($section->bindings)->toBeNull();
});

it('carries a settings schema in the same shape as embed_blocks.settings', function (): void {
    $settings = [
        ['name' => 'months_ahead', 'label' => 'Months to show', 'type' => 'number', 'default' => 2],
    ];

    $section = new SectionDefinition(
        type: 'example-app:events-calendar',
        name: 'Events Calendar',
        elementTree: [['type' => 'heading', 'text' => 'Events']],
        settings: $settings,
    );

    expect($section->settings)->toBe($settings);
});

it('carries a module/collection binding with a dotted relation path', function (): void {
    $bindings = [
        'module' => 'events',
        'filter' => ['entry.venue.name' => ['eq' => 'Main Hall']],
        'order' => ['entry.date' => 'asc'],
        'limit' => 50,
        'group_by' => ['field' => 'entry.date', 'granularity' => 'month'],
    ];

    $section = new SectionDefinition(
        type: 'example-app:events-calendar',
        name: 'Events Calendar',
        elementTree: [['type' => 'heading', 'text' => 'Events']],
        bindings: $bindings,
    );

    expect($section->bindings)->toBe($bindings)
        ->and($section->bindings['filter'])->toHaveKey('entry.venue.name')
        ->and($section->bindings['group_by']['field'])->toBe('entry.date');
});

it('round-trips settings, bindings, source and endpoint_path through fromArray()/toArray()', function (): void {
    $payload = [
        'type' => 'example-app:events-calendar',
        'name' => 'Events Calendar',
        'source' => SectionDefinition::SOURCE_BLUEPRINT,
        'element_tree' => [['type' => 'heading', 'text' => 'Events']],
        'settings' => [
            ['name' => 'months_ahead', 'label' => 'Months to show', 'type' => 'number', 'default' => 2],
        ],
        'bindings' => [
            'module' => 'events',
            'order' => ['entry.date' => 'asc'],
            'group_by' => ['field' => 'entry.date', 'granularity' => 'month'],
        ],
        'endpoint_path' => null,
        'category' => 'Content',
        'view' => null,
        'schema_version' => SectionSchema::CURRENT,
    ];

    $section = SectionDefinition::fromArray($payload);

    expect($section->toArray())->toBe($payload);
});

it('round-trips a dotted relation-path binding through fromArray()/toArray() unchanged', function (): void {
    $payload = [
        'type' => 'example-app:events-calendar',
        'name' => 'Events Calendar',
        'source' => SectionDefinition::SOURCE_BLUEPRINT,
        'element_tree' => [['type' => 'heading', 'text' => 'Events']],
        'settings' => [],
        'bindings' => [
            'module' => 'events',
            'filter' => [
                'entry.venue.name' => ['eq' => 'Main Hall'],
                'entry.ticket_product.price' => ['lte' => 5000],
            ],
        ],
        'endpoint_path' => null,
        'category' => null,
        'view' => null,
        'schema_version' => SectionSchema::CURRENT,
    ];

    $section = SectionDefinition::fromArray($payload);

    expect($section->bindings['filter'])->toHaveKeys(['entry.venue.name', 'entry.ticket_product.price'])
        ->and($section->toArray())->toBe($payload);
});

it('round-trips an endpoint-source section through fromArray()/toArray()', function (): void {
    $payload = [
        'type' => 'example-app:events',
        'name' => 'Events',
        'source' => SectionDefinition::SOURCE_ENDPOINT,
        'element_tree' => null,
        'settings' => [],
        'bindings' => null,
        'endpoint_path' => '/sections/events',
        'category' => null,
        'view' => null,
        'schema_version' => SectionSchema::CURRENT,
    ];

    $section = SectionDefinition::fromArray($payload);

    expect($section->source)->toBe(SectionDefinition::SOURCE_ENDPOINT)
        ->and($section->endpointPath)->toBe('/sections/events')
        ->and($section->toArray())->toBe($payload);
});

it('defaults source to blueprint from fromArray() when absent, matching the constructor default', function (): void {
    $section = SectionDefinition::fromArray([
        'type' => 'example-app:hero',
        'name' => 'Hero',
        'element_tree' => [['type' => 'heading', 'text' => 'Hi']],
    ]);

    expect($section->source)->toBe(SectionDefinition::SOURCE_BLUEPRINT);
});

it('still throws from fromArray() for an unsupported future schema_version even with source/bindings present', function (): void {
    expect(fn () => SectionDefinition::fromArray([
        'type' => 'example-app:hero',
        'name' => 'Hero',
        'source' => SectionDefinition::SOURCE_STATIC,
        'bindings' => ['module' => 'events'],
        'schema_version' => 2,
    ]))->toThrow(RuntimeException::class, 'section schema_version 2 is newer than this platform supports (1)');
});

it('rejects an unsupported source from fromArray(), naming the offending value', function (): void {
    expect(fn () => SectionDefinition::fromArray([
        'type' => 'example-app:hero',
        'name' => 'Hero',
        'source' => 'ftp',
    ]))->toThrow(InvalidArgumentException::class, "section source 'ftp' is not one of: blueprint, static, endpoint");
});

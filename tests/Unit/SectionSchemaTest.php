<?php

use Flashmandu\AppSdk\Storefront\SectionDefinition;
use Flashmandu\AppSdk\Storefront\SectionSchema;

it('defaults schemaVersion to CURRENT when unspecified', function (): void {
    $section = new SectionDefinition(type: 'example-app:hero', name: 'Hero', elementTree: [['type' => 'heading', 'text' => 'Hi']]);

    expect($section->schemaVersion)->toBe(SectionSchema::CURRENT)
        ->and(SectionSchema::CURRENT)->toBe(1);
});

it('accepts an explicit, supported schema version', function (): void {
    $section = new SectionDefinition(type: 'example-app:hero', name: 'Hero', elementTree: [['type' => 'heading', 'text' => 'Hi']], schemaVersion: 1);

    expect($section->schemaVersion)->toBe(1);
});

it('throws a clear, actionable message when the declared schema version is newer than the platform supports', function (): void {
    expect(fn () => new SectionDefinition(type: 'example-app:hero', name: 'Hero', elementTree: [['type' => 'heading', 'text' => 'Hi']], schemaVersion: 2))
        ->toThrow(RuntimeException::class, 'section schema_version 2 is newer than this platform supports (1)');
});

it('still constructs a definition built the old way, with no version argument, unchanged', function (): void {
    $section = new SectionDefinition(
        type: 'example-app:hero',
        name: 'Hero',
        elementTree: [['type' => 'heading', 'text' => 'Hi']],
        category: 'Marketing',
        view: 'example-app::sections.hero',
    );

    expect($section->type)->toBe('example-app:hero')
        ->and($section->name)->toBe('Hero')
        ->and($section->elementTree)->toBe([['type' => 'heading', 'text' => 'Hi']])
        ->and($section->category)->toBe('Marketing')
        ->and($section->view)->toBe('example-app::sections.hero')
        ->and($section->schemaVersion)->toBe(SectionSchema::CURRENT);
});

it('directly asserts SectionSchema::assertSupported() throws with both numbers named', function (): void {
    expect(fn () => SectionSchema::assertSupported(5))
        ->toThrow(RuntimeException::class, 'section schema_version 5 is newer than this platform supports (1)');

    expect(fn () => SectionSchema::assertSupported(1))->not->toThrow(RuntimeException::class);
});

it('round-trips a section definition through fromArray()/toArray(), defaulting schema_version when absent', function (): void {
    $section = SectionDefinition::fromArray([
        'type' => 'example-app:hero',
        'name' => 'Hero',
        'element_tree' => [['type' => 'heading', 'text' => 'Hi']],
        'category' => 'Marketing',
        'view' => 'example-app::sections.hero',
    ]);

    expect($section->schemaVersion)->toBe(SectionSchema::CURRENT)
        ->and($section->toArray())->toBe([
            'type' => 'example-app:hero',
            'name' => 'Hero',
            'source' => SectionDefinition::SOURCE_BLUEPRINT,
            'element_tree' => [['type' => 'heading', 'text' => 'Hi']],
            'settings' => [],
            'bindings' => null,
            'endpoint_path' => null,
            'category' => 'Marketing',
            'view' => 'example-app::sections.hero',
            'schema_version' => SectionSchema::CURRENT,
        ]);
});

it('throws from fromArray() when the manifest declares an unsupported future schema_version', function (): void {
    expect(fn () => SectionDefinition::fromArray([
        'type' => 'example-app:hero',
        'name' => 'Hero',
        'schema_version' => 2,
    ]))->toThrow(RuntimeException::class, 'section schema_version 2 is newer than this platform supports (1)');
});

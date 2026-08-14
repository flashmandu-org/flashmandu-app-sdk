<?php

use Flashmandu\AppSdk\Hooks\HookName;

it('exposes every Phase 1 event name', function (string $value): void {
    expect(HookName::tryFrom($value))->not->toBeNull();
})->with([
    'order.created',
    'order.updated',
    'customer.created',
    'customer.updated',
    'customer.deleted',
    'item.created',
    'item.updated',
    'item.deleted',
    'price.changed',
    'inventory.level.changed',
    'cms.entry.published',
    'cms.entry.updated',
    'cms.entry.unpublished',
    'cms.entry.deleted',
    'media.uploaded',
    'employee.created',
    'employee.updated',
    'employee.archived',
    'app.uninstalled',
    'app.scopes.updated',
    'profile.data.erased',
    'schedule.tick',
]);

it('keeps every pre-existing case, so already-shipped apps still compile', function (string $value): void {
    expect(HookName::tryFrom($value))->not->toBeNull();
})->with([
    'order.status.changed',
    'order.item.cancelled',
    'order.item.quantity.changed',
    'party.balance.changed',
    'account.balance.changed',
    'new.in.app.notification',
    'new.message.sent',
    'service.location.status.changed',
    'call.initiated',
    'call.ended',
    'cart.totals',
]);

it('describes every case', function (): void {
    foreach (HookName::cases() as $case) {
        expect($case->description())->not->toBe('');
    }
});

it('gives every case a distinct description', function (): void {
    $descriptions = array_map(
        static fn (HookName $case): string => $case->description(),
        HookName::cases(),
    );

    expect(array_unique($descriptions))->toHaveCount(count($descriptions));
});

it('treats only cart.totals as a filter', function (): void {
    $filters = array_values(array_filter(
        HookName::cases(),
        static fn (HookName $case): bool => $case->isFilter(),
    ));

    expect($filters)->toBe([HookName::CartTotals]);
});

it('marks exactly the three lifecycle events as mandatory-delivery', function (): void {
    $mandatory = array_map(
        static fn (HookName $case): string => $case->value,
        array_values(array_filter(
            HookName::cases(),
            static fn (HookName $case): bool => $case->isMandatory(),
        )),
    );

    expect($mandatory)->toBe(['app.uninstalled', 'app.scopes.updated', 'profile.data.erased']);
});

it('never lets a case be both a filter and mandatory', function (): void {
    foreach (HookName::cases() as $case) {
        expect($case->isFilter() && $case->isMandatory())->toBeFalse();
    }
});

it('uses dot-style domain.resource.verb names throughout', function (): void {
    foreach (HookName::cases() as $case) {
        expect($case->value)->toMatch('/^[a-z]+(\.[a-z]+)+$/');
    }
});

it('marks schedule.tick as declaration-driven, and nothing else', function (): void {
    $declarationDriven = array_map(
        static fn (HookName $case): string => $case->value,
        array_values(array_filter(
            HookName::cases(),
            static fn (HookName $case): bool => $case->isDeclarationDriven(),
        )),
    );

    expect($declarationDriven)->toBe(['schedule.tick']);
});

it('keeps declaration-driven and mandatory as distinct species', function (): void {
    // A mandatory hook arrives whether or not the app declared anything; a
    // declaration-driven one arrives only because it did. Nothing is both.
    foreach (HookName::cases() as $case) {
        expect($case->isMandatory() && $case->isDeclarationDriven())->toBeFalse();
    }

    expect(HookName::ScheduleTick->isMandatory())->toBeFalse()
        ->and(HookName::AppUninstalled->isDeclarationDriven())->toBeFalse();
});

<?php

use Flashmandu\AppSdk\Admin\Breadcrumb;
use Flashmandu\AppSdk\Admin\PageAction;
use Flashmandu\AppSdk\Admin\PageChrome;

it('defaults to empty chrome that is not dirty', function (): void {
    $chrome = new PageChrome;

    expect($chrome->crumbs)->toBe([])
        ->and($chrome->actions)->toBe([])
        ->and($chrome->dirty)->toBeFalse()
        ->and($chrome->current())->toBeNull();
});

it('composes a breadcrumb trail whose last entry is the current page', function (): void {
    $chrome = new PageChrome(crumbs: [
        new Breadcrumb('Dashboard', 'my-app.dashboard'),
        new Breadcrumb('Items'),
    ]);

    expect($chrome->current()?->label)->toBe('Items')
        ->and($chrome->current()?->route)->toBeNull()
        ->and($chrome->crumbs[0]->route)->toBe('my-app.dashboard');
});

it('carries command-bar actions and finds them by the id the host echoes back', function (): void {
    $chrome = new PageChrome(actions: [
        new PageAction(id: 'save', label: 'Save', variant: 'primary', icon: 'check'),
        new PageAction(id: 'export', label: 'Export'),
    ]);

    expect($chrome->action('save')?->label)->toBe('Save')
        ->and($chrome->action('save')?->icon)->toBe('check')
        ->and($chrome->action('missing'))->toBeNull();
});

it('defaults an action to the ghost variant with no icon', function (): void {
    $action = new PageAction(id: 'export', label: 'Export');

    expect($action->variant)->toBe('ghost')
        ->and($action->icon)->toBeNull();
});

it('reports a dirty page so the host can swap in native Save/Discard', function (): void {
    expect((new PageChrome(dirty: true))->dirty)->toBeTrue();
});

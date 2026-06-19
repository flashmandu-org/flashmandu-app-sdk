<?php

use Flashmandu\AppSdk\Admin\AdminUI;
use Flashmandu\AppSdk\Admin\MenuItem;
use Flashmandu\AppSdk\AppContext;
use Flashmandu\AppSdk\AppHooks;
use Flashmandu\AppSdk\AppManifest;
use Flashmandu\AppSdk\HookRegistrar;
use Flashmandu\AppSdk\Installable;
use Flashmandu\AppSdk\InstallContext;
use Flashmandu\AppSdk\Scope;
use Flashmandu\AppSdk\Storefront\SectionDefinition;
use Flashmandu\AppSdk\Storefront\Storefront;

it('builds a manifest with scopes and extension points', function (): void {
    $manifest = new AppManifest(
        id: 'flashmandu/example-app',
        name: 'Example App',
        version: '1.0.0',
        scopes: [Scope::ReadOrders],
    );

    expect($manifest->id)->toBe('flashmandu/example-app')
        ->and($manifest->scopes)->toBe([Scope::ReadOrders])
        ->and($manifest->hooks)->toBeNull()
        ->and($manifest->admin)->toBeNull()
        ->and($manifest->storefront)->toBeNull();
});

it('exposes scope cases as verb:resource strings', function (): void {
    expect(Scope::ReadOrders->value)->toBe('read:orders')
        ->and(Scope::ManagePayments->value)->toBe('manage:payments');
});

it('checks granted scopes on an AppContext', function (): void {
    $ctx = new AppContext(profile: null, appId: 'a', scopes: [Scope::ReadOrders]);

    expect($ctx->hasScope(Scope::ReadOrders))->toBeTrue()
        ->and($ctx->hasScope(Scope::WriteOrders))->toBeFalse()
        ->and($ctx->profileId())->toBeNull();
});

it('resolves a profile id from the opaque profile object', function (): void {
    $profile = new stdClass;
    $profile->id = 42;

    $ctx = new AppContext(profile: $profile, appId: 'a');

    expect($ctx->profileId())->toBe(42);
});

it('composes admin UI from menu items', function (): void {
    $ui = new AdminUI(menu: [
        new MenuItem(label: 'Settings', route: 'example-app.settings', icon: 'cog'),
    ]);

    expect($ui->menu)->toHaveCount(1)
        ->and($ui->menu[0]->label)->toBe('Settings')
        ->and($ui->menu[0]->route)->toBe('example-app.settings')
        ->and($ui->menu[0]->icon)->toBe('cog');
});

it('composes storefront from section definitions', function (): void {
    $sf = new Storefront(sections: [
        new SectionDefinition(type: 'example-app:hero', name: 'Hero', view: 'example-app::sections.hero'),
    ]);

    expect($sf->sections)->toHaveCount(1)
        ->and($sf->sections[0]->type)->toBe('example-app:hero')
        ->and($sf->sections[0]->view)->toBe('example-app::sections.hero');
});

it('lets an app declare hooks through a registrar', function (): void {
    $registrar = new class implements HookRegistrar
    {
        /** @var array<string, bool> */
        public array $on = [];

        /** @var array<string, bool> */
        public array $filter = [];

        public function on(string $event, callable $listener): void
        {
            $this->on[$event] = true;
        }

        public function filter(string $hook, callable $mutator): void
        {
            $this->filter[$hook] = true;
        }
    };

    $app = new class implements AppHooks
    {
        public function register(HookRegistrar $registrar): void
        {
            $registrar->on('order.status.changed', fn (array $p) => null);
            $registrar->filter('cart.totals', fn (mixed $v) => $v);
        }
    };

    $app->register($registrar);

    expect($registrar->on)->toBe(['order.status.changed' => true])
        ->and($registrar->filter)->toBe(['cart.totals' => true]);
});

it('supports an installable lifecycle implementation', function (): void {
    $log = [];
    $app = new class($log) implements Installable
    {
        public function __construct(private array &$log) {}

        public function onInstall(InstallContext $context): void
        {
            $this->log[] = 'install:'.$context->appId;
        }

        public function onUninstall(InstallContext $context): void
        {
            $this->log[] = 'uninstall:'.$context->appId;
        }

        /** @return list<string> */
        public function recorded(): array
        {
            return $this->log;
        }
    };

    $app->onInstall(new InstallContext(profile: null, appId: 'a', scopes: []));
    $app->onUninstall(new InstallContext(profile: null, appId: 'a', scopes: []));

    expect($app->recorded())->toBe(['install:a', 'uninstall:a']);
});

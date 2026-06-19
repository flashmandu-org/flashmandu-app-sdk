<?php

use Flashmandu\AppSdk\AppContext;
use Flashmandu\AppSdk\AppHooks;
use Flashmandu\AppSdk\AppManifest;
use Flashmandu\AppSdk\AppProvider;
use Flashmandu\AppSdk\HookRegistrar;
use Flashmandu\AppSdk\Scope;
use Flashmandu\AppSdk\Testing\AppTester;

// Simulates a real third-party app the way a developer would write one.
class DemoAppHooks implements AppHooks
{
    /** @var list<string> */
    public array $statuses = [];

    public function register(HookRegistrar $registrar): void
    {
        $registrar->on('order.status.changed', function (array $payload, AppContext $context): void {
            $this->statuses[] = (string) ($payload['status'] ?? 'unknown');
        });
        $registrar->filter('cart.totals', fn (int $value, AppContext $context): int => $value + 10);
    }
}

class DemoAppProvider implements AppProvider
{
    public function __construct(
        private ?AppHooks $hooks = null,
    ) {}

    public function manifest(): AppManifest
    {
        return new AppManifest(
            id: 'demo/app',
            name: 'Demo',
            version: '1.0.0',
            scopes: [Scope::ReadOrders],
            hooks: $this->hooks ?? new DemoAppHooks,
        );
    }
}

it('does not fire hooks before the app is installed (matches platform gating)', function (): void {
    $hooks = new DemoAppHooks;
    $tester = AppTester::for(new DemoAppProvider($hooks));

    $tester->emit('order.status.changed', ['status' => 'paid']);

    expect($tester->wasFired('order.status.changed'))->toBeFalse()
        ->and($tester->isInstalled())->toBeFalse()
        ->and($hooks->statuses)->toBeEmpty();
});

it('fires the app hook after install, locally with no platform', function (): void {
    $hooks = new DemoAppHooks;
    $tester = AppTester::for(new DemoAppProvider($hooks));

    $tester->install()->emit('order.status.changed', ['status' => 'paid']);

    expect($tester->isInstalled())->toBeTrue()
        ->and($tester->wasFired('order.status.changed'))->toBeTrue()
        ->and($tester->fired('order.status.changed'))->toBe(1)
        ->and($hooks->statuses)->toBe(['paid']);
});

it('pipes a value through the app filters locally', function (): void {
    $tester = AppTester::for(DemoAppProvider::class)->install();

    expect($tester->runFilter('cart.totals', 100))->toBe(110);
});

it('surfaces the app manifest for assertions', function (): void {
    $tester = AppTester::for(DemoAppProvider::class);

    expect($tester->manifest()->id)->toBe('demo/app')
        ->and($tester->manifest()->scopes)->toBe([Scope::ReadOrders]);
});

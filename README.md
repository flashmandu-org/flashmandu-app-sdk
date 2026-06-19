# Flashmandu App SDK

The public SDK for building **apps/plugins** that extend a Flashmandu storefront
platform. Third-party developers code against **this package only** — they never
receive the platform's core source (payments, billing, models). That secrecy is
by construction: the SDK exposes contracts; the platform keeps its implementation.

> This package is intentionally dependency-light (PHP 8.2+, contracts only) so it
> is fully standalone and independently versionable.

## Install (app developer)

```bash
composer require flashmandu/app-sdk
```

## Build an app

An app is any autoloaded class implementing `Flashmandu\AppSdk\AppProvider` that
returns an `AppManifest`:

```php
namespace Acme\Loyalty;

use Flashmandu\AppSdk\AppManifest;
use Flashmandu\AppSdk\AppProvider;

class Manifest implements AppProvider
{
    public function manifest(): AppManifest
    {
        return new AppManifest(
            id: 'acme/loyalty',
            name: 'Loyalty Points',
            version: '1.0.0',
            scopes: [Scope::ReadOrders],
        );
    }
}
```

Register the class in the platform's `config/apps.php` `registered` list. A
merchant then installs it for their account on the platform's **Apps** page.

## What an app can declare

All optional except `id`/`name`/`version`:

- **`scopes`** — `Scope[]` the app requests at install (e.g. `ReadOrders`,
  `WriteCustomers`, `ManageStorefront`). `ManagePayments` is elevated and
  defaults to denied.
- **`hooks`** — an `AppHooks` implementation: declare listeners/filters that run
  **only for accounts that installed the app**:
  ```php
  public function register(HookRegistrar $r): void
  {
      $r->on('order.status.changed', function (array $payload, AppContext $ctx): void {
          // $ctx->profileId() — the merchant; $ctx->hasScope(Scope::ReadOrders)
      });
      $r->filter('cart.totals', fn (mixed $value, AppContext $ctx) => $value);
  }
  ```
- **`admin`** — an `AdminUI` with `MenuItem[]`: the app contributes items to the
  merchant's admin sidebar (gated by `requiredScopes`).
- **`storefront`** — a `Storefront` with `SectionDefinition[]`: composable
  section templates (element-trees of **existing** page-builder elements) the
  merchant drops onto pages. Parity across the platform's renderers is inherited
  by construction — never introduce a new element type.
- Implement **`Installable`** as well to run `onInstall()` / `onUninstall()`
  lifecycle hooks (seed defaults, clean up).

## Platform hooks reference (common)

- Events: `order.status.changed` (`{order_id, status}`), and others the platform
  emits via `Hooks::emit()`.
- Filters: pipe a value through apps, e.g. `cart.totals`.

## Test your app locally (no platform needed)

Verify your app reacts correctly **before** publishing — no platform, database,
or server required. `Flashmandu\AppSdk\Testing\AppTester` is an in-memory harness
that collects your app's hook declarations and lets you install, emit, and pipe
filters in your own Pest/PHPUnit suite:

```php
use Flashmandu\AppSdk\Testing\AppTester;

it('awards points when an order is paid', function (): void {
    $tester = AppTester::for(Acme\Loyalty\Manifest::class);

    $tester->install()->emit('order.status.changed', ['status' => 'paid']);

    expect($tester->wasFired('order.status.changed'))->toBeTrue();
    // assert your app's side effect (DB row, API call, etc.)
});

it('applies a redemption to cart totals', function (): void {
    $tester = AppTester::for(Acme\Loyalty\Manifest::class)->install();

    expect($tester->runFilter('cart.totals', 100))->toBe(90);
});
```

The harness mirrors the platform's gating: nothing fires before `install()`, so
tests reflect how the real engine only runs apps a merchant has installed.

## Versioning

This package is the **stable contract** between apps and the platform. Breaking
changes follow Semver (`vMAJOR.MINOR.PATCH`); apps pin a compatible range.

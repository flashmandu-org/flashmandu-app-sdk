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

## Versioning

This package is the **stable contract** between apps and the platform. Breaking
changes follow Semver (`vMAJOR.MINOR.PATCH`); apps pin a compatible range.

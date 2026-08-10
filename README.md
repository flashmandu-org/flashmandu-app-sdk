# Flashmandu App SDK

The public SDK for building **apps/plugins** that extend a Flashmandu storefront
platform. The SDK exposes contracts; the platform keeps its implementation.

> This package is intentionally dependency-light (PHP 8.2+, contracts only) so it
> is fully standalone and independently versionable.

**Full guide:** <https://business.autohisab.com/custom-app/development-docs> —
sections 3 → 3e cover this track end to end (package anatomy, sidebar nav, admin
screens, hooks, ports, lifecycle), and the hook/scope/extension tables there are
rendered live from the platform so they cannot drift.

## Install

```bash
composer require flashmandu/app-sdk
```

That gives you contracts and nothing else — the SDK boots nothing. What follows
is everything between *installed the SDK* and *the app shows up in a merchant's
admin*.

## Two tracks — pick the right one first

A PHP-native app runs **inside the platform process** with the host's full
privileges (database, filesystem, secrets), so a platform only runs PHP apps
whose code its operator has deliberately placed on the server: a first-party
package, or a partner package the operator vendored and reviewed.

If you are a third-party developer shipping to merchants on someone else's
platform, build a **remote app** instead (OAuth install, signed webhooks, App
Bridge iframe, GraphQL API — same hooks, same scopes, same sidebar, your code on
your own server). See section 2 of the guide, and the `autohisab-business` CLI.

## 1. Package layout

```
packages/acme/loyalty/
├── composer.json                  # extra.laravel.providers → your provider
├── src/
│   ├── Manifest.php               # implements AppProvider (+ Installable)
│   ├── LoyaltyServiceProvider.php # routes, views, migrations, Livewire
│   ├── Hooks/LoyaltyHooks.php     # implements AppHooks
│   ├── Livewire/…                 # your admin screens
│   └── Models/…                   # your OWN tables, profile-scoped
├── routes/web.php
├── database/migrations/
└── resources/views/
```

```json
{
    "name": "acme/loyalty",
    "require": { "php": "^8.2", "flashmandu/app-sdk": "^1.0" },
    "autoload": { "psr-4": { "Acme\\Loyalty\\": "src/" } },
    "extra": { "laravel": { "providers": ["Acme\\Loyalty\\LoyaltyServiceProvider"] } }
}
```

## 2. The manifest

```php
namespace Acme\Loyalty;

use Flashmandu\AppSdk\Admin\AdminUI;
use Flashmandu\AppSdk\Admin\MenuItem;
use Flashmandu\AppSdk\AppManifest;
use Flashmandu\AppSdk\AppProvider;
use Flashmandu\AppSdk\Scope;
use Flashmandu\AppSdk\Settings\SettingField;
use Flashmandu\AppSdk\Settings\SettingsSchema;

class Manifest implements AppProvider
{
    public function manifest(): AppManifest
    {
        return new AppManifest(
            id: 'acme/loyalty',
            name: 'Loyalty Points',
            version: '1.0.0',
            scopes: [Scope::ReadOrders, Scope::ReadCustomers],
            hooks: new Hooks\LoyaltyHooks,
            admin: new AdminUI(menu: [
                new MenuItem(
                    label: 'Loyalty',
                    route: 'loyalty.dashboard',              // a REGISTERED named route
                    icon: 'star',
                    requiredScopes: [Scope::ReadCustomers],  // hidden unless granted
                ),
            ]),
            settingsSchema: new SettingsSchema(fields: [
                new SettingField(name: 'points_per_currency', label: 'Points per unit', type: 'number', default: 1),
            ]),
        );
    }
}
```

All fields are optional except `id` / `name` / `version`:

- **`scopes`** — `Scope[]` requested at install (`ReadOrders`, `WriteCustomers`,
  `ManageStorefront`, `ReadContent`, `ReadEmployees`, `ReadMedia`, …).
  `ManagePayments` is elevated and defaults to denied. `ReadEmployees` is
  **sensitive** — employee records carry contact details for people who are not
  the merchant, and marketplace review treats it as elevated.
  Port scopes (`read:catalog`, `write:ledger`, `send:notifications`, …) are
  granted platform-side, not from the manifest — see §5. Catalog reads
  (`item.*`, `inventory.*`, the `items` list query) ride `read:catalog`; there
  is deliberately no separate `read:items`.
- **`hooks`** — an `AppHooks` implementation (§4).
- **`admin`** — an `AdminUI` of `MenuItem`s: your entries in the merchant's admin
  sidebar (§3).
- **`storefront`** — a `Storefront` of `SectionDefinition`s: composable section
  templates built from **existing** page-builder elements, so parity across the
  platform's renderers is inherited by construction. Never introduce a new
  element type.
- **`extensions`** — `Extension`s that contribute a block to a host surface
  (`admin.order.detail.block`, `pos.cart.action`, …).
- **`settingsSchema`** — a declarative form the host renders for you at
  `/apps/{appId}/settings`, persisted on the install row. Read it back with the
  platform's `AppManager::settingsFor($profile, $appId)`.
- Implement **`Installable`** for `onInstall()` / `onUninstall()` lifecycle work
  (seed defaults, register notification types, clean up). Prefer leaving business
  records intact on uninstall.

## 3. Service provider, routes, sidebar

```php
class LoyaltyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'loyalty');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        Livewire::component('loyalty.dashboard', Livewire\Dashboard::class);
    }
}
```

```php
// routes/web.php — the installed-app middleware enters your app's identity,
// which is what makes port scope checks (§5) real for your own screens.
Route::middleware(['web', 'auth', 'installed-app:acme/loyalty'])
    ->prefix('apps/loyalty')
    ->name('loyalty.')
    ->group(function (): void {
        Route::get('/', Livewire\Dashboard::class)->name('dashboard');
    });
```

A sidebar item renders only when **(a)** the merchant has the app installed and
enabled, **(b)** `Route::has($item->route)` is true, and **(c)** the item's
`requiredScopes` are granted. A missing route is skipped silently — that is the
usual cause of "my app has no sidebar entry". Check with
`php artisan route:list --name=loyalty`.

Screens are ordinary full-page Livewire components rendered in the host layout
(`->layout('components.layouts.app', ['title' => …])`) and must match host
styling — header block, filter bar, plain-table card, tinted icon-button row
actions. Section 3b of the guide is the pattern sheet; copy it rather than
inventing a package-local design system.

## 4. Hooks

Declare listeners and filters once; the platform decides at fire time which run,
so a listener only ever executes for a profile that installed **and enabled**
your app.

```php
class LoyaltyHooks implements AppHooks
{
    public function register(HookRegistrar $r): void
    {
        $r->on('order.status.changed', function (array $payload, AppContext $ctx): void {
            // $ctx->profileId() — the merchant (may be null: return early)
            // $ctx->hasScope(Scope::ReadOrders) — what they actually granted
            try {
                AwardPoints::for($ctx->profileId(), (int) $payload['order_id']);
            } catch (Throwable $e) {
                report($e); // listeners run inline in the host request
            }
        });

        // Filters receive a value and MUST return one.
        $r->filter('cart.totals', fn (array $totals, AppContext $ctx): array => $totals);
    }
}
```

- Your exceptions are **not** caught by the host: an uncaught throw fails the
  merchant's checkout, not just your feature. Catch, `report()`, return — and
  push anything slow onto a queued job.
- Ordering across apps is unspecified; never assume you run first or last.
- Prefer the typed `Flashmandu\AppSdk\Hooks\HookName` enum over raw strings.
- Common events: `order.created`, `order.updated`, `order.status.changed`,
  `customer.created/updated/deleted`, `item.created/updated/deleted`,
  `price.changed`, `inventory.level.changed`,
  `cms.entry.published/updated/unpublished/deleted`, `media.uploaded`,
  `employee.created/updated/archived`, `schedule.tick`; `cart.totals` is a
  filter. The live catalog is section 9 of the guide (and
  `autohisab-business hooks`).
- **Payloads are thin.** `{ id, occurred_at, sequence, changed: [...] }` plus a
  small summary — hydrate details through the ports with your scoped token.
  Deliveries are **at-least-once**: dedupe on `X-App-Delivery` and drop updates
  whose per-resource `sequence` is below what you already applied.
- **Subscribing is scope-gated**, at manifest sync AND at delivery time — a
  scope revoked later silences the stream. `customer.*` needs
  `Scope::ReadCustomers`, `item.*`/`inventory.*` need the host's `read:catalog`
  port scope, `cms.entry.*` needs `Scope::ReadContent`, `employee.*` needs
  `Scope::ReadEmployees`, media reads need `Scope::ReadMedia`.
- **Three lifecycle events are mandatory-delivery** — every remote app gets
  them whether or not it subscribed, and they retry through the full backoff
  schedule: `app.uninstalled`, `app.scopes.updated`, `profile.data.erased`.
  They are the purge signals; `HookName::isMandatory()` tells you which.
- **`schedule.tick` is declaration-driven**: it arrives because the app
  declared a `[[schedules]]` block in `flashmandu.app.toml`, not because it
  subscribed, and no read scope gates it. Payload is
  `{ schedule, profile_id, scheduled_at }` — `schedule` is the block's `name`,
  which is why those names must be unique per app. Adding it to
  `subscribed_events` does nothing; deleting the block is what stops it.
  `HookName::isDeclarationDriven()` distinguishes it from the mandatory set.

### Verifying inbound webhooks

Remote (non-PHP-native) apps receive signed HTTP deliveries. Stop hand-rolling
the HMAC:

```php
use Flashmandu\AppSdk\Webhooks\Verifier;

$result = (new Verifier($webhookSecret))->verify(
    file_get_contents('php://input'),   // RAW body — never a re-encode
    getallheaders(),                    // or $request->headers->all()
);

if (! $result->ok) {
    // $result->reason, $result->event, $result->deliveryId — log all three.
    http_response_code(401);
    return;
}

match ($result->hook()) {
    HookName::OrderCreated => $this->onOrderCreated($payload),
    default => null,
};
```

`verify()` checks `hash_hmac('sha256', "{$timestamp}.{$rawBody}", $secret)`
against `X-App-Signature` in constant time and rejects a timestamp more than
300s (configurable) from now, in either direction. `verifyAndDecode()` does the
same and hands back the decoded array, or null.

Retry ladder: **6 attempts** — 1m, 5m, 30m, 2h, 8h — then dead-letter. Respond
within 10s; accept with 202 and finish asynchronously if the work is slow.

### Page chrome (PHP-native apps)

An app renders **no desktop heading of its own**. Breadcrumbs, page actions and
the dirty ⇄ Save/Discard swap live in the host command bar. Iframe apps send
them over the bridge as `SET_PAGE`; PHP-native apps declare the same thing:

```php
use Flashmandu\AppSdk\Admin\{Breadcrumb, PageAction, PageChrome};

new PageChrome(
    crumbs: [
        new Breadcrumb('Loyalty', 'loyalty.index'),
        new Breadcrumb('Tiers'),          // current page — no route
    ],
    actions: [
        new PageAction(id: 'new-tier', label: 'New tier', variant: 'primary'),
    ],
    dirty: $form->isDirty(),
);
```

Caps mirror the protocol — 5 crumbs, 4 actions — because the host drops an
over-cap payload whole rather than trusting a partial chrome. While `dirty` is
true the host replaces your actions with its own native Save / Discard.

## 5. Reading and writing host data — ports

Do not query the host's Eloquent models. The platform exposes **ports** —
`CatalogPort`, `PartyPort`, `LocationPort`, `OrdersPort`, `LedgerPort`,
`DiscountPort`, `SettingsPort`, `NotificationPort`, `BroadcastPort` — resolved
from the container and scope-checked on every call:

```php
app(LocationPort::class)->forIds($ids);          // requires read:locations
app(NotificationPort::class)->notifyStaff(…);    // requires send:notifications
```

Four rules: reads are **batch-first** (`forIds(array)`, keyed by the id you
asked for — there is no `find($id)`, so an N+1 across the boundary is not
writable by accident); **no Eloquent crosses the boundary** (readonly DTOs, and
you store platform ids as plain scalars so a deleted host row cannot cascade
into your data); `isAvailable()` lets you **degrade instead of crash**; and a
call without the scope throws `ScopeDeniedException` and is audited.

Port scopes are granted platform-side (`config/apps.php` →
`default_port_scopes`) because they are not yet part of this package's `Scope`
enum. Tenancy comes from the platform's `ProfileScope`, never from an argument
you pass — scope your own tables by it too.

## 6. Registering the app with a platform

Either add the provider class to `config/apps.php` → `registered` (first-party,
trusted code only), or have a super-admin register it from **Apps → Add custom
app** by its FQCN, which requires only that the class is autoloadable and
implements `AppProvider`. Merchants then install it from their Apps page — that
install record is what gates hooks, menu items and scopes.

On Octane, manifest edits need a worker reload (`php artisan octane:reload`);
install/enable changes take effect immediately.

## 7. Test your app locally (no platform needed)

`Flashmandu\AppSdk\Testing\AppTester` is an in-memory harness that collects your
app's declarations and lets you install, emit and pipe filters in your own
Pest/PHPUnit suite:

```php
it('awards points when an order is paid', function (): void {
    $tester = AppTester::for(Acme\Loyalty\Manifest::class);

    $tester->install()->emit('order.status.changed', ['status' => 'paid']);

    expect($tester->wasFired('order.status.changed'))->toBeTrue();
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

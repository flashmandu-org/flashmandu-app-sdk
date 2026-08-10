<?php

namespace Flashmandu\AppSdk\Hooks;

use Flashmandu\AppSdk\Scope;

/**
 * Canonical, typed catalog of platform hooks an app may listen to or filter.
 *
 * The string values MUST stay in lock-step with the host's
 * Flashmandu\Apps\HookCatalog (the host is the source of truth; this enum is
 * a typed mirror that gives SDK consumers autocomplete + refactor safety).
 * A host-side test asserts the two sets are equal in BOTH directions, so a
 * case added here before the host ships it fails CI on the host.
 *
 * Delivery semantics for every event below: **at-least-once**. Payloads are
 * thin — `{ id, occurred_at, sequence, changed: [...] }` plus a small
 * denormalized summary — and details are hydrated through GraphQL with the
 * app's scoped token. Use the per-resource monotonic `sequence` to drop stale
 * updates and the `X-App-Delivery` header to dedupe retries.
 *
 * Subscribing to a category requires its read scope, enforced at manifest sync
 * AND at delivery time (a scope revoked later silences the stream):
 * `customer.*` ⇢ {@see Scope::ReadCustomers},
 * `item.*`/`inventory.*` ⇢ `read:catalog`,
 * `cms.entry.*` ⇢ {@see Scope::ReadContent},
 * `employee.*` ⇢ {@see Scope::ReadEmployees},
 * `media.*` ⇢ {@see Scope::ReadMedia}.
 *
 * Not every case is subscribable, and the enum says which is which:
 *
 * - The three lifecycle events ({@see self::AppUninstalled},
 *   {@see self::AppScopesUpdated}, {@see self::ProfileDataErased}) are
 *   **mandatory-delivery**: every remote app is implicitly subscribed and they
 *   retry through the full backoff schedule. They are the purge signals a
 *   connector needs. See {@see self::isMandatory()}.
 * - {@see self::ScheduleTick} is **declaration-driven**: it arrives because
 *   the app declared `[[schedules]]` in its manifest, not because it
 *   subscribed, and no read scope gates it. See
 *   {@see self::isDeclarationDriven()}.
 *
 * Backward compatibility: apps can still pass bare strings to
 * {@see \Flashmandu\AppSdk\HookRegistrar::on()} / {@see HookRegistrar::filter()}.
 * This enum is an opt-in convenience, not a requirement.
 *
 * PHP 8.2+ (SDK repo target).
 */
enum HookName: string
{
    /** Order or transaction status changed (created, paid, shipped, cancelled, refunded). */
    case OrderStatusChanged = 'order.status.changed';

    /** An individual order line item was cancelled. */
    case OrderItemCancelled = 'order.item.cancelled';

    /** An order line item quantity was adjusted. */
    case OrderItemQuantityChanged = 'order.item.quantity.changed';

    /** A customer/party balance changed (credit, debit, adjustment). */
    case PartyBalanceChanged = 'party.balance.changed';

    /** An account balance changed. */
    case AccountBalanceChanged = 'account.balance.changed';

    /** A new in-app notification was created for a user. */
    case NewInAppNotification = 'new.in.app.notification';

    /** A new chat/message was sent. */
    case NewMessageSent = 'new.message.sent';

    /** A service location (table/room/station) status changed. */
    case ServiceLocationStatusChanged = 'service.location.status.changed';

    /** A call was initiated. */
    case CallInitiated = 'call.initiated';

    /** A call ended. */
    case CallEnded = 'call.ended';

    /** An order was created. */
    case OrderCreated = 'order.created';

    /** An existing order was updated. */
    case OrderUpdated = 'order.updated';

    /** A customer was created. */
    case CustomerCreated = 'customer.created';

    /** A customer was updated. */
    case CustomerUpdated = 'customer.updated';

    /** A customer was deleted. */
    case CustomerDeleted = 'customer.deleted';

    /** A catalog item was created. */
    case ItemCreated = 'item.created';

    /** A catalog item was updated. */
    case ItemUpdated = 'item.updated';

    /** A catalog item was deleted. */
    case ItemDeleted = 'item.deleted';

    /** A catalog item's price changed. */
    case PriceChanged = 'price.changed';

    /** An item's stock level changed at a location. */
    case InventoryLevelChanged = 'inventory.level.changed';

    /** A CMS entry was published. */
    case CmsEntryPublished = 'cms.entry.published';

    /** A published CMS entry was updated. */
    case CmsEntryUpdated = 'cms.entry.updated';

    /** A CMS entry was unpublished. */
    case CmsEntryUnpublished = 'cms.entry.unpublished';

    /** A CMS entry was deleted. */
    case CmsEntryDeleted = 'cms.entry.deleted';

    /** A media file was uploaded to the merchant's library. */
    case MediaUploaded = 'media.uploaded';

    /** An employee record was created. */
    case EmployeeCreated = 'employee.created';

    /** An employee record was updated. */
    case EmployeeUpdated = 'employee.updated';

    /** An employee was archived (soft-removed from the directory). */
    case EmployeeArchived = 'employee.archived';

    /**
     * Lifecycle (mandatory delivery): the app was uninstalled from a profile.
     * Purge that profile's data — no further reads will be authorized.
     */
    case AppUninstalled = 'app.uninstalled';

    /**
     * Lifecycle (mandatory delivery): the merchant changed the app's granted
     * scopes. Re-check what the app may still read before its next sync.
     */
    case AppScopesUpdated = 'app.scopes.updated';

    /**
     * Lifecycle (mandatory delivery): the merchant erased a profile's data.
     * The GDPR-parity purge signal — delete every copy the app holds.
     */
    case ProfileDataErased = 'profile.data.erased';

    /**
     * A schedule the app declared in `[[schedules]]` came due.
     *
     * Delivered because the app DECLARED it, not because it subscribed — see
     * {@see self::isDeclarationDriven()}. Payload is
     * `{ schedule, profile_id, scheduled_at }`; `schedule` is the `name` from
     * the manifest block, which is why those names must be unique per app.
     * Same HMAC, retries, backoff and delivery log as every other webhook.
     */
    case ScheduleTick = 'schedule.tick';

    /**
     * Filter: modify cart totals before checkout (fees, discounts, points).
     * Registered via {@see HookRegistrar::filter()} rather than {@see HookRegistrar::on()}.
     */
    case CartTotals = 'cart.totals';

    /**
     * Cases whose hooks are filters (mutators) rather than event listeners.
     * Keys are the enum cases; values are the hook names for convenience.
     *
     * @return array<string, string>
     */
    public const FILTERS = [
        'cart.totals' => 'cart.totals',
    ];

    /**
     * Hook names delivered without an explicit subscription. The host sends
     * these to every remote app and keeps retrying through the full backoff
     * schedule, because an app that never learns it was uninstalled keeps
     * merchant data forever.
     *
     * @return array<int, string>
     */
    public const MANDATORY = [
        'app.uninstalled',
        'app.scopes.updated',
        'profile.data.erased',
    ];

    /**
     * Hooks the host delivers because the app DECLARED something, not because
     * it subscribed to a stream. No `subscribed_events` entry turns these on
     * and no read scope gates them — they follow from a declaration elsewhere
     * in the manifest.
     *
     * Today that is `schedule.tick`, which follows from `[[schedules]]`.
     * Mandatory lifecycle hooks are a different species: they arrive whether
     * or not the app declared anything at all.
     *
     * @return array<int, string>
     */
    public const DECLARATION_DRIVEN = [
        'schedule.tick',
    ];

    /**
     * Short, human-readable description of when this hook fires.
     *
     * Mirrors the `description` field of the host's `HookCatalog::all()`.
     * (Deliberately not a `{@see}`: HookCatalog is host-side and does not
     * exist in this package, so importing it would be a lie.)
     */
    public function description(): string
    {
        return match ($this) {
            self::OrderStatusChanged => 'Order or transaction status changed (created, paid, shipped, cancelled, refunded).',
            self::OrderItemCancelled => 'An individual order line item was cancelled.',
            self::OrderItemQuantityChanged => 'An order line item quantity was adjusted.',
            self::PartyBalanceChanged => 'A customer/party balance changed (credit, debit, adjustment).',
            self::AccountBalanceChanged => 'An account balance changed.',
            self::NewInAppNotification => 'A new in-app notification was created for a user.',
            self::NewMessageSent => 'A new chat/message was sent.',
            self::ServiceLocationStatusChanged => 'A service location (table/room/station) status changed.',
            self::CallInitiated => 'A call was initiated.',
            self::CallEnded => 'A call ended.',
            self::OrderCreated => 'An order was created.',
            self::OrderUpdated => 'An existing order was updated.',
            self::CustomerCreated => 'A customer was created.',
            self::CustomerUpdated => 'A customer was updated.',
            self::CustomerDeleted => 'A customer was deleted.',
            self::ItemCreated => 'A catalog item was created.',
            self::ItemUpdated => 'A catalog item was updated.',
            self::ItemDeleted => 'A catalog item was deleted.',
            self::PriceChanged => "A catalog item's price changed.",
            self::InventoryLevelChanged => "An item's stock level changed at a location.",
            self::CmsEntryPublished => 'A CMS entry was published.',
            self::CmsEntryUpdated => 'A published CMS entry was updated.',
            self::CmsEntryUnpublished => 'A CMS entry was unpublished.',
            self::CmsEntryDeleted => 'A CMS entry was deleted.',
            self::MediaUploaded => "A media file was uploaded to the merchant's library.",
            self::EmployeeCreated => 'An employee record was created.',
            self::EmployeeUpdated => 'An employee record was updated.',
            self::EmployeeArchived => 'An employee was archived (soft-removed from the directory).',
            self::AppUninstalled => 'The app was uninstalled from a profile. Purge that profile’s data.',
            self::AppScopesUpdated => 'The merchant changed the app’s granted scopes.',
            self::ProfileDataErased => 'The merchant erased a profile’s data. Delete every copy the app holds.',
            self::ScheduleTick => 'A schedule the app declared in [[schedules]] came due.',
            self::CartTotals => 'Filter: modify cart totals before checkout (fees, discounts, points).',
        };
    }

    /** True when this hook is a filter (mutator) rather than a broadcast event. */
    public function isFilter(): bool
    {
        return array_key_exists($this->value, self::FILTERS);
    }

    /**
     * True when the host delivers this hook whether or not the app subscribed.
     * Mandatory hooks cannot be unsubscribed and must be handled.
     */
    public function isMandatory(): bool
    {
        return in_array($this->value, self::MANDATORY, true);
    }

    /**
     * True when this hook arrives because of a manifest DECLARATION rather
     * than a subscription — `schedule.tick` from `[[schedules]]`. Adding it to
     * `subscribed_events` does nothing; removing the declaration is what stops
     * it.
     */
    public function isDeclarationDriven(): bool
    {
        return in_array($this->value, self::DECLARATION_DRIVEN, true);
    }
}

<?php

namespace Flashmandu\AppSdk\Hooks;

/**
 * Canonical, typed catalog of platform hooks an app may listen to or filter.
 *
 * The string values MUST stay in lock-step with the host's
 * Flashmandu\Apps\HookCatalog (the host is the source of truth; this enum is
 * a typed mirror that gives SDK consumers autocomplete + refactor safety).
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
     * Short, human-readable description of when this hook fires.
     *
     * Mirrors the `description` field of {@see \Flashmandu\Apps\HookCatalog::all()}.
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
            self::CartTotals => 'Filter: modify cart totals before checkout (fees, discounts, points).',
        };
    }

    /** True when this hook is a filter (mutator) rather than a broadcast event. */
    public function isFilter(): bool
    {
        return array_key_exists($this->value, self::FILTERS);
    }
}

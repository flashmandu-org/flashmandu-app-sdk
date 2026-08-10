<?php

namespace Flashmandu\AppSdk\Extensions;

/**
 * Surfacing points where an app extension can contribute UI or behavior.
 *
 * Each case's value is the stable contract string the host matches against;
 * consumers should prefer the enum over raw strings.
 */
enum ExtensionTarget: string
{
    case AdminOrderDetailBlock = 'admin.order.detail.block';
    case AdminOrderDetailAction = 'admin.order.detail.action';
    case AdminOrderIndexAction = 'admin.order.index.action';
    case AdminItemDetailBlock = 'admin.item.detail.block';
    case AdminItemDetailAction = 'admin.item.detail.action';
    case AdminPoDetailBlock = 'admin.po.detail.block';
    case AdminCustomerDetailBlock = 'admin.customer.detail.block';
    case AdminCustomerHistoryBlock = 'admin.customer.history.block';
    case AdminExpenseIndexAction = 'admin.expense.index.action';

    /**
     * Bulk-action surfaces: rendered only while rows are SELECTED on an index
     * page, next to the host's own bulk actions. Distinct from
     * `admin.order.index.action`, which is the page-level action bar and is
     * always visible — an extension that acts on a selection has nothing to act
     * on until there is one.
     */
    case AdminOrderIndexSelectionAction = 'admin.order.index.selection-action';

    case AdminItemIndexSelectionAction = 'admin.item.index.selection-action';

    /**
     * Contributes a printable document to a detail page's print menu, alongside
     * the host's own invoice/receipt entries.
     */
    case AdminOrderDetailPrintAction = 'admin.order.detail.print-action';

    case PosCartAction = 'pos.cart.action';
    case StorefrontSection = 'storefront.section';

    /**
     * Case-insensitive lookup by enum value, returning null on miss.
     *
     * Acts as a thin convenience wrapper over the native tryFrom(), accepting
     * values that may arrive from external sources (manifests, payloads) in
     * arbitrary casing.
     */
    public static function tryFromValue(string $value): ?self
    {
        return self::tryFrom($value) ?? self::tryFrom(strtolower($value));
    }
}

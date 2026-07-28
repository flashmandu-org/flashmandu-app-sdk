<?php

namespace Flashmandu\AppSdk;

/**
 * Permission scopes an app may request at install time.
 *
 * Keys are TitleCase per project convention; string values follow the
 * "<verb>:<resource>" convention surfaced to the merchant in the install UI.
 *
 * ManagePayments is an elevated scope guarded by the host's hard data-access
 * boundary (see spec decision Q4): it defaults to denied even for installed apps.
 *
 * @deprecated since v1.x, bookings removed; do not use. The ReadBookings and
 *             WriteBookings cases are retained solely for SemVer compatibility
 *             with already-installed apps — the bookings domain has left the
 *             platform and the host no longer grants these scopes.
 */
enum Scope: string
{
    case ReadOrders = 'read:orders';
    case WriteOrders = 'write:orders';
    case ReadCustomers = 'read:customers';
    case WriteCustomers = 'write:customers';

    /** @deprecated since v1.x, bookings removed; do not use. Retained for SemVer only. */
    case ReadBookings = 'read:bookings';

    /** @deprecated since v1.x, bookings removed; do not use. Retained for SemVer only. */
    case WriteBookings = 'write:bookings';

    case ManageStorefront = 'manage:storefront';
    case ManagePayments = 'manage:payments';
}

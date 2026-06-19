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
 */
enum Scope: string
{
    case ReadOrders = 'read:orders';
    case WriteOrders = 'write:orders';
    case ReadCustomers = 'read:customers';
    case WriteCustomers = 'write:customers';
    case ReadBookings = 'read:bookings';
    case WriteBookings = 'write:bookings';
    case ManageStorefront = 'manage:storefront';
    case ManagePayments = 'manage:payments';
}

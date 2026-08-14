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
 * Catalog reads (`item.*` / `inventory.*` hooks, `catalogItems`, the `items`
 * list query) ride the host's existing `read:catalog` port scope. There is
 * deliberately NO `ReadItems` case: `read:catalog` already exists platform-side
 * and, per `Flashmandu\Apps\Ports\PortScope`, port scopes can only be granted
 * explicitly at install. Minting a near-synonym would mean every already-
 * installed app silently loses catalog access until the merchant re-grants,
 * and buys nothing a merchant would decide differently.
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

    /**
     * Read CMS entries through `CmsPort`: summaries (id, module, title, slug,
     * status, publishedAt, excerpt, tags, storefront permalink, featured media
     * as a public CDN URL) and the separate `cmsEntryBody` read. Also gates
     * subscription to the `cms.entry.*` hooks.
     */
    case ReadContent = 'read:content';

    /**
     * Read the employee directory through `EmployeePort`, and subscribe to the
     * `employee.*` hooks.
     *
     * SENSITIVE: employee records carry contact details — personal data about
     * people who are not the merchant. Marketplace review treats this scope as
     * elevated; request it only if the app genuinely needs the directory.
     */
    case ReadEmployees = 'read:employees';

    /**
     * Read media through `MediaPort`: public CDN URL, dimensions and mime for
     * media the app already knows the id of. URLs are always public CDN URLs,
     * never presigned — third-party APIs (Meta, TikTok) fetch them server-side.
     */
    case ReadMedia = 'read:media';
}

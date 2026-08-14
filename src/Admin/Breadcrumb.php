<?php

namespace Flashmandu\AppSdk\Admin;

/**
 * One breadcrumb in the host command bar.
 *
 * The trail is ordered from the app's root to the current page, and the LAST
 * entry is the current page — it carries no route, because a crumb that
 * navigates to where you already are is a dead control.
 *
 * Iframe apps send the same structure over the bridge as `SET_PAGE.crumbs`;
 * PHP-native apps declare it here. One host renderer serves both.
 */
final readonly class Breadcrumb
{
    /**
     * @param  string  $label  Shown in the command bar. Keep it short — the bar truncates.
     * @param  string|null  $route  Named route to navigate to. Null on the current (last) crumb.
     */
    public function __construct(
        public string $label,
        public ?string $route = null,
    ) {}
}

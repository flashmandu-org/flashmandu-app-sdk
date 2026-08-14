<?php

namespace Flashmandu\AppSdk\Admin;

/**
 * One command-bar action button owned by the app.
 *
 * The host renders these in ITS bar, not in the page — an embedded or
 * PHP-native app never draws its own desktop action row. The `id` is what
 * comes back when the merchant clicks: it is the stable handle the app
 * dispatches on, so it must not change between renders of the same button.
 *
 * When a page reports itself dirty ({@see PageChrome::$dirty}) the host
 * replaces this set with its own native Save / Discard pair, so an app never
 * needs to ship a save button of its own.
 */
final readonly class PageAction
{
    /**
     * @param  string  $id  Stable handle echoed back on click. `[A-Za-z0-9_-]{1,32}`.
     * @param  string  $label  Button label, max 48 characters.
     * @param  string  $variant  One of `primary`, `ghost`, `danger`.
     * @param  string|null  $icon  Icon name from the host allow-list. Unknown names render no icon.
     */
    public function __construct(
        public string $id,
        public string $label,
        public string $variant = 'ghost',
        public ?string $icon = null,
    ) {}
}

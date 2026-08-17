<?php

namespace Flashmandu\AppSdk\Admin;

use Flashmandu\AppSdk\Scope;

/**
 * One admin navigation entry contributed by an app.
 *
 * An app with more than one screen declares them as `children` of its top-level
 * item, and the host renders them as indented sub-links beneath it — the same
 * shape a remote app gets from its manifest's `[[nav_items]]`. Nesting is one
 * level deep on purpose: a child's own children are ignored rather than
 * rendered, so the sidebar cannot grow a tree a merchant has to unfold.
 */
final readonly class MenuItem
{
    /**
     * @param  array<int, Scope>  $requiredScopes  item only renders if granted
     * @param  array<int, MenuItem>  $children  sub-links rendered beneath this item
     */
    public function __construct(
        public string $label,
        public string $route,
        public ?string $icon = null,
        public array $requiredScopes = [],
        public array $children = [],
    ) {}
}

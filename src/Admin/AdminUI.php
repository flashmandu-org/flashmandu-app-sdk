<?php

namespace Flashmandu\AppSdk\Admin;

/**
 * The admin-surface contribution of an app: a set of menu items.
 */
final readonly class AdminUI
{
    /**
     * @param  array<int, MenuItem>  $menu
     */
    public function __construct(
        public array $menu,
    ) {}
}

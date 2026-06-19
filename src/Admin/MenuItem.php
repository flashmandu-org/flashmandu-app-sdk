<?php

namespace Flashmandu\AppSdk\Admin;

use Flashmandu\AppSdk\Scope;

/**
 * One admin navigation entry contributed by an app.
 */
final readonly class MenuItem
{
    /**
     * @param  array<int, Scope>  $requiredScopes  item only renders if granted
     */
    public function __construct(
        public string $label,
        public string $route,
        public ?string $icon = null,
        public array $requiredScopes = [],
    ) {}
}

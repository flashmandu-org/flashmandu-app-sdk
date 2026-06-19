<?php

namespace Flashmandu\AppSdk\Storefront;

/**
 * The storefront-surface contribution of an app: a set of section definitions.
 */
final readonly class Storefront
{
    /**
     * @param  array<int, SectionDefinition>  $sections
     */
    public function __construct(
        public array $sections,
    ) {}
}

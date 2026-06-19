<?php

namespace Flashmandu\AppSdk\Storefront;

/**
 * A composable section/block an app contributes to the storefront.
 *
 * An app section is a composable element_tree built from EXISTING page-builder
 * elements (heading, text, button, image, ...). Because it uses only elements
 * the platform already knows how to render, it inherits 3-renderer parity
 * (blade / local-preview / PageBuilder) by construction — no new element type
 * and no renderer edits required.
 */
final readonly class SectionDefinition
{
    /**
     * @param  array<int, array<string, mixed>>|null  $elementTree  composable blueprint of existing elements
     */
    public function __construct(
        public string $type,
        public string $name,
        public ?array $elementTree = null,
        public ?string $category = null,
        public ?string $view = null,
    ) {}
}

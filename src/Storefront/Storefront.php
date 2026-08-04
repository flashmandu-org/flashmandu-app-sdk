<?php

namespace Flashmandu\AppSdk\Storefront;

/**
 * The storefront-surface contribution of an app: composable sections AND
 * interactive embed blocks.
 *
 * Sections ({@see SectionDefinition}) inherit platform element parity by
 * construction; embed blocks ({@see EmbedBlockDefinition}) are the sanctioned
 * escape hatch for apps that must ship interactive code, rendered as sandboxed
 * iframes under the uniform storefront security envelope.
 */
final readonly class Storefront
{
    /**
     * @param  array<int, SectionDefinition>  $sections  composable sections built from existing elements
     * @param  array<int, EmbedBlockDefinition>|null  $embedBlocks  interactive iframe blocks; null = app declares none (treated as empty, backward compatible)
     */
    public function __construct(
        public array $sections,
        public ?array $embedBlocks = null,
    ) {}

    /**
     * Embed blocks declared by this app. Always returns a list (never null),
     * so consumers can iterate without a guard. Null is normalised to [].
     *
     * @return array<int, EmbedBlockDefinition>
     */
    public function embedBlocks(): array
    {
        return $this->embedBlocks ?? [];
    }

    /**
     * Return a new Storefront carrying the given embed blocks. Readonly-safe
     * (returns a fresh instance). Used by the host to fold in blocks resolved
     * from `installed_apps.extensions` JSON for remote apps that have no PHP
     * manifest in-process.
     *
     * @param  array<int, EmbedBlockDefinition>  $blocks
     */
    public function withEmbedBlocks(array $blocks): self
    {
        return new self(
            sections: $this->sections,
            embedBlocks: array_values($blocks),
        );
    }
}

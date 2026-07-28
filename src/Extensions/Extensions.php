<?php

namespace Flashmandu\AppSdk\Extensions;

/**
 * The extension-surface contribution of an app: a set of extensions.
 */
final readonly class Extensions
{
    /**
     * @param  array<int, Extension>  $extensions
     */
    public function __construct(
        public array $extensions,
    ) {}
}

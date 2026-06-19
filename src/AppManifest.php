<?php

namespace Flashmandu\AppSdk;

use Flashmandu\AppSdk\Admin\AdminUI;
use Flashmandu\AppSdk\Storefront\Storefront;

/**
 * Immutable description of an app: declared by the app package, read by the host.
 */
final readonly class AppManifest
{
    /**
     * @param  array<int, Scope>  $scopes  scopes the app requests at install
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $version,
        public array $scopes = [],
        public ?AppHooks $hooks = null,
        public ?AdminUI $admin = null,
        public ?Storefront $storefront = null,
    ) {}
}

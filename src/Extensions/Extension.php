<?php

namespace Flashmandu\AppSdk\Extensions;

/**
 * A single app-contributed extension bound to a host surfacing point.
 *
 * An extension describes WHERE it attaches (target) and HOW the host should
 * render it: a local blade view (block), a server-rendered fragment (render),
 * or a remote URL loaded inside the host shell (remoteUrl). Scope gates are
 * evaluated by the host before the extension is surfaced.
 */
final readonly class Extension
{
    /**
     * @param  string  $target  target surfacing point; an ExtensionTarget value
     * @param  string  $label  human-readable label shown by the host
     * @param  string|null  $render  server-rendered fragment or view key, when local
     * @param  string|null  $remoteUrl  absolute URL rendered inside the host shell, when remote
     * @param  string|null  $block  blade/view block key the host should include
     * @param  array<int, string>  $requiredScopes  app scopes that must be granted for the extension to render
     * @param  array<int, string>  $requiredPortScopes  per-port (profile) scopes that must be granted for the extension to render
     * @param  string|null  $icon  optional icon name/identifier for host chrome
     */
    public function __construct(
        public string $target,
        public string $label,
        public ?string $render = null,
        public ?string $remoteUrl = null,
        public ?string $block = null,
        public array $requiredScopes = [],
        public array $requiredPortScopes = [],
        public ?string $icon = null,
    ) {}
}

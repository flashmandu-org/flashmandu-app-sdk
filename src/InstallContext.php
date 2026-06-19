<?php

namespace Flashmandu\AppSdk;

/**
 * Context passed to install/uninstall lifecycle hooks.
 */
final readonly class InstallContext
{
    /**
     * @param  array<int, Scope>  $scopes
     */
    public function __construct(
        public ?object $profile,
        public string $appId,
        public array $scopes,
    ) {}

    public function profileId(): ?int
    {
        return isset($this->profile->id) ? (int) $this->profile->id : null;
    }
}

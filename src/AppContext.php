<?php

namespace Flashmandu\AppSdk;

/**
 * Runtime context handed to an app hook at fire time.
 *
 * The profile is typed opaquely as ?object so this SDK stays free of any
 * host dependency; the host passes its real Profile instance and apps in the
 * host may narrow the type themselves.
 */
final readonly class AppContext
{
    /**
     * @param  array<int, Scope>  $scopes  scopes granted to this app for this profile
     */
    public function __construct(
        public ?object $profile,
        public string $appId,
        public array $scopes = [],
    ) {}

    public function hasScope(Scope $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }

    public function profileId(): ?int
    {
        return isset($this->profile->id) ? (int) $this->profile->id : null;
    }
}

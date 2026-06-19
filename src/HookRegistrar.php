<?php

namespace Flashmandu\AppSdk;

/**
 * Collected by an app during registration. The host stores these declarations
 * and invokes them at fire time, gated by per-profile enablement.
 *
 * Listener signature: callable(array $payload, AppContext $context): mixed
 * Mutator signature:  callable(mixed $value, AppContext $context): mixed
 */
interface HookRegistrar
{
    public function on(string $event, callable $listener): void;

    public function filter(string $hook, callable $mutator): void;
}

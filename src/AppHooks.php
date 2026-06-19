<?php

namespace Flashmandu\AppSdk;

/**
 * Implemented by an app to declare its event listeners and filters.
 */
interface AppHooks
{
    public function register(HookRegistrar $registrar): void;
}

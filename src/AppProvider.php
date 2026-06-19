<?php

namespace Flashmandu\AppSdk;

/**
 * Implemented by an app's provider class to supply its manifest.
 * The host registry instantiates each registered provider and reads its manifest
 * once at boot (Octane-safe — manifests do not change per request).
 */
interface AppProvider
{
    public function manifest(): AppManifest;
}

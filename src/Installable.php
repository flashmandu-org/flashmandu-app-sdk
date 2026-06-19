<?php

namespace Flashmandu\AppSdk;

/**
 * Optional lifecycle hooks an app may implement. The host calls onInstall()
 * when a profile installs the app and onUninstall() on removal.
 */
interface Installable
{
    public function onInstall(InstallContext $context): void;

    public function onUninstall(InstallContext $context): void;
}

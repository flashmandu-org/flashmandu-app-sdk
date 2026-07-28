<?php

namespace Flashmandu\AppSdk\Settings;

/**
 * The declarative settings schema an app attaches to its manifest.
 *
 * Composed of an ordered list of {@see SettingField} DTOs. The host reads the
 * schema to auto-render a Flux form for in-process apps (those that ship a
 * manifest); remote apps have no manifest in the host and therefore render
 * their settings UI in their own iframe.
 */
final readonly class SettingsSchema
{
    /**
     * @param  array<int, SettingField>  $fields  ordered list of declared setting fields
     */
    public function __construct(
        public array $fields,
    ) {}
}

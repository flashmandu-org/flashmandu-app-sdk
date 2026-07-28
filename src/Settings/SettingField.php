<?php

namespace Flashmandu\AppSdk\Settings;

/**
 * A single declarative setting field an app surfaces through its manifest.
 *
 * The host reads this DTO to auto-render a settings form (Flux input/select/
 * checkbox/color/input-type-url) without the app shipping its own UI. Readonly
 * so a manifest's declared shape cannot be mutated at runtime — the host trusts
 * it as a stable contract.
 *
 * Supported $type values:
 *  - text     → single-line flux:input
 *  - number   → flux:input type="number"
 *  - boolean  → flux:checkbox
 *  - select   → flux:select (requires $options as a label/value map)
 *  - color    → flux:input type="color"
 *  - url      → flux:input type="url"
 */
final readonly class SettingField
{
    /**
     * @param  string  $name  stable machine key under which the value is stored in InstalledApp::settings
     * @param  string  $label  human-readable label rendered beside the input
     * @param  string  $type  one of text|number|boolean|select|color|url
     * @param  mixed  $default  value used when no stored value exists
     * @param  array<string, mixed>|null  $options  select options as ['value' => 'Label'] pairs (required for select, ignored otherwise)
     * @param  string|null  $help  optional help text rendered under the field
     * @param  bool  $required  marks the field required for validation
     */
    public function __construct(
        public string $name,
        public string $label,
        public string $type = 'text',
        public mixed $default = null,
        public ?array $options = null,
        public ?string $help = null,
        public bool $required = false,
    ) {}
}

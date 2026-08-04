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
 *  - select   → flux:select (requires $options OR $dynamic_options)
 *  - color    → flux:input type="color"
 *  - url      → flux:input type="url"
 *
 * $options gives a STATIC list the merchant picks from; $dynamic_options lets
 * the merchant pick from data the APP owns (e.g. "which map"). The host calls
 * the app's option endpoint server-side (scoped App API, keyed by storefront
 * identity) and renders the result as a normal <flux:select>.
 */
final readonly class SettingField
{
    /** Source marker for app-sourced dynamic options. */
    public const DYNAMIC_SOURCE_APP = 'app';

    /**
     * @param  string  $name  stable machine key under which the value is stored in InstalledApp::settings
     * @param  string  $label  human-readable label rendered beside the input
     * @param  string  $type  one of text|number|boolean|select|color|url
     * @param  mixed  $default  value used when no stored value exists
     * @param  array<string, mixed>|null  $options  STATIC select options as ['value' => 'Label'] pairs (required for select when no $dynamic_options, ignored otherwise)
     * @param  string|null  $help  optional help text rendered under the field
     * @param  bool  $required  marks the field required for validation
     * @param  array{source: string, path: string}|null  $dynamic_options  DYNAMIC options descriptor: ['source' => 'app', 'path' => '/blocks/{type}/options/{field}']. The host resolves {type}/{field} server-side and caches per (profile, app, field).
     */
    public function __construct(
        public string $name,
        public string $label,
        public string $type = 'text',
        public mixed $default = null,
        public ?array $options = null,
        public ?string $help = null,
        public bool $required = false,
        public ?array $dynamic_options = null,
    ) {}

    /**
     * @param  array<string, mixed>  $d
     * @param  array{source: string, path: string}|null  $dynamic_options  override $d['dynamic_options']
     */
    public static function fromArray(array $d, ?array $dynamic_options = null): self
    {
        $dynamic_options ??= isset($d['dynamic_options']) && is_array($d['dynamic_options'])
            ? [
                'source' => (string) ($d['dynamic_options']['source'] ?? self::DYNAMIC_SOURCE_APP),
                'path' => (string) ($d['dynamic_options']['path'] ?? ''),
            ]
            : null;

        return new self(
            name: (string) $d['name'],
            label: (string) $d['label'],
            type: (string) ($d['type'] ?? 'text'),
            default: $d['default'] ?? null,
            options: isset($d['options']) && is_array($d['options']) ? $d['options'] : null,
            help: isset($d['help']) ? (string) $d['help'] : null,
            required: (bool) ($d['required'] ?? false),
            dynamic_options: $dynamic_options,
        );
    }

    /** @return array<string, mixed> JSON-friendly representation (input-equivalent to {@see fromArray()}) */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'type' => $this->type,
            'default' => $this->default,
            'options' => $this->options,
            'help' => $this->help,
            'required' => $this->required,
            'dynamic_options' => $this->dynamic_options,
        ];
    }
}

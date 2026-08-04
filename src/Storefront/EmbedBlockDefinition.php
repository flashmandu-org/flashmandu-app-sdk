<?php

namespace Flashmandu\AppSdk\Storefront;

use Flashmandu\AppSdk\Scope;
use Flashmandu\AppSdk\Settings\SettingField;

/**
 * An interactive embed block an app contributes to the storefront.
 *
 * Unlike {@see SectionDefinition} — which is a composable element_tree built
 * from existing platform elements and therefore inherits 3-renderer parity by
 * construction — an embed block is the host's sanctioned escape hatch for apps
 * that must ship their own interactive code. The block renders on the public
 * storefront as a sandboxed `<iframe>` (scripts-only) served from the app's
 * registered {@see $block_origin} + {@see $block_path}, and talks to the host
 * only through the locked-down storefront App Bridge (postMessage).
 *
 * The iframe inherits standard page-builder element chrome (layout / appearance
 * / responsive / motion) for free via the host's `nodeCapabilityMatrix`; the
 * app only declares this descriptor plus its {@see $settings} schema. Capabilities
 * and scopes are enforced uniformly by the host regardless of hosting tier.
 *
 * @see https://docs.flashmandu.app/app-embed-blocks  App Embed Blocks (partner docs)
 */
final readonly class EmbedBlockDefinition
{
    /**
     * Capabilities the host recognises on the storefront surface. A block may
     * declare any subset; anything outside this list is rejected at registration.
     */
    public const CAPABILITY_IDENTITY = 'identity';

    public const CAPABILITY_RESIZE = 'resize';

    public const CAPABILITY_NAVIGATE = 'navigate';

    public const CAPABILITY_ERROR = 'error';

    /** Capabilities valid for a storefront embed block. */
    public const ALLOWED_CAPABILITIES = [
        self::CAPABILITY_IDENTITY,
        self::CAPABILITY_RESIZE,
        self::CAPABILITY_NAVIGATE,
        self::CAPABILITY_ERROR,
    ];

    /** Hosting tiers: changes hosting/latency/origin-pinning only, never the security envelope. */
    public const HOSTING_TIER_TRUSTED = 'trusted';

    public const HOSTING_TIER_REMOTE = 'remote';

    public const ALLOWED_HOSTING_TIERS = [
        self::HOSTING_TIER_TRUSTED,
        self::HOSTING_TIER_REMOTE,
    ];

    /** Default settings-schema version when an app does not declare one. */
    public const DEFAULT_SCHEMA_VERSION = 1;

    /**
     * @param  string  $type  stable machine key, unique within the app (e.g. "interactive-map")
     * @param  string  $name  merchant-facing label shown in the picker / inspector
     * @param  string  $description  merchant-facing description shown in the picker
     * @param  string  $category  picker grouping (e.g. "Maps", "Media")
     * @param  string  $icon  icon key/token the host renders in the picker/inspector (Tabler key or app icon)
     * @param  string  $block_origin  registered HTTPS origin that may serve this block's iframe; pinned at install, never app-mutable without re-approval. Trusted tier: loopback/internal origin; remote tier: app's public host on the allowlist.
     * @param  string  $block_path  path under {@see $block_origin} the host navigates the iframe to; the full src is assembled by the host (origin+path+session_token)
     * @param  array<int, SettingField>  $settings  merchant-configurable fields; supports static options AND dynamic options sourced from the app (see {@see SettingField::$dynamic_options})
     * @param  array<int, string>  $capabilities  subset of {@see ALLOWED_CAPABILITIES}; storefront blocks get {@see Scope::identity}-equivalent context only — never merchant scopes
     * @param  array<int, Scope>  $scopes  platform scopes required (empty for storefront-only blocks; enforced centrally and approved at OAuth install)
     * @param  int  $schema_version  settings schema version; defaults to {@see DEFAULT_SCHEMA_VERSION}. The host runs {@see onSettingsMigrate()} on edit/render when this bumps.
     * @param  string|null  $replaces  previous `$type` this descriptor supersedes (deprecation/rename path without orphaning placed nodes)
     * @param  string  $hosting_tier  one of {@see ALLOWED_HOSTING_TIERS}; set at install from this declaration
     */
    public function __construct(
        public string $type,
        public string $name,
        public string $description,
        public string $category,
        public string $icon,
        public string $block_origin,
        public string $block_path,
        public array $settings = [],
        public array $capabilities = [],
        public array $scopes = [],
        public int $schema_version = self::DEFAULT_SCHEMA_VERSION,
        public ?string $replaces = null,
        public string $hosting_tier = self::HOSTING_TIER_REMOTE,
    ) {}

    /**
     * Reconstruct a descriptor from a manifest/JSON payload.
     *
     * @param  array<string, mixed>  $d
     * @param  array<int, SettingField>|null  $settings  pre-built SettingField DTOs (override $d['settings'])
     * @param  array<int, Scope>|null  $scopes  pre-built Scope enums (override $d['scopes'])
     */
    public static function fromArray(array $d, ?array $settings = null, ?array $scopes = null): self
    {
        $settings ??= array_map(
            static fn (array $f) => SettingField::fromArray($f),
            $d['settings'] ?? [],
        );

        $scopes ??= array_map(
            static fn (string|array $s) => is_string($s) ? Scope::from($s) : $s,
            $d['scopes'] ?? [],
        );

        return new self(
            type: (string) $d['type'],
            name: (string) $d['name'],
            description: (string) ($d['description'] ?? ''),
            category: (string) ($d['category'] ?? ''),
            icon: (string) ($d['icon'] ?? ''),
            block_origin: (string) $d['block_origin'],
            block_path: (string) $d['block_path'],
            settings: $settings,
            capabilities: array_values(array_map('strval', $d['capabilities'] ?? [])),
            scopes: $scopes,
            schema_version: (int) ($d['schema_version'] ?? self::DEFAULT_SCHEMA_VERSION),
            replaces: isset($d['replaces']) ? (string) $d['replaces'] : null,
            hosting_tier: (string) ($d['hosting_tier'] ?? self::HOSTING_TIER_REMOTE),
        );
    }

    /** @return array<string, mixed> JSON-friendly representation (input-equivalent to {@see fromArray()}) */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'icon' => $this->icon,
            'block_origin' => $this->block_origin,
            'block_path' => $this->block_path,
            'settings' => array_map(static fn (SettingField $f) => $f->toArray(), $this->settings),
            'capabilities' => array_values($this->capabilities),
            'scopes' => array_map(static fn (Scope $s) => $s->value, $this->scopes),
            'schema_version' => $this->schema_version,
            'replaces' => $this->replaces,
            'hosting_tier' => $this->hosting_tier,
        ];
    }

    /**
     * Host-side settings migrator hook (declared on the descriptor; the host
     * invokes the matching class method on the app's provider when a placed
     * node's schema_version is older than the current descriptor). Apps MAY
     * implement `onSettingsMigrate(int $fromVersion, array $settings): array`
     * on their provider; the host runs the chain on edit and render.
     *
     * @param  int  $fromVersion  schema_version the stored settings were written under
     * @param  array<string, mixed>  $settings  stored settings at $fromVersion
     * @return array<string, mixed> settings upgraded to {@see $schema_version}
     */
    public function onSettingsMigrate(int $fromVersion, array $settings): array
    {
        // Default: identity passthrough. Apps override on their provider.
        return $settings;
    }
}

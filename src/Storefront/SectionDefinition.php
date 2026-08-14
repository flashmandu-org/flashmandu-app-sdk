<?php

namespace Flashmandu\AppSdk\Storefront;

/**
 * A composable section/block an app contributes to the storefront.
 *
 * An app section is a composable element_tree built from EXISTING page-builder
 * elements (heading, text, button, image, ...). Because it uses only elements
 * the platform already knows how to render, it inherits 3-renderer parity
 * (blade / local-preview / PageBuilder) by construction — no new element type
 * and no renderer edits required.
 *
 * The `element_tree` shape itself is versioned via {@see $schemaVersion} /
 * {@see SectionSchema} — a public contract once third-party apps depend on
 * it, so a section declared against a schema this platform build doesn't
 * understand yet fails fast instead of rendering unpredictably.
 *
 * {@see $source} controls where the element_tree comes from:
 *  - {@see SOURCE_BLUEPRINT}: `element_tree` is declared inline in the
 *    manifest (or inlined from a `blueprint_file` at CLI push time). No app
 *    server involved, ever — the safest and default tier.
 *  - {@see SOURCE_STATIC}: the tree lives in a JSON file inside the app's
 *    static bundle, resolved by the host from platform-owned storage (no
 *    HTTP call to the app).
 *  - {@see SOURCE_ENDPOINT}: the tree is fetched from the app's own server at
 *    {@see $endpointPath} (Tier 2 — proxied, cached, circuit-broken by the
 *    host; not implemented by this SDK version beyond the descriptor shape).
 *
 * {@see $bindings} lets a section bind to a CMS module/collection without the
 * app writing a second query layer — the host resolves it through the SAME
 * binding provider the builder's native collection sections use, restricted
 * to a per-target attribute whitelist. Field paths are plain dotted strings
 * (`'entry.title'`) so relation paths (`'entry.venue.name'`,
 * `'entry.ticket_product.price'`) are representable from day one, even before
 * the host's module-relations feature ships — the shape does not change when
 * it does.
 */
final readonly class SectionDefinition
{
    /** element_tree is declared inline in the manifest (or inlined from blueprint_file at push time). */
    public const SOURCE_BLUEPRINT = 'blueprint';

    /** element_tree is a JSON file in the app's static bundle. */
    public const SOURCE_STATIC = 'static';

    /** element_tree is fetched from the app's own server (Tier 2). */
    public const SOURCE_ENDPOINT = 'endpoint';

    /** Sources a section may declare. */
    public const ALLOWED_SOURCES = [
        self::SOURCE_BLUEPRINT,
        self::SOURCE_STATIC,
        self::SOURCE_ENDPOINT,
    ];

    /**
     * @param  string  $source  one of {@see ALLOWED_SOURCES}; defaults to {@see SOURCE_BLUEPRINT}
     * @param  array<int, array<string, mixed>>|null  $elementTree  composable blueprint of existing elements; REQUIRED when $source is {@see SOURCE_BLUEPRINT} unless $view is set (legacy in-process Blade view section)
     * @param  array<int, array<string, mixed>>  $settings  merchant-configurable fields, same shape as {@see \Flashmandu\AppSdk\Storefront\EmbedBlockDefinition::$settings} (i.e. raw {@see \Flashmandu\AppSdk\Settings\SettingField} array payloads)
     * @param  array{module?: string, filter?: array<string, mixed>, order?: array<string, mixed>, limit?: int, group_by?: array{field: string, granularity?: string}}|null  $bindings  module/collection binding; field references inside filter/order (and any future relation-bound attribute) are plain dotted strings such as `entry.title` or `entry.venue.name` — resolved through the platform's binding provider and its per-target attribute whitelist, never a raw query
     * @param  string|null  $endpointPath  path on the app's server the host fetches the tree from; REQUIRED when $source is {@see SOURCE_ENDPOINT}
     * @param  int  $schemaVersion  element_tree schema version this section is built against; defaults to {@see SectionSchema::CURRENT}
     *
     * @throws \InvalidArgumentException  when $source is unrecognised, or a source's required field is missing (the offending field is named in the message)
     * @throws \RuntimeException  when $schemaVersion is newer than {@see SectionSchema::CURRENT}
     */
    public function __construct(
        public string $type,
        public string $name,
        public string $source = self::SOURCE_BLUEPRINT,
        public ?array $elementTree = null,
        public array $settings = [],
        public ?array $bindings = null,
        public ?string $endpointPath = null,
        public ?string $category = null,
        public ?string $view = null,
        public int $schemaVersion = SectionSchema::CURRENT,
    ) {
        SectionSchema::assertSupported($this->schemaVersion);
        self::assertValidSource($this->source, $this->elementTree, $this->endpointPath, $this->view);
    }

    /**
     * @throws \InvalidArgumentException  naming the offending field when a source's requirement is unmet
     */
    private static function assertValidSource(string $source, ?array $elementTree, ?string $endpointPath, ?string $view): void
    {
        if (! in_array($source, self::ALLOWED_SOURCES, true)) {
            throw new \InvalidArgumentException(
                "section source '{$source}' is not one of: " . implode(', ', self::ALLOWED_SOURCES)
            );
        }

        // A legacy view-rendered section (in-process Blade view, pre-dating the
        // source ladder) satisfies the blueprint tier without an element_tree.
        if ($source === self::SOURCE_BLUEPRINT && $elementTree === null && $view === null) {
            throw new \InvalidArgumentException(
                "section source 'blueprint' requires 'element_tree' (elementTree)"
            );
        }

        if ($source === self::SOURCE_ENDPOINT && ($endpointPath === null || $endpointPath === '')) {
            throw new \InvalidArgumentException(
                "section source 'endpoint' requires 'endpoint_path' (endpointPath)"
            );
        }
    }

    /**
     * Reconstruct a section definition from a manifest/JSON payload.
     *
     * A `blueprint_file` key, if present, is expected to have already been
     * resolved to `element_tree` by the caller (the CLI inlines it at push
     * time; the host reads whichever of the two is present) — this method
     * only reads `element_tree`.
     *
     * @param  array<string, mixed>  $d
     *
     * @throws \InvalidArgumentException  when $source is unrecognised, or a source's required field is missing
     * @throws \RuntimeException  when the declared schema_version is newer than {@see SectionSchema::CURRENT}
     */
    public static function fromArray(array $d): self
    {
        return new self(
            type: (string) $d['type'],
            name: (string) $d['name'],
            source: (string) ($d['source'] ?? self::SOURCE_BLUEPRINT),
            elementTree: $d['element_tree'] ?? null,
            settings: $d['settings'] ?? [],
            bindings: $d['bindings'] ?? null,
            endpointPath: isset($d['endpoint_path']) ? (string) $d['endpoint_path'] : null,
            category: isset($d['category']) ? (string) $d['category'] : null,
            view: isset($d['view']) ? (string) $d['view'] : null,
            schemaVersion: (int) ($d['schema_version'] ?? SectionSchema::CURRENT),
        );
    }

    /** @return array<string, mixed> JSON-friendly representation (input-equivalent to {@see fromArray()}) */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'name' => $this->name,
            'source' => $this->source,
            'element_tree' => $this->elementTree,
            'settings' => $this->settings,
            'bindings' => $this->bindings,
            'endpoint_path' => $this->endpointPath,
            'category' => $this->category,
            'view' => $this->view,
            'schema_version' => $this->schemaVersion,
        ];
    }
}

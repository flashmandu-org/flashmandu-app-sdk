<?php

namespace Flashmandu\AppSdk\Storefront;

/**
 * Version gate for the section schema — the `element_tree` contract a
 * {@see SectionDefinition} is built against.
 *
 * The moment the platform lets third-party apps contribute declarative
 * storefront sections, this schema becomes a public API consumed by apps the
 * platform does not control. {@see CURRENT} is the highest schema_version
 * this build of the platform understands; {@see assertSupported()} is the
 * single gate both apps and the host run before trusting a declared section,
 * so an app built against a newer schema fails loudly (in CI, at install, or
 * on render) instead of silently rendering something the host misinterprets.
 */
final class SectionSchema
{
    /** Highest section schema_version this platform build understands. */
    public const CURRENT = 1;

    /**
     * Assert that a declared section schema_version is understood by this
     * platform build.
     *
     * @throws \RuntimeException  when $version is newer than {@see CURRENT}
     */
    public static function assertSupported(int $version): void
    {
        if ($version > self::CURRENT) {
            throw new \RuntimeException(
                "section schema_version {$version} is newer than this platform supports (" . self::CURRENT . ')'
            );
        }
    }
}

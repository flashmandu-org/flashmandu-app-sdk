<?php

namespace Flashmandu\AppSdk\Testing;

use Flashmandu\AppSdk\Scope;
use Flashmandu\AppSdk\Settings\SettingField;
use Flashmandu\AppSdk\Storefront\EmbedBlockDefinition;

/**
 * Local test harness for an app's storefront embed block.
 *
 * Lets an app dev verify their {@see EmbedBlockDefinition} — the settings
 * schema, the bridge identity context the host posts to the iframe, and the
 * shape of the one-time signed storefront identity token — WITHOUT the
 * platform, database, or a server. Mirror of {@see AppTester} for the block
 * surface.
 *
 *     $block = new EmbedBlockDefinition(type: 'interactive-map', ...);
 *     $tester = EmbedBlockTester::for($block);
 *
 *     // 1. validate declared settings schema (select needs options/dynamic_options, ...)
 *     $errors = $tester->validateSchema();
 *     expect($errors)->toBeEmpty();
 *
 *     // 2. validate merchant-stored settings against the schema
 *     $errors = $tester->validateSettings(['map_id' => 'nyc']);
 *     expect($errors)->toBeEmpty();
 *
 *     // 3. build the bridge context payload exactly as the host will post it
 *     $ctx = $tester->bridgeContext(visitorId: 'v_abc', instanceId: 'node_42');
 *     expect($ctx['surface'])->toBe('storefront');
 *
 *     // 4. mint + verify a signed token in the host's shape
 *     $token = $tester->mintToken($ctx, secret: 'shared_secret');
 *     $payload = EmbedBlockTester::verifyToken($token, secret: 'shared_secret');
 *     expect($payload['block_type'])->toBe('interactive-map');
 *
 * Token shape mirrors the host's storefront identity token: a base64url payload
 * (version, nonce, payload) followed by an HMAC signature. The signature
 * algorithm matches the host's documented contract — sort payload keys,
 * rebuild the query string, compare with hash_equals. Apps implementing token
 * verification SHOULD test against this shape; apps SHOULD NOT ship the shared
 * secret in client code.
 */
class EmbedBlockTester
{
    /** Token envelope version this tester emits/consumes. */
    public const TOKEN_VERSION = 1;

    /** Storefront surface marker embedded in every bridge context + token payload. */
    public const SURFACE_STOREFRONT = 'storefront';

    private function __construct(
        private EmbedBlockDefinition $block,
    ) {}

    public static function for(EmbedBlockDefinition $block): self
    {
        return new self($block);
    }

    public function block(): EmbedBlockDefinition
    {
        return $this->block;
    }

    /**
     * Validate the block's declared settings schema itself (not the values).
     *
     * Catches: unknown field type, select without options AND without
     * dynamic_options, dynamic_options missing source/path, or an unsupported
     * dynamic_options source.
     *
     * @return array<int, string>  empty when valid, list of human-readable errors otherwise
     */
    public function validateSchema(): array
    {
        $errors = [];
        $validTypes = ['text', 'number', 'boolean', 'select', 'color', 'url'];

        foreach ($this->block->settings as $field) {
            if (! in_array($field->type, $validTypes, true)) {
                $errors[] = "Field `{$field->name}`: unknown type `{$field->type}`.";
            }

            if ($field->type === 'select') {
                $hasStatic = is_array($field->options) && ! empty($field->options);
                $hasDynamic = is_array($field->dynamic_options) && ! empty($field->dynamic_options['path'] ?? null);

                if (! $hasStatic && ! $hasDynamic) {
                    $errors[] = "Field `{$field->name}`: select requires `options` or `dynamic_options`.";
                }
            }

            if (is_array($field->dynamic_options)) {
                $source = $field->dynamic_options['source'] ?? null;
                $path = $field->dynamic_options['path'] ?? null;

                if ($source !== SettingField::DYNAMIC_SOURCE_APP) {
                    $errors[] = "Field `{$field->name}`: dynamic_options.source must be '"
                        . SettingField::DYNAMIC_SOURCE_APP . "' (got `" . var_export($source, true) . "`).";
                }

                if (! is_string($path) || trim($path) === '') {
                    $errors[] = "Field `{$field->name}`: dynamic_options.path is required.";
                }
            }
        }

        foreach ($this->block->capabilities as $cap) {
            if (! in_array($cap, EmbedBlockDefinition::ALLOWED_CAPABILITIES, true)) {
                $errors[] = "Capability `{$cap}` is not in the storefront allowlist: "
                    . implode(', ', EmbedBlockDefinition::ALLOWED_CAPABILITIES) . '.';
            }
        }

        if (! in_array($this->block->hosting_tier, EmbedBlockDefinition::ALLOWED_HOSTING_TIERS, true)) {
            $errors[] = "hosting_tier `{$this->block->hosting_tier}` is not one of: "
                . implode(', ', EmbedBlockDefinition::ALLOWED_HOSTING_TIERS) . '.';
        }

        if ($this->block->schema_version < 1) {
            $errors[] = 'schema_version must be >= 1.';
        }

        return $errors;
    }

    /**
     * Validate merchant-stored settings values against the declared schema.
     *
     * Catches: missing required fields, wrong type for value, select value not
     * in static options (dynamic-option validation is skipped here — the host
     * resolves those server-side).
     *
     * @param  array<string, mixed>  $values  stored settings to validate
     * @return array<int, string>  empty when valid
     */
    public function validateSettings(array $values): array
    {
        $errors = [];

        foreach ($this->block->settings as $field) {
            $present = array_key_exists($field->name, $values);
            $value = $values[$field->name] ?? $field->default;

            if ($field->required && (! $present || $value === null || $value === '')) {
                $errors[] = "Field `{$field->name}` is required.";
                continue;
            }

            if (! $present) {
                continue; // optional + absent is fine
            }

            $errors = array_merge($errors, $this->validateFieldValue($field, $value));
        }

        return $errors;
    }

    /**
     * Build the bridge identity context payload exactly as the host posts it
     * to the iframe on load (the storefront App Bridge `identity` capability).
     *
     * @param  string|null  $visitorId  pseudonymous per-session visitor id; the host NEVER sends profile_id to anonymous-visitor iframes
     * @param  string|null  $instanceId  the page-builder node id (opaque to the app, stable only within a page) — lets the app sync per-instance state
     * @param  array<string, mixed>  $extra  additional payload keys to merge (e.g. locale, settings)
     * @return array<string, mixed>
     */
    public function bridgeContext(
        ?string $visitorId = null,
        ?string $instanceId = null,
        array $extra = [],
    ): array {
        return array_merge([
            'surface' => self::SURFACE_STOREFRONT,
            'app_id' => null,
            'block_type' => $this->block->type,
            'schema_version' => $this->block->schema_version,
            'instance_id' => $instanceId,
            'visitor_id' => $visitorId,
            'capabilities' => array_values($this->block->capabilities),
            'scopes' => array_map(static fn (Scope $s) => $s->value, $this->block->scopes),
        ], $extra);
    }

    /**
     * Mint a signed storefront identity token in the host's documented shape.
     *
     * Shape: base64url(json{v, nonce, iat, exp, payload}) . '.' . base64url(hmac_sig).
     * Signature algorithm: sort payload keys recursively, rebuild as a query
     * string, HMAC-SHA256 over (envelope_body . secret), compare with
     * hash_equals on verify. This matches the partner-docs verification
     * algorithm so apps can test their verification routine locally.
     *
     * @param  array<string, mixed>  $context  context payload (e.g. from {@see bridgeContext()})
     * @param  string  $secret  shared secret between host and app (DO NOT ship in client code)
     * @param  int  $ttlSeconds  token lifetime; defaults to 60s (host uses single-use + short TTL)
     */
    public function mintToken(array $context, string $secret, int $ttlSeconds = 60): string
    {
        $now = time();
        $body = [
            'v' => self::TOKEN_VERSION,
            'nonce' => bin2hex(random_bytes(16)),
            'iat' => $now,
            'exp' => $now + max(1, $ttlSeconds),
            'payload' => $context,
        ];

        $bodyB64 = self::base64UrlEncode(json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $sig = hash_hmac('sha256', self::canonicalSignatureInput($body['payload'], $bodyB64), $secret, true);

        return $bodyB64 . '.' . self::base64UrlEncode($sig);
    }

    /**
     * Verify a signed storefront identity token and return its payload.
     *
     * @return array<string, mixed>  the verified payload
     * @throws \RuntimeException  on malformed token, bad signature, version mismatch, or expiry
     */
    public static function verifyToken(string $token, string $secret): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            throw new \RuntimeException('Malformed token: expected body.signature.');
        }

        [$bodyB64, $sigB64] = $parts;
        $bodyJson = self::base64UrlDecode($bodyB64);
        $body = json_decode($bodyJson, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($body) || ! isset($body['payload'], $body['v'], $body['exp'])) {
            throw new \RuntimeException('Malformed token body.');
        }

        if ((int) $body['v'] !== self::TOKEN_VERSION) {
            throw new \RuntimeException('Unsupported token version.');
        }

        $expectedSig = hash_hmac(
            'sha256',
            self::canonicalSignatureInput($body['payload'], $bodyB64),
            $secret,
            true,
        );
        $actualSig = self::base64UrlDecode($sigB64);

        if (! hash_equals($expectedSig, $actualSig)) {
            throw new \RuntimeException('Invalid token signature.');
        }

        if (time() > (int) $body['exp']) {
            throw new \RuntimeException('Token expired.');
        }

        return $body['payload'];
    }

    /**
     * @return array<int, string>
     */
    private function validateFieldValue(SettingField $field, mixed $value): array
    {
        $errors = [];
        $name = $field->name;

        if ($value === null) {
            return $errors; // null handled by required check above
        }

        switch ($field->type) {
            case 'text':
            case 'color':
            case 'url':
                if (! is_string($value)) {
                    $errors[] = "Field `{$name}` ({$field->type}): expected string.";
                }
                break;
            case 'number':
                if (! is_int($value) && ! is_float($value) && ! (is_string($value) && is_numeric($value))) {
                    $errors[] = "Field `{$name}` (number): expected numeric.";
                }
                break;
            case 'boolean':
                if (! is_bool($value)) {
                    $errors[] = "Field `{$name}` (boolean): expected bool.";
                }
                break;
            case 'select':
                if (is_array($field->options) && ! isset($field->options[$value]) && ! in_array($value, array_keys($field->options), true)) {
                    $errors[] = "Field `{$name}` (select): value `" . var_export($value, true)
                        . "` is not in the static options list.";
                }
                break;
        }

        return $errors;
    }

    /**
     * Canonical signed-input string: base64url(body) . "\n" . sorted-query(payload).
     * Mirrors the partner-docs verification algorithm (sort keys, rebuild query, hash_equals).
     *
     * @param  array<string, mixed>  $payload
     */
    private static function canonicalSignatureInput(array $payload, string $bodyB64): string
    {
        return $bodyB64 . "\n" . self::buildSortedQuery($payload);
    }

    /**
     * Recursively sort payload keys and rebuild as a urlencoded query string.
     *
     * @param  array<string, mixed>|scalar|null  $value
     */
    private static function buildSortedQuery(mixed $value, string $prefix = ''): string
    {
        if (! is_array($value)) {
            return $prefix . '=' . urlencode((string) $value);
        }

        ksort($value, SORT_STRING);
        $parts = [];
        foreach ($value as $k => $v) {
            $key = $prefix === '' ? (string) $k : $prefix . '[' . $k . ']';
            $parts[] = self::buildSortedQuery($v, $key);
        }

        return implode('&', $parts);
    }

    private static function base64UrlEncode(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $b64): string
    {
        $pad = strlen($b64) % 4;
        if ($pad !== 0) {
            $b64 .= str_repeat('=', 4 - $pad);
        }

        $decoded = base64_decode(strtr($b64, '-_', '+/'), true);
        if ($decoded === false) {
            throw new \RuntimeException('Invalid base64url input.');
        }

        return $decoded;
    }
}

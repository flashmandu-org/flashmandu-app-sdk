<?php

namespace Flashmandu\AppSdk\Webhooks;

use Flashmandu\AppSdk\Hooks\HookName;

/**
 * Verifies an inbound platform webhook.
 *
 * Every PHP app was hand-rolling this. The signature is an HMAC-SHA256 over
 * `"{timestamp}.{rawBody}"` keyed by the install's `webhook_secret`:
 *
 *     signature = hash_hmac('sha256', $timestamp.'.'.$rawBody, $webhookSecret)
 *
 * Headers the platform sends:
 *
 *   - `X-App-Event`      the hook name (see {@see HookName})
 *   - `X-App-Signature`  hex HMAC as above
 *   - `X-App-Timestamp`  unix seconds, also the first half of the signed string
 *   - `X-App-Delivery`   per-ATTEMPT id — the dedupe key
 *
 * **Feed it the RAW body.** Re-serializing the JSON changes bytes (key order,
 * whitespace, unicode escaping) and the HMAC will not match. Read
 * `php://input` verbatim, verify, and only then decode.
 *
 * **Deliveries are at-least-once.** The retry ladder is 6 attempts — 1m, 5m,
 * 30m, 2h, 8h — after which the delivery is dead-lettered. Each attempt gets
 * its own `X-App-Delivery`, so record it and treat a repeat as a duplicate.
 * Payloads also carry a per-resource monotonic `sequence`: drop an update whose
 * sequence is below the one already applied, and out-of-order retries stop
 * being a correctness problem.
 *
 * Respond within 10 seconds. Accept with 202 and continue asynchronously if
 * the work is slow — a timeout is a failed attempt and costs you a retry.
 *
 * Framework-free by design: it takes a raw body and a header map, so it drops
 * into Laravel, Symfony or a bare PHP endpoint unchanged.
 */
final readonly class Verifier
{
    /**
     * @param  string  $webhookSecret  The install's `webhook_secret`, delivered once at install.
     * @param  int  $maxSkewSeconds  Reject timestamps further from now than this, in either direction.
     */
    public function __construct(
        private string $webhookSecret,
        private int $maxSkewSeconds = 300,
    ) {}

    /**
     * Verify a delivery and return a typed result.
     *
     * Header lookup is case-insensitive, because PSR-7, `getallheaders()` and
     * Laravel's request bag each normalize casing differently and an app should
     * not have to care which one it is holding.
     *
     * @param  array<string, string|array<int, string>|null>  $headers
     */
    public function verify(string $rawBody, array $headers, ?int $now = null): VerificationResult
    {
        $signature = $this->header($headers, 'X-App-Signature');
        $timestamp = $this->header($headers, 'X-App-Timestamp');
        $event = $this->header($headers, 'X-App-Event');
        $delivery = $this->header($headers, 'X-App-Delivery');

        if ($signature === null || $timestamp === null) {
            return VerificationResult::fail('missing X-App-Signature or X-App-Timestamp', $event, $delivery);
        }

        if (! preg_match('/^\d+$/', $timestamp)) {
            return VerificationResult::fail('X-App-Timestamp is not a unix timestamp', $event, $delivery);
        }

        $age = abs(($now ?? time()) - (int) $timestamp);
        if ($age > $this->maxSkewSeconds) {
            return VerificationResult::fail(
                "timestamp skew {$age}s exceeds the {$this->maxSkewSeconds}s window",
                $event,
                $delivery,
            );
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$rawBody, $this->webhookSecret);
        if (! hash_equals($expected, $signature)) {
            return VerificationResult::fail('signature mismatch', $event, $delivery);
        }

        return VerificationResult::pass($event, $delivery, (int) $timestamp);
    }

    /**
     * Verify, then decode the JSON body.
     *
     * Returns null when verification fails or the body is not a JSON object —
     * so a caller can branch once instead of checking two things. Use
     * {@see self::verify()} when the failure reason matters (it usually does,
     * for logs).
     *
     * @param  array<string, string|array<int, string>|null>  $headers
     * @return array<string, mixed>|null
     */
    public function verifyAndDecode(string $rawBody, array $headers, ?int $now = null): ?array
    {
        if (! $this->verify($rawBody, $headers, $now)->ok) {
            return null;
        }

        $decoded = json_decode($rawBody, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Compute the signature the platform would send for a body. Intended for
     * TESTS and local fixtures — never call it on an inbound request and
     * compare by hand; that is what {@see self::verify()} is for.
     */
    public function sign(string $rawBody, int $timestamp): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$rawBody, $this->webhookSecret);
    }

    /**
     * Case-insensitive header lookup that also flattens the array-of-values
     * shape PSR-7 uses.
     *
     * @param  array<string, string|array<int, string>|null>  $headers
     */
    private function header(array $headers, string $name): ?string
    {
        $needle = strtolower($name);

        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) !== $needle) {
                continue;
            }
            if (is_array($value)) {
                $value = $value[0] ?? null;
            }
            if (! is_string($value) || $value === '') {
                return null;
            }

            return $value;
        }

        return null;
    }
}

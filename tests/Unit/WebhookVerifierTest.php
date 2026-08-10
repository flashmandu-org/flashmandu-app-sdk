<?php

use Flashmandu\AppSdk\Hooks\HookName;
use Flashmandu\AppSdk\Webhooks\Verifier;

const SECRET = 'whsec_test_secret';

function signedHeaders(string $body, int $timestamp, string $secret = SECRET): array
{
    return [
        'X-App-Event' => 'order.created',
        'X-App-Signature' => hash_hmac('sha256', $timestamp.'.'.$body, $secret),
        'X-App-Timestamp' => (string) $timestamp,
        'X-App-Delivery' => 'dlv_123',
    ];
}

it('accepts a correctly signed delivery', function (): void {
    $body = '{"event":"order.created","payload":{"id":1}}';
    $now = 1_760_000_000;

    $result = (new Verifier(SECRET))->verify($body, signedHeaders($body, $now), $now);

    expect($result->ok)->toBeTrue()
        ->and($result->reason)->toBeNull()
        ->and($result->event)->toBe('order.created')
        ->and($result->deliveryId)->toBe('dlv_123')
        ->and($result->timestamp)->toBe($now);
});

it('rejects a body that was tampered with after signing', function (): void {
    $now = 1_760_000_000;
    $headers = signedHeaders('{"a":1}', $now);

    $result = (new Verifier(SECRET))->verify('{"a":2}', $headers, $now);

    expect($result->ok)->toBeFalse()
        ->and($result->reason)->toBe('signature mismatch');
});

it('rejects a signature made with the wrong secret', function (): void {
    $body = '{"a":1}';
    $now = 1_760_000_000;

    $result = (new Verifier(SECRET))->verify($body, signedHeaders($body, $now, 'other'), $now);

    expect($result->ok)->toBeFalse();
});

it('rejects a timestamp outside the skew window, in both directions', function (int $offset): void {
    $body = '{"a":1}';
    $now = 1_760_000_000;
    $headers = signedHeaders($body, $now + $offset);

    $result = (new Verifier(SECRET))->verify($body, $headers, $now);

    expect($result->ok)->toBeFalse()
        ->and($result->reason)->toContain('timestamp skew');
})->with([601, -601]);

it('accepts a timestamp at the edge of the window', function (): void {
    $body = '{"a":1}';
    $now = 1_760_000_000;
    $headers = signedHeaders($body, $now - 300);

    expect((new Verifier(SECRET))->verify($body, $headers, $now)->ok)->toBeTrue();
});

it('honours a custom skew window', function (): void {
    $body = '{"a":1}';
    $now = 1_760_000_000;
    $headers = signedHeaders($body, $now - 400);

    expect((new Verifier(SECRET, maxSkewSeconds: 600))->verify($body, $headers, $now)->ok)->toBeTrue();
});

it('rejects a delivery with no signature or timestamp', function (): void {
    $result = (new Verifier(SECRET))->verify('{}', ['X-App-Event' => 'order.created']);

    expect($result->ok)->toBeFalse()
        ->and($result->reason)->toContain('missing');
});

it('rejects a non-numeric timestamp', function (): void {
    $result = (new Verifier(SECRET))->verify('{}', [
        'X-App-Signature' => 'deadbeef',
        'X-App-Timestamp' => 'yesterday',
    ]);

    expect($result->reason)->toContain('not a unix timestamp');
});

it('reads headers case-insensitively', function (): void {
    $body = '{"a":1}';
    $now = 1_760_000_000;
    $headers = array_change_key_case(signedHeaders($body, $now), CASE_LOWER);

    expect((new Verifier(SECRET))->verify($body, $headers, $now)->ok)->toBeTrue();
});

it('flattens the PSR-7 array-of-values header shape', function (): void {
    $body = '{"a":1}';
    $now = 1_760_000_000;
    $headers = array_map(
        static fn (string $value): array => [$value],
        signedHeaders($body, $now),
    );

    expect((new Verifier(SECRET))->verify($body, $headers, $now)->ok)->toBeTrue();
});

it('surfaces the event and delivery id even on a failed verification, for logs', function (): void {
    $now = 1_760_000_000;
    $result = (new Verifier(SECRET))->verify('tampered', signedHeaders('{"a":1}', $now), $now);

    expect($result->ok)->toBeFalse()
        ->and($result->event)->toBe('order.created')
        ->and($result->deliveryId)->toBe('dlv_123');
});

it('maps the event header to a typed hook case', function (): void {
    $body = '{"a":1}';
    $now = 1_760_000_000;

    expect((new Verifier(SECRET))->verify($body, signedHeaders($body, $now), $now)->hook())
        ->toBe(HookName::OrderCreated);
});

it('returns null for a hook name the SDK does not know yet', function (): void {
    $body = '{"a":1}';
    $now = 1_760_000_000;
    $headers = signedHeaders($body, $now);
    $headers['X-App-Event'] = 'some.future.event';

    expect((new Verifier(SECRET))->verify($body, $headers, $now)->hook())->toBeNull();
});

it('maps a schedule tick to a typed case, so no app hand-writes the string', function (): void {
    $body = '{"schedule":"nightly-reconcile","profile_id":9,"scheduled_at":"2026-08-10T02:00:00Z"}';
    $now = 1_760_000_000;
    $headers = signedHeaders($body, $now);
    $headers['X-App-Event'] = 'schedule.tick';

    $result = (new Verifier(SECRET))->verify($body, $headers, $now);

    expect($result->ok)->toBeTrue()
        ->and($result->hook())->toBe(HookName::ScheduleTick)
        ->and($result->hook()?->isDeclarationDriven())->toBeTrue();
});

it('decodes a verified body', function (): void {
    $body = '{"event":"order.created","payload":{"id":7}}';
    $now = 1_760_000_000;

    expect((new Verifier(SECRET))->verifyAndDecode($body, signedHeaders($body, $now), $now))
        ->toBe(['event' => 'order.created', 'payload' => ['id' => 7]]);
});

it('decodes to null when verification fails', function (): void {
    $now = 1_760_000_000;

    expect((new Verifier(SECRET))->verifyAndDecode('{"a":2}', signedHeaders('{"a":1}', $now), $now))
        ->toBeNull();
});

it('decodes to null for a verified body that is not a JSON object', function (): void {
    $body = 'not json';
    $now = 1_760_000_000;

    expect((new Verifier(SECRET))->verifyAndDecode($body, signedHeaders($body, $now), $now))->toBeNull();
});

it('round-trips its own sign() helper', function (): void {
    $verifier = new Verifier(SECRET);
    $body = '{"a":1}';
    $now = 1_760_000_000;

    $result = $verifier->verify($body, [
        'X-App-Signature' => $verifier->sign($body, $now),
        'X-App-Timestamp' => (string) $now,
    ], $now);

    expect($result->ok)->toBeTrue();
});

it('signs over the RAW bytes, so a re-serialized body fails', function (): void {
    // Pretty-printed on the wire; a decode/encode round-trip drops the
    // whitespace and the HMAC no longer matches.
    $raw = "{\n    \"b\": 1,\n    \"a\": 2\n}";
    $now = 1_760_000_000;
    $headers = signedHeaders($raw, $now);

    /** @var array<string, mixed> $decoded */
    $decoded = json_decode($raw, true);
    $reserialized = json_encode($decoded);

    expect((new Verifier(SECRET))->verify($reserialized, $headers, $now)->ok)->toBeFalse();
});

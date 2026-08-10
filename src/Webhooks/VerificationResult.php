<?php

namespace Flashmandu\AppSdk\Webhooks;

use Flashmandu\AppSdk\Hooks\HookName;

/**
 * The outcome of {@see Verifier::verify()}.
 *
 * A value object rather than a bare bool so the failure `reason` survives into
 * the app's logs — "webhook rejected" with no reason is the single most
 * annoying thing to debug against a signed transport.
 *
 * `event` and `deliveryId` are populated even on failure, because they come
 * from headers that are readable before the signature is checked and are what
 * makes a rejection traceable against the platform's own delivery log. They
 * are NOT authenticated on a failed result — never act on them.
 */
final readonly class VerificationResult
{
    /**
     * @param  bool  $ok  True when the signature and timestamp both check out.
     * @param  string|null  $reason  Why verification failed. Null on success.
     * @param  string|null  $event  `X-App-Event` — the hook name.
     * @param  string|null  $deliveryId  `X-App-Delivery` — per-attempt id; the dedupe key.
     * @param  int|null  $timestamp  Verified `X-App-Timestamp`, unix seconds. Null on failure.
     */
    private function __construct(
        public bool $ok,
        public ?string $reason = null,
        public ?string $event = null,
        public ?string $deliveryId = null,
        public ?int $timestamp = null,
    ) {}

    public static function pass(?string $event, ?string $deliveryId, int $timestamp): self
    {
        return new self(ok: true, event: $event, deliveryId: $deliveryId, timestamp: $timestamp);
    }

    public static function fail(string $reason, ?string $event = null, ?string $deliveryId = null): self
    {
        return new self(ok: false, reason: $reason, event: $event, deliveryId: $deliveryId);
    }

    /** The event as a typed enum case, when the host name is one the SDK knows. */
    public function hook(): ?HookName
    {
        return $this->event === null
            ? null
            : HookName::tryFrom($this->event);
    }
}

<?php

namespace Flashmandu\AppSdk\Testing;

use Flashmandu\AppSdk\AppContext;
use Flashmandu\AppSdk\AppManifest;
use Flashmandu\AppSdk\AppProvider;
use Flashmandu\AppSdk\HookRegistrar;

/**
 * Local test harness for app developers.
 *
 * Run an app's hooks in isolation — install, emit events, pipe filters, inspect
 * — with NO platform, NO database, NO server. App devs use this in their own
 * Pest/PHPUnit suite to verify their app reacts correctly before publishing.
 *
 *     $tester = AppTester::for(Acme\Loyalty\Manifest::class);
 *     $tester->install()->emit('order.status.changed', ['status' => 'paid']);
 *     expect($tester->wasFired('order.status.changed'))->toBeTrue();
 *
 * It implements HookRegistrar to collect the app's declared listeners/filters,
 * and mirrors the platform's per-profile gating: nothing fires before install(),
 * matching how the real engine only runs apps a merchant installed.
 */
class AppTester implements HookRegistrar
{
    /** @var array<string, array<int, callable>> */
    public array $listeners = [];

    /** @var array<string, array<int, callable>> */
    public array $filters = [];

    /** @var array<string, int> */
    private array $fired = [];

    private bool $installed = false;

    private function __construct(
        private AppProvider $provider,
    ) {
        $provider->manifest()->hooks?->register($this);
    }

    public static function for(AppProvider|string $provider): self
    {
        $provider = is_string($provider) ? new $provider : $provider;

        return new self($provider);
    }

    public function manifest(): AppManifest
    {
        return $this->provider->manifest();
    }

    public function install(): self
    {
        $this->installed = true;

        return $this;
    }

    public function uninstall(): self
    {
        $this->installed = false;

        return $this;
    }

    public function isInstalled(): bool
    {
        return $this->installed;
    }

    /**
     * Fire an event to the app's listeners — a no-op until install().
     *
     * @param  array<string, mixed>  $payload
     */
    public function emit(string $event, array $payload = []): self
    {
        if (! $this->installed) {
            return $this;
        }

        $this->fired[$event] = ($this->fired[$event] ?? 0) + 1;

        $context = $this->context();
        foreach ($this->listeners[$event] ?? [] as $listener) {
            $listener($payload, $context);
        }

        return $this;
    }

    /**
     * Pipe a value through the app's mutators for a filter hook.
     */
    public function runFilter(string $hook, mixed $value): mixed
    {
        if (! $this->installed) {
            return $value;
        }

        $context = $this->context();
        foreach ($this->filters[$hook] ?? [] as $mutator) {
            $value = $mutator($value, $context);
        }

        return $value;
    }

    /** How many times an event was emitted (after install). */
    public function fired(string $event): int
    {
        return $this->fired[$event] ?? 0;
    }

    public function wasFired(string $event): bool
    {
        return $this->fired($event) > 0;
    }

    // ── HookRegistrar (collects the app's declarations) ────────────────

    public function on(string $event, callable $listener): void
    {
        $this->listeners[$event][] = $listener;
    }

    public function filter(string $hook, callable $mutator): void
    {
        $this->filters[$hook][] = $mutator;
    }

    private function context(): AppContext
    {
        $manifest = $this->provider->manifest();

        return new AppContext(
            profile: null,
            appId: $manifest->id,
            scopes: $manifest->scopes,
        );
    }
}

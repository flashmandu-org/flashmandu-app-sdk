<?php

namespace Flashmandu\AppSdk\Admin;

/**
 * The chrome a page contributes to the host command bar: breadcrumbs, actions
 * and a dirty flag.
 *
 * This is the PHP-native twin of the bridge's `SET_PAGE` message. An iframe
 * app posts that payload; a PHP-native app returns one of these. The host
 * renders both through the same command-bar slot, so the two app types are
 * visually indistinguishable — which is the whole point.
 *
 * **Render nothing yourself.** A page that draws its own desktop `<h1>`,
 * breadcrumb trail or action row is what makes an app look bolted on. Declare
 * it here and let the host draw it.
 *
 * Caps mirror the protocol, because the host drops an over-cap payload WHOLE
 * rather than trusting a partial chrome: 5 crumbs, 4 actions.
 */
final readonly class PageChrome
{
    /**
     * @param  array<int, Breadcrumb>  $crumbs  Root → current page; the last entry is the current page and carries no route.
     * @param  array<int, PageAction>  $actions  Command-bar buttons. Replaced by the host's native Save/Discard while `$dirty`.
     * @param  bool  $dirty  True when the page has unsaved changes.
     */
    public function __construct(
        public array $crumbs = [],
        public array $actions = [],
        public bool $dirty = false,
    ) {}

    /** The last crumb — the current page — or null when the trail is empty. */
    public function current(): ?Breadcrumb
    {
        return $this->crumbs === [] ? null : $this->crumbs[array_key_last($this->crumbs)];
    }

    /** Look up an action by the id the host echoes back on click. */
    public function action(string $id): ?PageAction
    {
        foreach ($this->actions as $action) {
            if ($action->id === $id) {
                return $action;
            }
        }

        return null;
    }
}

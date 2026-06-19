<?php

use Flashmandu\AppSdk\AppManifest;
use Flashmandu\AppSdk\AppProvider;

it('an app provider supplies its manifest', function (): void {
    $provider = new class implements AppProvider
    {
        public function manifest(): AppManifest
        {
            return new AppManifest(id: 'acme/widget', name: 'Widget', version: '1.0.0');
        }
    };

    $manifest = $provider->manifest();

    expect($manifest)->toBeInstanceOf(AppManifest::class)
        ->and($manifest->id)->toBe('acme/widget');
});

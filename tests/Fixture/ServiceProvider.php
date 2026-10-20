<?php

declare(strict_types=1);

namespace Igniter\Storefront\Tests\Fixture;

use Igniter\Storefront\Config;
use Igniter\Storefront\Storefront;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Override;

/**
 * A tiny theme so the storefront suite does not depend on Glass.
 */
class ServiceProvider extends BaseServiceProvider
{
    #[Override]
    public function register(): void
    {
        Storefront::register(new Config(
            theme: 'storefront-fixture',
            rootView: 'fixture::app',
            defaultBackground: '#2a1a30',
            defaultRing: '#f6d7c3',
            defaultFontUrl: 'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap',
            defaultFontFamily: 'Plus Jakarta Sans',
            pwaDirectory: __DIR__.'/resources/pwa',
            langNamespace: 'storefront.fixture',
        ));
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/resources/lang', 'storefront.fixture');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'fixture');
    }
}

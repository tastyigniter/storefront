<?php

declare(strict_types=1);

namespace Igniter\Storefront\Tests;

use Igniter\Local\Models\Location;
use Igniter\Main\Classes\ThemeManager;
use Igniter\Main\Http\Middleware\CheckInitialSetup;
use Igniter\Main\Models\Theme;
use Igniter\Storefront\Tests\Fixture\ServiceProvider as FixtureServiceProvider;
use Igniter\System\Models\Country;
use Igniter\System\Models\Currency;
use Igniter\User\Models\CustomerGroup;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\File;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Override;

abstract class TestCase extends \SamPoyigi\Testbench\TestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        // Default models are cached statically and outlive each test's rolled-back transaction.
        foreach ([Location::class, Country::class, Currency::class, CustomerGroup::class, Theme::class] as $model) {
            $model::clearDefaultModels();
        }

        // The root view expects the theme's published assets folder.
        File::ensureDirectoryExists(public_path('vendor/storefront-fixture'));

        $this->withoutMiddleware(CheckInitialSetup::class);
        $this->withoutVite();
    }

    #[Override]
    protected function getPackageProviders($app)
    {
        $app->booted(function(Application $app): void {
            $themeManager = $app[ThemeManager::class];
            $theme = $themeManager->loadTheme(__DIR__.'/Fixture');
            $themeManager->bootTheme($theme);
        });

        return array_merge(parent::getPackageProviders($app), [
            InertiaServiceProvider::class,
            FixtureServiceProvider::class,
        ]);
    }

    #[Override]
    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);

        $app['config']->set('igniter-system.defaultTheme', 'storefront-fixture');
        // assertInertia() checks the page component exists in the theme.
        $app['config']->set('inertia.pages.paths', [__DIR__.'/Fixture/resources/js/pages']);
        $app['config']->set('inertia.pages.extensions', ['vue']);
    }
}

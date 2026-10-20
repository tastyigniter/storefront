<?php

declare(strict_types=1);

namespace Igniter\Storefront\Tests;

use Igniter\System\Classes\LanguageManager;
use Igniter\System\Models\Language;
use Inertia\Testing\AssertableInertia;

it('registers the storefront language strings', function(): void {
    expect(lang('storefront.fixture::default.cart.title'))->toBe('Your basket')
        ->and(resolve(LanguageManager::class)->namespaces())->toHaveKey('storefront.fixture');
});

it('lists the strings for editing in the admin', function(): void {
    $package = collect(resolve(LanguageManager::class)->listLocalePackages('en'))->firstWhere('code', 'storefront.fixture');

    expect($package)->not->toBeNull()
        ->and($package->files)->toHaveCount(1);
});

it('shares the strings with the Vue pages', function(): void {
    currentLocation();

    $this->get(route('igniter.theme.contact'))
        ->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page
            ->where('lang.cart.title', 'Your basket')
            ->where('lang.alert.contact_sent', 'Thanks, your message is on its way.'));
});

it('uses lines edited in the admin', function(): void {
    currentLocation();
    Language::query()->where('code', 'en')->firstOrFail()
        ->updateTranslation('default', 'storefront.fixture', 'cart.title', 'Your bag');
    app('translator')->setLoaded([]);

    expect(lang('storefront.fixture::default.cart.title'))->toBe('Your bag');

    $this->get(route('igniter.theme.contact'))
        ->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page->where('lang.cart.title', 'Your bag'));
});

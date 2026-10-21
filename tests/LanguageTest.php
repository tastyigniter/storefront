<?php

declare(strict_types=1);

namespace Igniter\Storefront\Tests;

use Igniter\Storefront\Storefront;
use Igniter\System\Classes\LanguageManager;
use Igniter\System\Models\Language;
use Inertia\Testing\AssertableInertia;

it('resolves a missing theme line to its key', function(): void {
    expect(Storefront::lang('cart.title'))->toBe('cart.title')
        ->and(resolve(LanguageManager::class)->namespaces())->toHaveKey('storefront.fixture');
});

it('lists the theme namespace when it has no language file', function(): void {
    $package = collect(resolve(LanguageManager::class)->listLocalePackages('en'))->firstWhere('code', 'storefront.fixture');

    expect($package)->not->toBeNull()
        ->and($package->files)->toBe([]);
});

it('uses lines edited in the admin', function(): void {
    currentLocation();
    Language::query()->where('code', 'en')->firstOrFail()
        ->updateTranslation('default', 'storefront.fixture', 'cart.title', 'Your bag');
    app('translator')->setLoaded([]);

    expect(Storefront::lang('cart.title'))->toBe('Your bag');

    $this->get(route('igniter.theme.contact'))
        ->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page->where('lang.cart.title', 'Your bag'));
});

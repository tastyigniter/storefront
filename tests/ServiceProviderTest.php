<?php

declare(strict_types=1);

namespace Igniter\Storefront\Tests;

use Igniter\Main\Classes\ThemeManager;
use Igniter\PayRegister\Models\PaymentProfile;
use Igniter\User\Models\Customer;
use Igniter\Storefront\Storefront;
use Igniter\Storefront\Support\StorefrontRoutes;
use Illuminate\Support\Facades\File;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Mockery;

it('registers the storefront routes', function(): void {
    expect(route('igniter.theme.home', absolute: false))->toBe('/')
        ->and(route('igniter.theme.checkout.success', ['hash' => 'abc'], false))->toBe('/checkout/success/abc')
        ->and(StorefrontRoutes::templates('igniter.theme.checkout.store'))->toBe('/checkout')
        ->and(StorefrontRoutes::templates('igniter.theme.account.login.store'))->toBe('/login')
        ->and(StorefrontRoutes::templates('igniter.theme.cart.update'))->toBe('/cart/{rowId}');
});

it('resolves menu links before the storefront routes are registered', function(): void {
    expect(StorefrontRoutes::url('igniter.theme.order.current'))->toBe(route('igniter.theme.order.current', absolute: false))
        ->and(StorefrontRoutes::url('igniter.theme.missing'))->toBe('/');

    $router = app('router');
    $uri = config('igniter-routes.uri');
    app()->instance('router', new Router(app('events'), app()));
    Route::clearResolvedInstances();
    config(['igniter-routes.uri' => 'shop']);

    try {
        $main = require __DIR__.'/Fixture/resources/meta/menus/main-menu.php';
        $footer = require __DIR__.'/Fixture/resources/meta/menus/footer-menu.php';
    } finally {
        config(['igniter-routes.uri' => $uri]);
        app()->instance('router', $router);
        Route::clearResolvedInstances();
    }

    expect($main['items'][0]['url'])->toBe('/shop/order')
        ->and($main['items'][1]['url'])->toBe('/shop/locations')
        ->and($main['items'][2]['url'])->toBe('/shop/book-table')
        ->and($main['items'][3]['url'])->toBe('/shop/contact')
        ->and($footer['items'][0]['items'][0]['url'])->toBe('/shop/order')
        ->and($footer['items'][0]['items'][1]['url'])->toBe('/shop/book-table')
        ->and($footer['items'][0]['items'][2]['url'])->toBe('/shop/contact');
});

it('skips the storefront routes when another theme is active', function(): void {
    $manager = Mockery::mock(resolve(ThemeManager::class))->makePartial();
    $manager->shouldReceive('getActiveTheme')->andReturnNull();
    app()->instance(ThemeManager::class, $manager);
    $router = Mockery::mock(Router::class);
    $router->shouldNotReceive('middleware');

    Event::dispatch('igniter.main.registerRoutes', [$router]);

    expect(true)->toBeTrue();
});

it('removes saved cards before a customer is deleted', function(): void {
    $customer = Customer::factory()->create();
    $profile = PaymentProfile::factory()->create(['customer_id' => $customer->getKey()]);

    Event::dispatch('igniter.user.beforeDeleteAccount', [$customer]);

    expect(PaymentProfile::find($profile->getKey()))->toBeNull();
});

it('hands the Inertia payload to the page view', function(): void {
    currentLocation();

    $this->get(route('igniter.theme.contact'))
        ->assertOk()
        ->assertSee('Contact\/Index', false);
});

<?php

declare(strict_types=1);

namespace Igniter\Storefront\Tests\Support;

use Igniter\Cart\Classes\CartManager;
use Igniter\Local\Facades\Location;
use Igniter\Local\Models\Location as LocationModel;
use Igniter\Storefront\Support\CheckoutStatus;
use Igniter\Storefront\Support\Money;
use Mockery;
use RuntimeException;

beforeEach(function(): void {
    $this->travelTo(now()->setTime(12, 0));
    $this->location = currentLocation();
});

it('asks for a location when there is none', function(): void {
    LocationModel::query()->delete();
    Location::resetSession();
    (fn(): null => $this->model = null)->call(resolve('location'));

    expect(CheckoutStatus::current())->toBe([
        'blocked' => true,
        'reason' => 'Choose a location to order from.',
        'minimum' => null,
    ]);
});

it('blocks an empty basket without a reason and shows the minimum', function(): void {
    locationSettings($this->location, 'delivery', ['min_order_amount' => 20]);

    expect(CheckoutStatus::current())->toBe([
        'blocked' => true,
        'reason' => null,
        'minimum' => Money::label(20),
    ]);
});

it('lets an open location with enough in the basket check out', function(): void {
    addMenuToCart();

    expect(CheckoutStatus::current())->toBe(['blocked' => false, 'reason' => null, 'minimum' => null]);
});

it('explains the minimum order', function(): void {
    locationSettings($this->location, 'delivery', ['min_order_amount' => 100]);
    addMenuToCart();

    expect(CheckoutStatus::current('reason'))
        ->toBe('Add '.Money::label(90).' more to reach the '.Money::label(100).' minimum for delivery.');
});

it('suggests scheduling when the kitchen is closed but opens later', function(): void {
    addMenuToCart();
    $location = spyLocation();
    $location->shouldReceive('checkOrderTime')->andReturnFalse();
    $location->shouldReceive('hasLaterSchedule')->andReturnTrue();

    expect(CheckoutStatus::current('reason'))
        ->toBe("We're closed for that time. Change your order details to schedule it for when we're open.");
});

it('says when the kitchen is closed', function(): void {
    addMenuToCart();
    $location = spyLocation();
    $location->shouldReceive('checkOrderTime')->andReturnFalse();
    $location->shouldReceive('hasLaterSchedule')->andReturnFalse();

    expect(CheckoutStatus::current('reason'))
        ->toBe("Sorry, we're closed right now. Please come back during our opening times.");
});

it('says when delivery is unavailable for the order', function(): void {
    addMenuToCart();
    $manager = Mockery::mock(resolve(CartManager::class))->makePartial();
    $manager->shouldReceive('deliveryChargeIsUnavailable')->andReturnTrue();
    app()->instance(CartManager::class, $manager);

    expect(CheckoutStatus::current('reason'))
        ->toBe("Delivery isn't available for this order. Try collection or another address.");
});

it('blocks checkout when the basket cannot be read', function(): void {
    app()->bind(CartManager::class, fn() => throw new RuntimeException('cart down'));

    expect(CheckoutStatus::current())->toBe([
        'blocked' => true,
        'reason' => 'Checkout is unavailable right now. Please try again.',
        'minimum' => null,
    ]);
});

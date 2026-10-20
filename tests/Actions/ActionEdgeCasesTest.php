<?php

declare(strict_types=1);

namespace Igniter\Storefront\Tests\Actions;

use Igniter\Cart\Classes\CartManager;
use Igniter\Flame\Exception\ApplicationException;
use Igniter\Local\Facades\Location;
use Igniter\Local\Models\Location as LocationModel;
use Igniter\Storefront\Actions\Fulfilment;
use Igniter\Storefront\Actions\ManageAccount;
use Igniter\Storefront\Actions\PlaceOrder;
use Igniter\Storefront\Actions\PlaceReservation;
use Mockery;

it('asks guests to sign in before deleting an account', function(): void {
    expect(fn() => (new ManageAccount)->deleteAccount('password'))
        ->toThrow(ApplicationException::class, 'Sign in to delete your account.');
});

it('asks for a location before booking', function(): void {
    LocationModel::query()->delete();
    Location::resetSession();
    (fn(): null => $this->model = null)->call(resolve('location'));

    expect(fn(): mixed => (new PlaceReservation)->handle(['date' => '2026-01-01', 'time' => '12:00']))
        ->toThrow(ApplicationException::class, 'Choose a location before booking a table.');
});

it('asks for a date and time when the order is not ASAP', function(): void {
    currentLocation();

    expect(fn() => (new Fulfilment)->update('collection', false, null, null, null, null))
        ->toThrow(ApplicationException::class, 'Pick a date and time for your order.');
});

it('will not save an order checkout would block', function(): void {
    $this->travelTo(now()->setTime(12, 0));
    currentLocation();
    Location::updateOrderType(LocationModel::COLLECTION);
    addMenuToCart();
    $manager = Mockery::mock(resolve(CartManager::class))->makePartial();
    $manager->shouldReceive('deliveryChargeIsUnavailable')->andReturnTrue();
    app()->instance(CartManager::class, $manager);

    expect(fn(): array => (new PlaceOrder)->prepare(['first_name' => 'Jane']))
        ->toThrow(ApplicationException::class, "Delivery isn't available for this order. Try collection or another address.");
});

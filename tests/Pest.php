<?php

declare(strict_types=1);

use Igniter\Cart\Classes\CartManager;
use Igniter\Cart\Models\Menu;
use Igniter\Cart\Models\Order;
use Igniter\Local\Facades\Location;
use Igniter\Local\Models\Location as LocationModel;
use Igniter\Local\Models\LocationArea;
use Igniter\Main\Classes\ThemeManager;
use Igniter\User\Models\Customer;
use Igniter\User\Models\User;
use Igniter\Storefront\Tests\TestCase;
use Illuminate\Support\Facades\URL;
use Mockery\MockInterface;

uses(TestCase::class)->in(__DIR__);

function actingAsSuperUser()
{
    return test()->actingAs(User::factory()->superUser()->create(), 'igniter-admin');
}

function actingAsCustomer(?Customer $customer = null): Customer
{
    $customer ??= Customer::factory()->create();
    test()->actingAs($customer, 'igniter-customer');

    return $customer;
}

/**
 * A location open around the clock for delivery and collection, set as the current one.
 */
function currentLocation(array $attributes = []): LocationModel
{
    $location = LocationModel::factory()->create($attributes);
    Location::setCurrent($location);
    if ($location->permalink_slug) {
        URL::defaults(['location' => $location->permalink_slug]);
    }

    return $location;
}

/**
 * Use these Glass theme Customize settings for the rest of the test.
 */
function setThemeSettings(array $data): void
{
    $theme = resolve(ThemeManager::class)->getActiveTheme();

    (function() use ($data): void {
        $this->customData = array_merge($this->getCustomData(), $data);
    })->call($theme);
}

function addMenuToCart(?Menu $menu = null, int $quantity = 1): string
{
    $menu ??= Menu::factory()->create(['menu_price' => 10]);

    // Requests use the cart of the current location; do the same here.
    $manager = resolve(CartManager::class);
    if ($locationId = Location::getId()) {
        $manager->cartInstance((int)$locationId);
    }

    $manager->addOrUpdateCartItem([
        'menuId' => $menu->getKey(),
        'quantity' => $quantity,
        'menu_options' => [],
    ]);

    return (string)$manager->getCart()->content()->first()->rowId;
}

/**
 * A paid order for the customer at the current location.
 */
function paidOrder(?Customer $customer = null, array $attributes = []): Order
{
    return Order::factory()->create(array_merge([
        'customer_id' => $customer?->getKey(),
        'location_id' => Location::getId(),
        'processed' => 1,
        'status_id' => (int)setting('default_order_status'),
    ], $attributes));
}

function completeOrder(Order $order): void
{
    $order->updateOrderStatus((int)collect(setting('completed_order_status'))->first());
}

function geoPosition(float $latitude = 51.5074, float $longitude = -0.1278, array $attributes = []): Igniter\Flame\Geolite\Model\Location
{
    return Igniter\Flame\Geolite\Model\Location::createFromArray(array_merge([
        'latitude' => $latitude,
        'longitude' => $longitude,
        'streetNumber' => '10',
        'streetName' => 'Downing Street',
        'locality' => 'London',
        'postalCode' => 'SW1A 2AA',
        'countryCode' => 'GB',
    ], $attributes));
}

/**
 * A circular delivery area around the point.
 */
function deliveryArea(LocationModel $location, float $latitude = 51.5074, float $longitude = -0.1278, int $radius = 5000): LocationArea
{
    $area = LocationArea::factory()->create([
        'location_id' => $location->getKey(),
        'name' => 'Central',
        'type' => 'circle',
        'boundaries' => ['circle' => json_encode(['lat' => $latitude, 'lng' => $longitude, 'radius' => $radius])],
        'conditions' => [['type' => 'all', 'amount' => 2, 'total' => 0, 'priority' => 1]],
    ]);
    $location->unsetRelation('delivery_areas');

    return $area;
}

function locationSettings(LocationModel $location, string $item, array $data): void
{
    $location->settings()->create(['item' => $item, 'data' => $data]);
    $location->unsetRelation('settings');
}

/**
 * Wrap the real location manager so single methods can be stubbed, keeping its session state.
 */
function spyLocation(): MockInterface
{
    $mock = Mockery::mock(resolve('location'))->makePartial();
    Location::swap($mock);

    return $mock;
}

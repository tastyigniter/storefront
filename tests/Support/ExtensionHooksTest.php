<?php

declare(strict_types=1);

namespace Igniter\Storefront\Tests\Support;

use Igniter\Cart\Models\Order;
use Igniter\Flame\Exception\ApplicationException;
use Igniter\Flame\Geolite\Facades\Geocoder;
use Igniter\Flame\Pagic\Router as PagicRouter;
use Igniter\Local\Facades\Location;
use Igniter\Local\Models\Location as LocationModel;
use Igniter\Main\Classes\ThemeManager;
use Igniter\Reservation\Models\DiningArea;
use Igniter\Reservation\Models\DiningTable;
use Igniter\Storefront\Actions\Fulfilment;
use Igniter\Storefront\Support\CheckoutStatus;
use Igniter\Storefront\Support\DineInTables;
use Igniter\Storefront\Support\PageRouter;
use Illuminate\Support\Facades\Event;
use Mockery;

afterEach(function(): void {
    DineInTables::$enabled = false;
    Event::forget('igniter.orange.checkCheckoutSecurity');
});

// page_url()

it('resolves theme page names to the storefront routes', function(): void {
    expect(resolve(PagicRouter::class))->toBeInstanceOf(PageRouter::class)
        ->and(page_url('account.login'))->toBe(url('/login'))
        ->and(page_url('local.menus', ['location' => 'soho']))->toBe(url('/soho/order'))
        ->and(page_url('checkout.success', ['hash' => 'abc']))->toBe(url('/checkout/success/abc'))
        ->and(page_url('group-order.join', ['hash' => 'xyz']))->toBe(url('/group-order/join/xyz'))
        ->and(page_url('no.such-page'))->toBe(url('/no.such-page'));
});

it('uses the current location for a menu page that needs one', function(): void {
    $location = currentLocation();

    expect(page_url('local.menus'))->toBe(url('/'.$location->permalink_slug.'/order'));
});

it('leaves page names alone when another theme is active', function(): void {
    $manager = Mockery::mock(resolve(ThemeManager::class))->makePartial();
    $manager->shouldReceive('getActiveTheme')->andReturnNull();
    app()->instance(ThemeManager::class, $manager);

    expect(page_url('account.login'))->toBe(url('/account.login'));
});

// Orange events

it('tells extensions when the order details change', function(): void {
    $this->travelTo(now()->setTime(12, 0));
    $location = currentLocation();
    Event::fake(['igniter.orange.fulfilmentUpdated']);

    resolve(Fulfilment::class)->update('collection', true, null, null, null, null);
    resolve(Fulfilment::class)->update('collection', false, now()->toDateString(), now()->addHours(2)->format('H:i'), null, null);
    resolve(Fulfilment::class)->switchLocation((int)$location->getKey(), LocationModel::COLLECTION);

    Event::assertDispatchedTimes('igniter.orange.fulfilmentUpdated', 3);
});

it('tells extensions when a delivery search picks the location', function(): void {
    $location = currentLocation();
    Event::fake(['igniter.orange.fulfilmentUpdated']);
    Geocoder::shouldReceive('geocode')->andReturn(collect());

    resolve(Fulfilment::class)->search('1 High Street');

    expect(Location::getId())->toBe($location->getKey());
    Event::assertDispatched('igniter.orange.fulfilmentUpdated');
});

it('lets extensions stop checkout with a reason', function(): void {
    $this->travelTo(now()->setTime(12, 0));
    currentLocation();
    addMenuToCart();
    Event::listen('igniter.orange.checkCheckoutSecurity', function(): void {
        throw new ApplicationException('Two guests have not ordered yet.');
    });

    expect(CheckoutStatus::current())->toMatchArray(['blocked' => true, 'reason' => 'Two guests have not ordered yet.']);
});

// Dine-in

it('reads dine-in support added to the location by the extension', function(): void {
    $location = currentLocation();
    expect(DineInTables::offered($location))->toBeFalse();

    $location->addDynamicMethod('hasDineIn', fn(): bool => true);
    expect(DineInTables::offered($location))->toBeTrue();
});

it('describes the table a dine-in order is served to', function(): void {
    DineInTables::$enabled = true;
    $location = currentLocation();
    $area = DiningArea::factory()->create(['location_id' => $location->getKey(), 'name' => 'Patio']);
    $table = DiningTable::factory()->create(['dining_area_id' => $area->getKey(), 'name' => 'Table 4', 'min_capacity' => 2, 'max_capacity' => 6]);
    // The dine-in extension adds the relation the same way.
    Order::extend(function(Order $order): void {
        $order->relation['belongsTo']['diningTable'] = [DiningTable::class, 'foreignKey' => 'dining_table_id'];
    });
    $order = paidOrder();
    $order->order_type = 'dinein';
    $order->setAttribute('dining_table_id', $table->getKey());

    expect(DineInTables::details($order))->toBe(['name' => 'Table 4', 'area' => 'Patio', 'guests' => '2–6']);
});

it('falls back to the table number when the table is gone', function(): void {
    currentLocation();
    $order = paidOrder();
    $order->order_type = 'dinein';
    $order->setAttribute('dining_table_id', 12);

    expect(DineInTables::details($order))->toBe(['name' => '#12', 'area' => null, 'guests' => '']);
});

<?php

declare(strict_types=1);

namespace Igniter\Storefront\Tests\Support;

use Igniter\Local\Facades\Location;
use Igniter\Local\Models\Location as LocationModel;
use Igniter\Storefront\Support\DeliveryCoverage;
use RuntimeException;

beforeEach(function(): void {
    $this->location = currentLocation();
});

it('records nothing when the location has no delivery areas', function(): void {
    expect(DeliveryCoverage::check($this->location, geoPosition()))->toBeNull()
        ->and(DeliveryCoverage::present())->toBeNull()
        ->and(DeliveryCoverage::message())->toBeNull();
});

it('reports a covered address with the area conditions', function(): void {
    deliveryArea($this->location);

    expect(DeliveryCoverage::check($this->location, geoPosition()))->toBe(DeliveryCoverage::COVERED);

    $coverage = DeliveryCoverage::present();
    expect($coverage['status'])->toBe(DeliveryCoverage::COVERED)
        ->and($coverage['area'])->toBe('Central')
        ->and($coverage['conditions'])->not->toBeEmpty()
        ->and(DeliveryCoverage::message()[0])->toBe('success');
});

it('still says covered when the area was removed since', function(): void {
    deliveryArea($this->location);
    DeliveryCoverage::check($this->location, geoPosition());
    spyLocation()->shouldReceive('coveredArea')->andThrow(new RuntimeException('gone'));

    expect(DeliveryCoverage::present())->toMatchArray(['status' => DeliveryCoverage::COVERED, 'area' => null, 'conditions' => []]);
});

it('reports an address outside the delivery areas', function(): void {
    deliveryArea($this->location);
    locationSettings($this->location, 'collection', ['is_enabled' => 0]);

    expect(DeliveryCoverage::check($this->location, geoPosition(40.7, -74.0)))->toBe(DeliveryCoverage::OUTSIDE)
        ->and(DeliveryCoverage::message())->toBe(['error', 'Sorry, '.$this->location->location_name." doesn't deliver to your address yet."]);
});

it('says nothing about coverage for collection orders', function(): void {
    deliveryArea($this->location);
    DeliveryCoverage::check($this->location, geoPosition());
    Location::updateOrderType(LocationModel::COLLECTION);

    expect(DeliveryCoverage::present())->toBeNull();
});

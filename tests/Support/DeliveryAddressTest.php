<?php

declare(strict_types=1);

namespace Igniter\Storefront\Tests\Support;

use Igniter\Local\Facades\Location;
use Igniter\System\Models\Country;
use Igniter\System\Models\Settings;
use Igniter\User\Models\Address;
use Igniter\Storefront\Support\DeliveryAddress;

beforeEach(function(): void {
    currentLocation();
    $this->countryId = (int)Country::query()->value('country_id');
    Settings::set('country_id', $this->countryId);
});

it('has no address before one is picked', function(): void {
    expect(DeliveryAddress::current())->toBeNull();
});

it('uses the saved address the customer picked', function(): void {
    $customer = actingAsCustomer();
    $address = Address::factory()->create([
        'customer_id' => $customer->getKey(),
        'address_1' => '1 Test Street',
        'address_2' => 'Flat 2',
        'city' => 'Leeds',
        'state' => 'West Yorkshire',
        'postcode' => 'LS1 1AA',
        'country_id' => 0,
    ]);
    Location::putSession('addressId', $address->getKey());

    expect(DeliveryAddress::current())->toBe([
        'address_id' => $address->getKey(),
        'address_1' => '1 Test Street',
        'address_2' => 'Flat 2',
        'city' => 'Leeds',
        'state' => 'West Yorkshire',
        'postcode' => 'LS1 1AA',
        'country_id' => $this->countryId,
    ]);
});

it('uses the typed address when it was not geocoded', function(): void {
    Location::putSession('searchQuery', '  1 Test Street  ');

    expect(DeliveryAddress::current())->toBe([
        'address_id' => null,
        'address_1' => '1 Test Street',
        'address_2' => null,
        'city' => '',
        'state' => null,
        'postcode' => '',
        'country_id' => $this->countryId,
    ]);
});

it('splits a geocoded address into parts', function(): void {
    $gb = Country::query()->where('iso_code_2', 'GB')->value('country_id');
    Location::putSession('searchQuery', '10 Downing Street');
    Location::updateUserPosition(geoPosition(attributes: [
        'adminLevels' => [['name' => 'Greater London', 'code' => 'LND', 'level' => 1]],
    ]));

    expect(DeliveryAddress::current())->toMatchArray([
        'address_1' => '10 Downing Street',
        'city' => 'London',
        'state' => 'Greater London',
        'postcode' => 'SW1A 2AA',
        'country_id' => (int)$gb,
    ]);
});

it('falls back to the typed text and site country for sparse geocodes', function(): void {
    Settings::set('country_id');
    Location::putSession('searchQuery', 'Somewhere');
    Location::updateUserPosition(geoPosition(attributes: [
        'streetNumber' => null,
        'streetName' => null,
        'locality' => null,
        'subLocality' => 'Soho',
        'countryCode' => null,
    ]));

    expect(DeliveryAddress::current())->toMatchArray([
        'address_1' => 'Somewhere',
        'city' => 'Soho',
        'state' => null,
        'country_id' => null,
    ]);
});

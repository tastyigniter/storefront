<?php

declare(strict_types=1);

namespace Igniter\Storefront\Tests\Http\Controllers;

use Igniter\Flame\Geolite\Facades\Geocoder;
use Igniter\Local\Facades\Location;
use Igniter\Local\Models\Location as LocationModel;
use Igniter\System\Models\Settings;
use Igniter\User\Models\Address;

beforeEach(function(): void {
    $this->travelTo(now()->setTime(12, 0));
    $this->location = currentLocation(['location_lat' => 51.5074, 'location_lng' => -0.1278]);
});

function fulfilment(array $overrides = []): array
{
    return array_merge([
        'order_type' => 'delivery',
        'asap' => true,
        'address' => '10 Downing Street, London',
    ], $overrides);
}

// Search from the homepage

it('finds a kitchen that delivers to the address', function(): void {
    deliveryArea($this->location);
    Geocoder::shouldReceive('geocode')->with('10 Downing Street, London')->andReturn(collect([geoPosition()]));

    $this->post(route('igniter.theme.fulfillment'), ['address' => '10 Downing Street, London'])
        ->assertRedirect(route('igniter.theme.order'))
        ->assertSessionHas('success');

    expect(Location::getSession('searchQuery'))->toBe('10 Downing Street, London')
        ->and(Location::orderType())->toBe(LocationModel::DELIVERY);
});

it('searches with a saved address', function(): void {
    $customer = actingAsCustomer();
    $address = Address::factory()->create([
        'customer_id' => $customer->getKey(),
        'address_1' => '1 Test Street',
        'address_2' => null,
        'city' => 'London',
        'postcode' => 'E1 6AN',
    ]);
    Geocoder::shouldReceive('geocode')->with('1 Test Street, London, E1 6AN')->andReturn(collect());

    $this->post(route('igniter.theme.fulfillment'), ['address_id' => $address->getKey()])
        ->assertRedirect(route('igniter.theme.order'));

    expect(Location::getSession('addressId'))->toBe($address->getKey());
});

it("rejects a saved address that is not the customer's", function(): void {
    actingAsCustomer();

    $this->from('/')
        ->post(route('igniter.theme.fulfillment'), ['address_id' => 999999])
        ->assertSessionHasErrors(['form' => 'alert.saved_address_missing']);
});

it('searches from a dropped map pin', function(): void {
    deliveryArea($this->location);
    Geocoder::shouldReceive('reverse')->andReturn(collect([geoPosition(51.6, -0.2)]));

    $this->post(route('igniter.theme.fulfillment'), ['latitude' => 51.5075, 'longitude' => -0.1279])
        ->assertRedirect(route('igniter.theme.order'));

    expect(Location::getSession('searchQuery'))->toBe('10 Downing Street, London, SW1A 2AA')
        ->and(Location::userPosition()->getCoordinates()->getLatitude())->toBe(51.5075);
});

it('explains a map pin it cannot place', function(): void {
    Geocoder::shouldReceive('reverse')->andReturn(collect());

    $this->from('/')
        ->post(route('igniter.theme.fulfillment'), ['latitude' => 51.5, 'longitude' => -0.1])
        ->assertSessionHasErrors(['form' => 'alert.pin_address_failed']);
});

it('says when no kitchen delivers to the address and coverage is required', function(): void {
    Settings::set('location_order', 1);
    deliveryArea($this->location);
    Geocoder::shouldReceive('geocode')->andReturn(collect([geoPosition(40.7128, -74.0060)]));

    $this->from('/')
        ->post(route('igniter.theme.fulfillment'), ['address' => 'New York'])
        ->assertSessionHasErrors(['form' => 'alert.no_delivery_yet']);
});

it('says when the address cannot be found and coverage is required', function(): void {
    Settings::set('location_order', 1);
    Geocoder::shouldReceive('geocode')->andReturn(collect());

    $this->from('/')
        ->post(route('igniter.theme.fulfillment'), ['address' => 'Nowhere'])
        ->assertSessionHasErrors(['form' => 'alert.address_not_found_check']);
});

it('keeps the current kitchen and explains it does not deliver there', function(): void {
    deliveryArea($this->location);
    Geocoder::shouldReceive('geocode')->andReturn(collect([geoPosition(40.7128, -74.0060)]));

    $this->post(route('igniter.theme.fulfillment'), ['address' => 'New York'])
        ->assertRedirect(route('igniter.theme.order'))
        ->assertSessionHas('error', 'alert.coverage_outside'.'alert.coverage_collection');
});

it('needs an address, saved address or pin', function(): void {
    $this->post(route('igniter.theme.fulfillment'), [])->assertSessionHasErrors('address');
});

// Order details modal

it('switches to collection as soon as possible', function(): void {
    $this->from(route('igniter.theme.order'))
        ->put(route('igniter.theme.fulfillment.update'), ['order_type' => 'collection', 'asap' => true])
        ->assertRedirect(route('igniter.theme.order'))
        ->assertSessionHasNoErrors();

    expect(Location::orderType())->toBe(LocationModel::COLLECTION);
});

it('schedules an order for later', function(): void {
    $time = now()->addHours(2)->format('H:i');

    $this->put(route('igniter.theme.fulfillment.update'), [
        'order_type' => 'collection',
        'asap' => false,
        'date' => now()->toDateString(),
        'time' => $time,
    ])->assertSessionHasNoErrors();

    expect(Location::orderDateTime()->format('H:i'))->toBe($time);
});

it('rejects a time the kitchen is closed', function(): void {
    $this->from(route('igniter.theme.order'))
        ->put(route('igniter.theme.fulfillment.update'), [
            'order_type' => 'collection',
            'asap' => false,
            'date' => now()->subDays(2)->toDateString(),
            'time' => '10:00',
        ])
        ->assertSessionHasErrors(['form' => 'alert.time_unavailable']);
});

it('rejects an order type the kitchen does not offer', function(): void {
    $this->from(route('igniter.theme.order'))
        ->put(route('igniter.theme.fulfillment.update'), ['order_type' => 'drone', 'asap' => true])
        ->assertSessionHasErrors(['form' => 'alert.order_type_unavailable']);
});

it('rejects ASAP when only scheduled orders are taken', function(): void {
    locationSettings($this->location, 'checkout', ['limit_orders' => 1]);

    $this->from(route('igniter.theme.order'))
        ->put(route('igniter.theme.fulfillment.update'), ['order_type' => 'collection', 'asap' => true])
        ->assertSessionHasErrors(['form' => 'alert.asap_unavailable']);
});

it('asks for a location first', function(): void {
    LocationModel::query()->delete();
    Location::resetSession();
    (fn(): null => $this->model = null)->call(resolve('location'));

    $this->from(route('igniter.theme.order'))
        ->put(route('igniter.theme.fulfillment.update'), ['order_type' => 'collection', 'asap' => true])
        ->assertSessionHasErrors(['form' => 'alert.choose_location_first']);
});

it('updates the delivery address', function(): void {
    deliveryArea($this->location);
    Geocoder::shouldReceive('geocode')->once()->andReturn(collect([geoPosition()]));

    $this->put(route('igniter.theme.fulfillment.update'), fulfilment())->assertSessionHasNoErrors();

    expect(Location::getSession('searchQuery'))->toBe('10 Downing Street, London');

    // The same address again does not call the geocoder.
    $this->put(route('igniter.theme.fulfillment.update'), fulfilment())->assertSessionHasNoErrors();
});

it('uses a saved delivery address', function(): void {
    $customer = actingAsCustomer();
    $address = Address::factory()->create(['customer_id' => $customer->getKey(), 'address_1' => '1 Test Street', 'address_2' => null, 'city' => 'Leeds', 'postcode' => 'LS1']);
    Geocoder::shouldReceive('geocode')->with('1 Test Street, Leeds, LS1')->andReturn(collect([geoPosition()]));

    $this->put(route('igniter.theme.fulfillment.update'), fulfilment(['address' => null, 'address_id' => $address->getKey()]))
        ->assertSessionHasNoErrors();

    expect(Location::getSession('addressId'))->toBe($address->getKey());
});

it('rejects a saved delivery address it cannot find', function(): void {
    actingAsCustomer();

    $this->from(route('igniter.theme.order'))
        ->put(route('igniter.theme.fulfillment.update'), fulfilment(['address' => null, 'address_id' => 999999]))
        ->assertSessionHasErrors(['form' => 'alert.saved_address_missing']);
});

it('asks for a delivery address', function(): void {
    $this->from(route('igniter.theme.order'))
        ->put(route('igniter.theme.fulfillment.update'), fulfilment(['address' => '  ']))
        ->assertSessionHasErrors(['form' => 'alert.enter_address']);
});

it('uses a dropped pin as the delivery address', function(): void {
    Geocoder::shouldReceive('reverse')->andReturn(collect([geoPosition()]));

    $this->put(route('igniter.theme.fulfillment.update'), fulfilment(['address' => null, 'latitude' => 51.5, 'longitude' => -0.12]))
        ->assertSessionHasNoErrors();

    expect(Location::getSession('searchQuery'))->toBe('10 Downing Street, London, SW1A 2AA');
});

it('explains a pin it cannot place for delivery', function(): void {
    Geocoder::shouldReceive('reverse')->andReturn(collect());

    $this->from(route('igniter.theme.order'))
        ->put(route('igniter.theme.fulfillment.update'), fulfilment(['latitude' => 51.5, 'longitude' => -0.12]))
        ->assertSessionHasErrors(['form' => 'alert.location_address_failed']);
});

it('explains a pin with no address for delivery', function(): void {
    Geocoder::shouldReceive('reverse')->andReturn(collect([geoPosition(attributes: [
        'streetNumber' => null, 'streetName' => null, 'locality' => null, 'postalCode' => null, 'formattedAddress' => null,
    ])]));

    $this->from(route('igniter.theme.order'))
        ->put(route('igniter.theme.fulfillment.update'), fulfilment(['address' => null, 'latitude' => 51.5, 'longitude' => -0.12]))
        ->assertSessionHasErrors(['form' => 'alert.location_address_failed']);
});

it('requires a known address when coverage is required', function(): void {
    Settings::set('location_order', 1);
    Geocoder::shouldReceive('geocode')->andReturn(collect());

    $this->from(route('igniter.theme.order'))
        ->put(route('igniter.theme.fulfillment.update'), fulfilment())
        ->assertSessionHasErrors(['form' => 'alert.address_not_found_check']);
});

it('requires an address inside the delivery area when coverage is required', function(): void {
    Settings::set('location_order', 1);
    deliveryArea($this->location);
    Geocoder::shouldReceive('geocode')->andReturn(collect([geoPosition(40.7128, -74.0060)]));

    $this->from(route('igniter.theme.order'))
        ->put(route('igniter.theme.fulfillment.update'), fulfilment())
        ->assertSessionHasErrors(['form' => 'alert.no_delivery_to_address']);
});

// Map pin lookup

it('turns a map pin into an address', function(): void {
    Geocoder::shouldReceive('reverse')->with(51.5, -0.12)->andReturn(collect([geoPosition()]));

    $this->postJson(route('igniter.theme.fulfillment.locate'), ['latitude' => 51.5, 'longitude' => -0.12])
        ->assertOk()
        ->assertJson(['address' => '10 Downing Street, London, SW1A 2AA', 'latitude' => 51.5, 'longitude' => -0.12]);
});

it('says when a map pin has no address', function(): void {
    Geocoder::shouldReceive('reverse')->andReturn(collect());

    $this->postJson(route('igniter.theme.fulfillment.locate'), ['latitude' => 51.5, 'longitude' => -0.12])
        ->assertStatus(422)
        ->assertJsonPath('message', 'alert.pin_no_address');
});

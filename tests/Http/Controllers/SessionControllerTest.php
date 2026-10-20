<?php

declare(strict_types=1);

namespace Igniter\Storefront\Tests\Http\Controllers;

use Igniter\Local\Facades\Location;
use Igniter\Local\Models\Location as LocationModel;

beforeEach(function(): void {
    $this->location = currentLocation();
});

it('switches the kitchen and order type', function(): void {
    $other = LocationModel::factory()->create();

    $this->from(route('igniter.theme.order'))
        ->post(route('igniter.theme.session'), ['location_id' => $other->getKey(), 'order_type' => 'collection'])
        ->assertRedirect(route('igniter.theme.order', ['location' => $other->permalink_slug]))
        ->assertSessionHasNoErrors();

    expect(Location::getId())->toBe($other->getKey())
        ->and(Location::orderType())->toBe(LocationModel::COLLECTION);
});

it('keeps the current order type when none is sent', function(): void {
    Location::updateOrderType(LocationModel::DELIVERY);

    $this->post(route('igniter.theme.session'), ['location_id' => $this->location->getKey()])->assertSessionHasNoErrors();

    expect(Location::orderType())->toBe(LocationModel::DELIVERY);
});

it('rejects a disabled kitchen', function(): void {
    $disabled = LocationModel::factory()->create(['location_status' => false]);

    $this->from(route('igniter.theme.order'))
        ->post(route('igniter.theme.session'), ['location_id' => $disabled->getKey()])
        ->assertSessionHasErrors(['form' => 'That location is not available.']);
});

it('rejects delivery from a kitchen without delivery', function(): void {
    $other = LocationModel::factory()->create();
    locationSettings($other, 'delivery', ['is_enabled' => 0]);

    $this->from(route('igniter.theme.order'))
        ->post(route('igniter.theme.session'), ['location_id' => $other->getKey(), 'order_type' => 'delivery'])
        ->assertSessionHasErrors(['form' => 'This location does not offer delivery.']);
});

it('rejects collection from a kitchen without collection', function(): void {
    $other = LocationModel::factory()->create();
    locationSettings($other, 'collection', ['is_enabled' => 0]);

    $this->from(route('igniter.theme.order'))
        ->post(route('igniter.theme.session'), ['location_id' => $other->getKey(), 'order_type' => 'collection'])
        ->assertSessionHasErrors(['form' => 'This location does not offer collection.']);
});

it('stays on the page it was opened from', function(): void {
    $other = LocationModel::factory()->create();

    $this->from(route('igniter.theme.locations'))
        ->post(route('igniter.theme.session'), ['location_id' => $other->getKey(), 'order_type' => 'collection'])
        ->assertRedirect(route('igniter.theme.locations'));

    expect(Location::getId())->toBe($other->getKey());
});

it('keeps a menu search when the kitchen changes', function(): void {
    $other = LocationModel::factory()->create();

    $this->from(route('igniter.theme.order', ['search' => 'jollof']))
        ->post(route('igniter.theme.session'), ['location_id' => $other->getKey()])
        ->assertRedirect(route('igniter.theme.order', ['location' => $other->permalink_slug, 'search' => 'jollof']));
});

it('stays on the booking page when switching kitchen from it', function(): void {
    $other = LocationModel::factory()->create();

    $this->post(route('igniter.theme.session'), ['location_id' => $other->getKey(), 'order_type' => 'collection', 'return_to' => 'booking'])
        ->assertRedirect(route('igniter.theme.booking'));

    expect(Location::getId())->toBe($other->getKey());
});

it('only returns to known pages', function(): void {
    $this->post(route('igniter.theme.session'), ['location_id' => $this->location->getKey(), 'return_to' => 'https://example.org'])
        ->assertSessionHasErrors('return_to');
});

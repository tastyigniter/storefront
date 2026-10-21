<?php

declare(strict_types=1);

namespace Igniter\Storefront\Tests\Http;

use Igniter\Local\Facades\Location;
use Igniter\Local\Models\Location as LocationModel;
use Igniter\Storefront\Support\LocationDirectory;
use Igniter\System\Models\Settings;
use Inertia\Testing\AssertableInertia;

beforeEach(function(): void {
    LocationModel::query()->update(['location_status' => false]);
    $this->soho = currentLocation(['location_name' => 'Soho Kitchen', 'location_city' => 'London', 'location_lat' => 51.5136, 'location_lng' => -0.1365]);
    $this->leeds = LocationModel::factory()->create(['location_name' => 'Leeds Diner', 'location_city' => 'Leeds', 'location_lat' => 53.8008, 'location_lng' => -1.5491]);
    locationSettings($this->leeds, 'delivery', ['is_enabled' => 0]);
    $this->nowhere = LocationModel::factory()->create(['location_name' => 'Ghost Kitchen', 'location_lat' => null, 'location_lng' => null]);
});

function names(array $locations): array
{
    return array_column($locations, 'name');
}

it('searches by name or address', function(): void {
    expect(names(LocationDirectory::locations(null, 'soho')))->toBe(['Soho Kitchen'])
        ->and(names(LocationDirectory::locations(null, ' LEEDS ')))->toBe(['Leeds Diner'])
        ->and(LocationDirectory::locations(null, 'paris'))->toBe([]);
});

it('filters by order type', function(): void {
    expect(names(LocationDirectory::locations(null, '', 'delivery')))->not->toContain('Leeds Diner')
        ->and(names(LocationDirectory::locations(null, '', 'collection')))->toContain('Leeds Diner')
        ->and(LocationDirectory::locations(null, '', 'dinein'))->toBe([])
        ->and(names(LocationDirectory::locations(null, '', 'unknown')))->toHaveCount(3);
});

it('sorts nearest first in miles, with unmapped locations last', function(): void {
    Settings::set('distance_unit', 'mi');
    $locations = LocationDirectory::locations(null, '', null, ['lat' => 51.5074, 'lng' => -0.1278]);

    expect(names($locations))->toBe(['Soho Kitchen', 'Leeds Diner', 'Ghost Kitchen'])
        ->and($locations[0]['distanceLabel'])->toBe('locations.distance_mi')
        ->and($locations[1]['distanceLabel'])->toBe('locations.distance_mi')
        ->and($locations[2]['distanceLabel'])->toBeNull();
});

it('shows distances in kilometres when the site uses them', function(): void {
    Settings::set('distance_unit', 'km');

    expect(LocationDirectory::locations(null, 'leeds', null, ['lat' => 51.5074, 'lng' => -0.1278])[0]['distanceLabel'])->toBe('locations.distance_km');
});

it('measures from the shared point or the searched address', function(): void {
    expect(LocationDirectory::origin(1.5, 2.5))->toBe(['lat' => 1.5, 'lng' => 2.5])
        ->and(LocationDirectory::origin(null, null))->toBeNull();

    Location::updateUserPosition(geoPosition(51.5, -0.12));
    expect(LocationDirectory::origin(null, null))->toBe(['lat' => 51.5, 'lng' => -0.12]);
});

it('lists locations with the filters applied', function(): void {
    $this->get(route('igniter.theme.locations', ['search' => 'leeds', 'type' => 'collection', 'sort' => 'distance', 'lat' => 51.5, 'lng' => -0.12]))
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page
            ->component('Locations/Index')
            ->where('locations.0.name', 'Leeds Diner')
            ->where('filters', ['search' => 'leeds', 'type' => 'collection', 'sort' => 'distance', 'needsPosition' => false]));
});

it('asks for a position when sorting nearest first without one', function(): void {
    $this->get(route('igniter.theme.locations', ['sort' => 'distance']))
        ->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page
            ->where('filters.needsPosition', true)
            ->where('locations.0.name', 'Ghost Kitchen'));
});

it('validates the filters', function(): void {
    $this->get(route('igniter.theme.locations', ['type' => 'drone', 'sort' => 'price', 'lat' => 95]))
        ->assertSessionHasErrors(['type', 'sort', 'lat', 'lng']);
});

it('refuses the locations page in single location mode', function(): void {
    setThemeSettings(['single_location' => true]);

    $this->get(route('igniter.theme.locations'))->assertNotFound();
});

it('pages the directory and keeps the filters', function(): void {
    setThemeSettings(['page_limit' => 2]);

    $this->get(route('igniter.theme.locations', ['page' => 2]))
        ->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page
            ->component('Locations/Index')
            ->where('locations.0.name', 'Soho Kitchen')
            ->where('pagination', ['currentPage' => 2, 'lastPage' => 2, 'perPage' => 2, 'total' => 3, 'from' => 3, 'to' => 3]));
});

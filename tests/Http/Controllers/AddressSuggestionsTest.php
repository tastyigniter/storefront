<?php

declare(strict_types=1);

namespace Igniter\Storefront\Tests\Http\Controllers;

use Igniter\Flame\Geolite\Contracts\AbstractProvider;
use Igniter\Flame\Geolite\Facades\Geocoder;
use Igniter\Flame\Geolite\Model\Coordinates;
use Igniter\Flame\Geolite\Place;
use Igniter\Storefront\Support\ThemeContent;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;

beforeEach(function(): void {
    currentLocation();
});

function geocoderProvider(): MockInterface
{
    $provider = Mockery::mock(AbstractProvider::class);
    Geocoder::shouldReceive('driver')->andReturn($provider);

    return $provider;
}

it('suggests addresses with their coordinates', function(): void {
    geocoderProvider()->shouldReceive('placesAutocomplete')->once()->andReturn(collect([
        (new Place)->placeId('1')->title('Hertford Street')->description('Hertford Street, Coventry, CV1 1LF')->provider('nominatim')
            ->withData('latitude', '52.4068')->withData('longitude', '-1.5104'),
        (new Place)->placeId('g-2')->title('Coventry Station')->description('Coventry Station')->provider('google'),
    ]));

    $this->getJson(route('igniter.theme.fulfillment.suggestions', ['query' => 'Hertford']))
        ->assertOk()
        ->assertJsonPath('suggestions.0', [
            'id' => '1',
            'title' => 'Hertford Street',
            'description' => 'Hertford Street, Coventry, CV1 1LF',
            'provider' => 'nominatim',
            'latitude' => 52.4068,
            'longitude' => -1.5104,
        ])
        ->assertJsonPath('suggestions.1.description', '')
        ->assertJsonPath('suggestions.1.latitude', null);
});

it('waits for three characters before searching', function(): void {
    Geocoder::shouldReceive('driver')->never();

    $this->getJson(route('igniter.theme.fulfillment.suggestions', ['query' => 'He ']))
        ->assertOk()
        ->assertExactJson(['suggestions' => []]);
});

it('suggests nothing when address suggestions are turned off', function(): void {
    setThemeSettings(['address_suggestions' => 0]);
    Geocoder::shouldReceive('driver')->never();

    $this->getJson(route('igniter.theme.fulfillment.suggestions', ['query' => 'Hertford']))
        ->assertOk()
        ->assertExactJson(['suggestions' => []]);
});

it('shares whether address suggestions are on, defaulting to on', function(): void {
    expect(ThemeContent::site('addressSuggestions'))->toBeTrue();

    setThemeSettings(['address_suggestions' => '0']);

    expect(ThemeContent::site('addressSuggestions'))->toBeFalse();
});

it('returns no suggestions when the geocoder fails', function(): void {
    geocoderProvider()->shouldReceive('placesAutocomplete')->andThrow(new RuntimeException('quota'));

    $this->getJson(route('igniter.theme.fulfillment.suggestions', ['query' => 'Hertford']))
        ->assertOk()
        ->assertExactJson(['suggestions' => []]);
});

it('looks up the coordinates of a picked suggestion', function(): void {
    geocoderProvider()->shouldReceive('getPlaceCoordinates')->andReturn(new Coordinates(52.4, -1.5));

    $this->getJson(route('igniter.theme.fulfillment.place', ['placeId' => 'g-2']))
        ->assertOk()
        ->assertExactJson(['latitude' => 52.4, 'longitude' => -1.5]);
});

it('says when a picked suggestion cannot be placed', function(): void {
    geocoderProvider()->shouldReceive('getPlaceCoordinates')->andReturn(new Coordinates(0, 0));

    $this->getJson(route('igniter.theme.fulfillment.place', ['placeId' => 'g-2']))
        ->assertUnprocessable()
        ->assertJsonStructure(['message']);
});

<?php

declare(strict_types=1);

namespace Igniter\Storefront\Support;

use Igniter\Flame\Geolite\Contracts\PlaceInterface;
use Igniter\Flame\Geolite\Facades\Geocoder;
use Igniter\Flame\Geolite\GeoQuery;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Address suggestions as the customer types, from the geocoder set under
 * System > Settings > General (Nominatim or Google), as the Orange theme does.
 */
class AddressSuggestions
{
    public const int MIN_LENGTH = 3;

    public const int LIMIT = 6;

    /**
     * @return list<array{id: string, title: string, description: string, provider: string, latitude: ?float, longitude: ?float}>
     */
    public static function search(string $query): array
    {
        $query = trim($query);
        if (!self::enabled() || mb_strlen($query) < self::MIN_LENGTH) {
            return [];
        }

        try {
            $places = Geocoder::driver()->placesAutocomplete(GeoQuery::create($query));
        } catch (Throwable $exception) {
            Log::warning('Address suggestions failed: '.$exception->getMessage());

            return [];
        }

        return collect($places)
            ->filter(fn($place): bool => $place instanceof PlaceInterface && $place->getTitle() !== '')
            ->take(self::LIMIT)
            ->map(function(PlaceInterface $place): array {
                $latitude = $place->getData('latitude');
                $longitude = $place->getData('longitude');

                return [
                    'id' => $place->getPlaceId(),
                    'title' => $place->getTitle(),
                    // Nominatim repeats the title at the start of the description.
                    'description' => $place->getDescription() === $place->getTitle() ? '' : $place->getDescription(),
                    'provider' => $place->getProvider(),
                    'latitude' => is_numeric($latitude) ? (float)$latitude : null,
                    'longitude' => is_numeric($longitude) ? (float)$longitude : null,
                ];
            })
            ->values()
            ->all();
    }

    /** Customize > General > Address suggestions, on unless turned off. */
    public static function enabled(): bool
    {
        return (bool)ThemeContent::value('address_suggestions', true);
    }

    /**
     * Coordinates for a suggestion that came without them (Google), or null when unknown.
     *
     * @return array{latitude: float, longitude: float}|null
     */
    public static function coordinates(string $placeId): ?array
    {
        try {
            $coordinates = Geocoder::driver()->getPlaceCoordinates(GeoQuery::create($placeId));
        } catch (Throwable $exception) {
            Log::warning('Address suggestion lookup failed: '.$exception->getMessage());

            return null;
        }

        $latitude = $coordinates->getLatitude();
        $longitude = $coordinates->getLongitude();

        return $latitude == 0.0 && $longitude == 0.0 ? null : ['latitude' => $latitude, 'longitude' => $longitude];
    }
}

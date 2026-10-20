<?php

declare(strict_types=1);

namespace Igniter\Storefront\Support;

use Igniter\Local\Facades\Location;
use Igniter\Local\Models\Location as LocationModel;
use Igniter\Storefront\Storefront;

/**
 * Location listing: search, order type and cuisine filters (multivendor), nearest first.
 */
class LocationDirectory
{
    public const array ORDER_TYPES = ['delivery' => 'offersDelivery', 'collection' => 'offersCollection', 'dinein' => 'offersDineIn'];

    /**
     * Locations matching the cuisine, search text and order type, by name or nearest first.
     *
     * @param array{lat: float, lng: float}|null $from Where to measure distance from.
     * @return list<array<string, mixed>>
     */
    public static function locations(?int $cuisineId, string $search = '', ?string $orderType = null, ?array $from = null): array
    {
        $locations = StorefrontState::locations();
        $class = 'IgniterLabs\\MultiVendor\\Models\\Cuisine';
        $locations = $cuisineId && class_exists($class) ? self::matching($locations, $cuisineId, $class) : $locations;

        $search = mb_strtolower(trim($search));
        if ($search !== '') {
            $locations = array_filter($locations, fn(array $location): bool => str_contains(
                mb_strtolower($location['name'].' '.$location['address']),
                $search,
            ));
        }

        if ($flag = self::ORDER_TYPES[$orderType] ?? null) {
            $locations = array_filter($locations, fn(array $location): bool => (bool)$location[$flag]);
        }

        return $from ? self::nearestFirst(array_values($locations), $from) : array_values($locations);
    }

    /**
     * Where the customer is: the point the browser shared, else the address they searched for.
     *
     * @return array{lat: float, lng: float}|null
     */
    public static function origin(?float $latitude, ?float $longitude): ?array
    {
        if ($latitude !== null && $longitude !== null) {
            return ['lat' => $latitude, 'lng' => $longitude];
        }

        $position = Location::userPosition();
        if ($position?->hasCoordinates()) {
            return ['lat' => (float)$position->getCoordinates()->getLatitude(), 'lng' => (float)$position->getCoordinates()->getLongitude()];
        }

        return null;
    }

    /**
     * Sort by distance and label each location with it, in the site's distance unit.
     *
     * @param list<array<string, mixed>> $locations
     * @param array{lat: float, lng: float} $from
     * @return list<array<string, mixed>>
     */
    protected static function nearestFirst(array $locations, array $from): array
    {
        $coordinates = LocationModel::query()
            ->whereKey(array_column($locations, 'id'))
            ->get(['location_id', 'location_lat', 'location_lng'])
            ->keyBy('location_id');
        $miles = setting('distance_unit', 'mi') !== 'km';

        foreach ($locations as $index => $location) {
            $model = $coordinates->get($location['id']);
            $kilometres = $model && $model->location_lat !== null && $model->location_lng !== null
                ? self::kilometres($from, (float)$model->location_lat, (float)$model->location_lng)
                : null;
            $distance = $kilometres === null ? null : ($miles ? $kilometres * 0.621371 : $kilometres);
            $locations[$index]['distance'] = $distance;
            $locations[$index]['distanceLabel'] = $distance === null ? null : Storefront::lang('locations.'.($miles ? 'distance_mi' : 'distance_km'), [
                'distance' => number_format($distance, $distance < 10 ? 1 : 0),
            ]);
        }

        // Locations without coordinates go last.
        usort($locations, fn(array $a, array $b): int => ($a['distance'] ?? INF) <=> ($b['distance'] ?? INF));

        return $locations;
    }

    /**
     * Great-circle distance between two points.
     *
     * @param array{lat: float, lng: float} $from
     */
    protected static function kilometres(array $from, float $latitude, float $longitude): float
    {
        $dLat = deg2rad($latitude - $from['lat']);
        $dLng = deg2rad($longitude - $from['lng']);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($from['lat'])) * cos(deg2rad($latitude)) * sin($dLng / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public static function cuisines(): array
    {
        return VendorSignup::cuisines();
    }

    /**
     * @param list<array<string, mixed>> $locations
     * @param list<int> $ids
     * @return list<array<string, mixed>>
     */
    public static function filter(array $locations, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return array_values(array_filter(
            $locations,
            fn(array $location): bool => in_array($location['id'], $ids, true),
        ));
    }

    /**
     * @param list<array<string, mixed>> $locations
     * @return list<array<string, mixed>>
     *
     * @codeCoverageIgnore
     */
    protected static function matching(array $locations, int $cuisineId, string $class): array
    {
        $cuisine = $class::query()->whereIsEnabled()->whereKey($cuisineId)->first();
        $ids = $cuisine
            ? $cuisine->locations->map(fn($location): int => (int)$location->getKey())->all()
            : [];

        return self::filter($locations, $ids);
    }
}

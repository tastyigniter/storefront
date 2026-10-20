<?php

declare(strict_types=1);

namespace Igniter\Storefront\Support;

use Igniter\Flame\Geolite\Facades\Geocoder;
use Igniter\Flame\Geolite\Model\Location as UserLocation;

/**
 * A map pin dropped by the customer: reverse-geocoded for its address parts,
 * but keeping the exact coordinates of the pin.
 */
class GeoPin
{
    public static function locate(float $latitude, float $longitude): ?UserLocation
    {
        $position = Geocoder::reverse($latitude, $longitude)?->first();

        return $position?->setCoordinates($latitude, $longitude);
    }

    /**
     * A short address such as "10 Downing Street, London, SW1A 2AA".
     */
    public static function label(UserLocation $position): string
    {
        $street = trim($position->getStreetNumber().' '.$position->getStreetName());
        $parts = array_filter([
            $street,
            $position->getLocality() ?: $position->getSubLocality(),
            $position->getPostalCode(),
        ]);

        return $parts !== [] ? implode(', ', array_unique($parts)) : (string)$position->getFormattedAddress();
    }
}

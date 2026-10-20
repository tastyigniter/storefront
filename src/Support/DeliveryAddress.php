<?php

declare(strict_types=1);

namespace Igniter\Storefront\Support;

use Igniter\Local\Facades\Location;
use Igniter\System\Models\Country;
use Igniter\User\Facades\Auth;
use Igniter\User\Models\Address;

/**
 * Turns the address picked in the order details modal into order address fields.
 */
class DeliveryAddress
{
    /**
     * @return array{address_id: ?int, address_1: string, address_2: ?string, city: string, state: ?string, postcode: string, country_id: ?int}|null
     */
    public static function current(): ?array
    {
        $addressId = Location::getSession('addressId');
        $saved = $addressId ? Auth::customer()?->addresses()->find($addressId) : null;
        if ($saved instanceof Address) {
            return [
                'address_id' => (int)$saved->getKey(),
                'address_1' => (string)$saved->address_1,
                'address_2' => $saved->address_2,
                'city' => (string)$saved->city,
                'state' => $saved->state,
                'postcode' => (string)$saved->postcode,
                'country_id' => (int)$saved->country_id ?: self::defaultCountryId(),
            ];
        }

        $search = trim((string)Location::getSession('searchQuery'));
        if ($search === '') {
            return null;
        }

        $position = Location::userPosition();
        if (!$position?->hasCoordinates()) {
            return [
                'address_id' => null,
                'address_1' => $search,
                'address_2' => null,
                'city' => '',
                'state' => null,
                'postcode' => '',
                'country_id' => self::defaultCountryId(),
            ];
        }

        $countryId = $position->getCountryCode()
            ? Country::query()->where('iso_code_2', $position->getCountryCode())->value('country_id')
            : null;

        return [
            'address_id' => null,
            'address_1' => trim($position->getStreetNumber().' '.$position->getStreetName()) ?: $search,
            'address_2' => null,
            'city' => (string)($position->getLocality() ?: $position->getSubLocality()),
            'state' => $position->getAdminLevels()->first()?->getName(),
            'postcode' => (string)$position->getPostalCode(),
            'country_id' => $countryId ? (int)$countryId : self::defaultCountryId(),
        ];
    }

    protected static function defaultCountryId(): ?int
    {
        return setting('country_id') ? (int)setting('country_id') : null;
    }
}

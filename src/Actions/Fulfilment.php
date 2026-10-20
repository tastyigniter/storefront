<?php

declare(strict_types=1);

namespace Igniter\Storefront\Actions;

use Carbon\Carbon;
use Igniter\Flame\Exception\ApplicationException;
use Igniter\Flame\Geolite\Facades\Geocoder;
use Igniter\Flame\Geolite\Model\Location as UserLocation;
use Igniter\Local\Facades\Location;
use Igniter\Local\Models\Location as LocationModel;
use Igniter\Local\Models\LocationArea;
use Igniter\Storefront\Storefront;
use Igniter\Storefront\Support\DeliveryCoverage;
use Igniter\Storefront\Support\DineInTables;
use Igniter\Storefront\Support\GeoPin;
use Igniter\Storefront\Support\GroupOrders;
use Igniter\Storefront\Support\Schedules;
use Igniter\User\Facades\Auth;
use Illuminate\Support\Facades\Event;

class Fulfilment
{
    public function search(
        ?string $address,
        ?int $addressId = null,
        ?float $latitude = null,
        ?float $longitude = null,
    ): LocationModel {
        [$address, $addressId, $userLocation] = $this->locate($address, $addressId, $latitude, $longitude, 'alert.pin_address_failed');

        $userLocation ??= Geocoder::geocode($address)?->first();
        $coordinates = $userLocation?->hasCoordinates() ? $userLocation->getCoordinates() : null;

        $match = $coordinates ? Location::searchByCoordinates($coordinates)->first(fn(LocationModel $location): bool => $location->searchDeliveryArea($coordinates) instanceof LocationArea) : null;

        // Without enforced coverage, keep ordering from the current kitchen.
        if (!$match instanceof LocationModel && !Location::requiresUserPosition()) {
            $match = Location::current() ?? Location::currentOrDefault();
        }

        if (!$match instanceof LocationModel) {
            throw new ApplicationException($coordinates === null
                ? Storefront::lang('alert.address_not_found_check')
                : Storefront::lang('alert.no_delivery_yet'));
        }

        if ($userLocation && $coordinates) {
            Location::updateUserPosition($userLocation);
        }

        Location::putSession('searchQuery', $address);
        Location::putSession('addressId', $addressId);
        Location::setCurrent($match);
        Location::updateOrderType(LocationModel::DELIVERY);
        Event::dispatch('igniter.orange.fulfilmentUpdated');

        DeliveryCoverage::check($match, $userLocation);

        return $match;
    }

    public function update(
        string $orderType,
        bool $asap,
        ?string $date,
        ?string $time,
        ?string $address,
        ?int $addressId,
        ?float $latitude = null,
        ?float $longitude = null,
        ?int $areaId = null,
        ?int $tableId = null,
    ): void {
        $location = Location::current() ?? Location::currentOrDefault();
        if (!$location instanceof LocationModel) {
            throw new ApplicationException(Storefront::lang('alert.choose_location_first'));
        }

        $option = collect(Schedules::fulfilmentOptions())->firstWhere('code', $orderType);
        if (!$option) {
            throw new ApplicationException(Storefront::lang('alert.order_type_unavailable', ['location' => $location->location_name]));
        }

        if (!$asap && (!$date || !$time)) {
            throw new ApplicationException(Storefront::lang('alert.pick_date_time'));
        }

        if ($asap && !$option['asap']) {
            throw new ApplicationException(Storefront::lang('alert.asap_unavailable'));
        }

        if ($orderType === LocationModel::DELIVERY) {
            $this->updateDeliveryAddress($location, $address, $addressId, $latitude, $longitude);
        }

        // Guests keep the host's table. Anyone else stores the table they just chose.
        GroupOrders::hostTableId() ? DineInTables::pinHostTable() : ($orderType !== 'dinein' || DineInTables::remember((int)$location->getKey(), $areaId, $tableId));

        Location::updateOrderType($orderType);

        if ($asap) {
            Location::updateScheduleTimeSlot(null, true);
        } else {
            $dateTime = Carbon::parse($date.' '.$time);
            if (!Location::checkOrderTime($dateTime, $orderType)) {
                throw new ApplicationException(Storefront::lang('alert.time_unavailable'));
            }

            Location::updateScheduleTimeSlot($dateTime, false);
        }

        GroupOrders::rememberHostTable();

        // Same hook the Orange order details modal fires, e.g. a group order follows the host's changes.
        Event::dispatch('igniter.orange.fulfilmentUpdated');
    }

    public function switchLocation(int $locationId, ?string $orderType = null): void
    {
        $location = LocationModel::query()->whereIsEnabled()->find($locationId);
        if (!$location instanceof LocationModel) {
            throw new ApplicationException(Storefront::lang('alert.location_unavailable'));
        }

        $orderType ??= (string)Location::orderType();
        $orderType = match ($orderType) {
            LocationModel::COLLECTION => LocationModel::COLLECTION,
            'dinein' => 'dinein',
            default => LocationModel::DELIVERY,
        };

        if ($orderType === LocationModel::DELIVERY && !$location->hasDelivery()) {
            throw new ApplicationException(Storefront::lang('alert.no_delivery'));
        }

        if ($orderType === LocationModel::COLLECTION && !$location->hasCollection()) {
            throw new ApplicationException(Storefront::lang('alert.no_collection'));
        }

        if ($orderType === 'dinein' && !DineInTables::offered($location)) {
            throw new ApplicationException(Storefront::lang('alert.no_dinein'));
        }

        Location::setCurrent($location);
        Location::updateOrderType($orderType);

        Event::dispatch('igniter.orange.fulfilmentUpdated');
    }

    public static function formatAddress(object $address): string
    {
        return implode(', ', array_filter([
            $address->address_1,
            $address->address_2,
            $address->city,
            $address->postcode,
        ]));
    }

    /**
     * A saved address, or the street the pin landed on.
     *
     * @return array{0: string, 1: ?int, 2: ?UserLocation}
     */
    private function locate(
        ?string $address,
        ?int $addressId,
        ?float $latitude,
        ?float $longitude,
        string $pinFailure,
    ): array {
        if ($addressId) {
            $saved = Auth::customer()?->addresses()->find($addressId);
            if (!$saved) {
                throw new ApplicationException(Storefront::lang('alert.saved_address_missing'));
            }

            $address = self::formatAddress($saved);
        }

        if ($latitude === null || $longitude === null) {
            return [trim((string)$address), $addressId, null];
        }

        $position = GeoPin::locate($latitude, $longitude);
        if (!$position?->hasCoordinates()) {
            throw new ApplicationException(Storefront::lang($pinFailure));
        }

        return [trim((string)$address) ?: GeoPin::label($position), $addressId, $position];
    }

    protected function updateDeliveryAddress(
        LocationModel $location,
        ?string $address,
        ?int $addressId,
        ?float $latitude = null,
        ?float $longitude = null,
    ): void {
        $pinned = $latitude !== null && $longitude !== null;
        [$address, $addressId, $userLocation] = $this->locate(
            $address,
            $pinned ? null : $addressId,
            $latitude,
            $longitude,
            'alert.location_address_failed',
        );

        if ($pinned) {
            if ($address === '') {
                throw new ApplicationException(Storefront::lang('alert.location_address_failed'));
            }
        } else {
            if ($address === '') {
                throw new ApplicationException(Storefront::lang('alert.enter_address'));
            }

            // Skip the geocoder when the address has not changed.
            if ($address === (string)Location::getSession('searchQuery') && Location::userPosition()?->hasCoordinates()) {
                Location::putSession('addressId', $addressId);

                return;
            }

            $userLocation = Geocoder::geocode($address)?->first();
        }

        $area = $userLocation?->hasCoordinates() ? $location->searchDeliveryArea($userLocation->getCoordinates()) : null;

        // Coverage is only enforced when the site requires a customer address before ordering.
        if (Location::requiresUserPosition()) {
            if (!$userLocation?->hasCoordinates()) {
                throw new ApplicationException(Storefront::lang('alert.address_not_found_check'));
            }

            if (!$area instanceof LocationArea) {
                throw new ApplicationException(Storefront::lang('alert.no_delivery_to_address', ['location' => $location->location_name]));
            }
        }

        if ($userLocation?->hasCoordinates()) {
            Location::updateUserPosition($userLocation);
        }

        DeliveryCoverage::record($location, $userLocation, $area);
        Location::putSession('searchQuery', $address);
        Location::putSession('addressId', $addressId);
    }
}
